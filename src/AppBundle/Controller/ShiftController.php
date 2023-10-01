<?php

namespace AppBundle\Controller;

use DateTime;
use AppBundle\Entity\Job;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Event\ShiftBookedEvent;
use AppBundle\Event\ShiftFreedEvent;
use AppBundle\Event\ShiftValidatedEvent;
use AppBundle\Event\ShiftInvalidatedEvent;
use AppBundle\Event\ShiftDeletedEvent;
use AppBundle\Form\AutocompleteBeneficiaryType;
use AppBundle\Form\AutocompleteBeneficiaryCollectionType;
use AppBundle\Form\RadioChoiceType;
use AppBundle\Form\ShiftType;
use AppBundle\Security\MembershipVoter;
use AppBundle\Security\ShiftVoter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;


/**
 * @Route("shift")
 */
class ShiftController extends Controller
{
    private $forbid_own_shift_book_admin;
    private $forbid_own_shift_free_admin;
    private $forbid_own_shift_validate_admin;
    private $use_fly_and_fixed;
    private $use_time_log_saving;
    private $time_log_saving_shift_free_min_time_in_advance_days;

    public function __construct(bool $forbid_own_shift_book_admin, bool $forbid_own_shift_free_admin, bool $forbid_own_shift_validate_admin, bool $use_fly_and_fixed, bool $use_time_log_saving, $time_log_saving_shift_free_min_time_in_advance_days)
    {
        $this->forbid_own_shift_book_admin = $forbid_own_shift_book_admin;
        $this->forbid_own_shift_free_admin = $forbid_own_shift_free_admin;
        $this->forbid_own_shift_validate_admin = $forbid_own_shift_validate_admin;
        $this->use_fly_and_fixed = $use_fly_and_fixed;
        $this->use_time_log_saving = $use_time_log_saving;
        $this->time_log_saving_shift_free_min_time_in_advance_days = $time_log_saving_shift_free_min_time_in_advance_days;
    }

    /**
     * @Route("/new", name="shift_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $job = $em->getRepository(Job::class)->findOneBy(array());

        if (!$job) {
            $session->getFlashBag()->add('warning', 'Commençons par créer un poste de bénévolat');
            return $this->redirectToRoute('job_new');
        }

        $shift = new Shift();
        $form = $this->get('form.factory')->createNamed('bucket_shift_add_form', ShiftType::class, $shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $number = $form->get('number')->getData();
            while ($number > 1) {
                $s = clone($shift);
                $s->setCreatedBy($current_user);
                $em->persist($s);
                $number --;
            }
            $shift->setCreatedBy($current_user);
            $em->persist($shift);
            $em->flush();

            $success = true;
            $message = 'Le créneau a bien été créé !';
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de créer le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $bucket = $this->get('shift_service')->getShiftBucketFromShift($shift);
                $card =  $this->get('twig')->render('admin/booking/_partial/bucket_card.html.twig', array(
                    'bucket' => $bucket,
                    'start' => 6,
                    'end' => 22,
                    'line' => 0,
                ));
                $modal = $this->forward('AppBundle\Controller\BookingController::showBucketAction', [
                    'bucket' => $bucket->getShiftWithMinId()
                ])->getContent();
                return new JsonResponse(array('message'=>$message, 'card' => $card, 'modal' => $modal), 201);
            } else {
                return new JsonResponse(array('message'=>$message), 400);
            }
        } else {
            if ($success) {
                $session->getFlashBag()->add('success', $message);
                return $this->redirectToRoute('booking_admin');
            } else {
                if ($form->isSubmitted()) {
                    $session->getFlashBag()->add('error', $message);
                }
                return $this->render('admin/shift/new.html.twig', array(
                    "form" => $form->createView()
                ));
            }
        }
    }

    /**
     * Book a shift.
     *
     * @Route("/{id}/book", name="shift_book", methods={"POST"})
     */
    public function bookShiftAction(Request $request, Shift $shift): Response
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $content = json_decode($request->getContent());
        $beneficiaryId = $content->beneficiaryId;
        $isFixe = $content->typeService;

        $beneficiary = $em->getRepository('AppBundle:Beneficiary')->find($beneficiaryId);

        // Check if the shift is bookable by the given beneficiary
        // Also check if the beneficiary belongs to the same membership as the current user
        if (!$beneficiary
            || !$this->get('shift_service')->isShiftBookable($shift, $beneficiary)
            || !$this->isGranted(MembershipVoter::EDIT, $beneficiary->getMembership())
        ) {
            $session->getFlashBag()->add("error", "Impossible de réserver ce créneau");
            return new Response($this->generateUrl('booking'), 205);
        }

        if (!$shift->getBooker()) {
            $shift->setBooker($current_user);
            $shift->setBookedTime(new DateTime('now'));
        }
        $shift->setShifter($beneficiary);
        $shift->setLastShifter(null);
        $shift->setFixe($isFixe);
        $em->persist($shift);

        $member = $beneficiary->getMembership();
        if ($member->getFirstShiftDate() == null) {
            $firstDate = clone($shift->getStart());
            $firstDate->setTime(0, 0, 0);
            $member->setFirstShiftDate($firstDate);
            $em->persist($member);
        }

        $em->flush();

        $dispatcher = $this->get('event_dispatcher');
        $dispatcher->dispatch(ShiftBookedEvent::NAME, new ShiftBookedEvent($shift, false));

        $session->getFlashBag()->add("success", "Ce créneau a bien été réservé !");
        return new Response($this->generateUrl('homepage'), 200);
    }

    /**
     * Book a shift (admin side).
     *
     * @Route("/{id}/book_admin", name="shift_book_admin", methods={"GET","POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function bookShiftAdminAction(Request $request, Shift $shift)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $form = $this->createShiftBookAdminForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $beneficiary = $form->get("shifter")->getData();
            $fixe = $form->get("fixe")->getData();
            $shifter_is_current_user = $current_user->getBeneficiary() == $beneficiary;

            if ($shift->getShifter()) {
                $success = false;
                $message = "Désolé, ce créneau est déjà réservé";
            } elseif ($shift->getFormation() && !$beneficiary->getFormations()->contains($shift->getFormation())) {
                $success = false;
                $message = "Désolé, ce bénévole n'a pas la qualification necessaire (" . $shift->getFormation()->getName() . ")";
            } elseif ($beneficiary->getMembership()->isCurrentlyExemptedFromShifts($shift->getStart())) {
                $success = false;
                $message = "Désolé, ce bénévole est exempté de créneau sur cette période";
            // check if user is allowed to book shift
            } elseif ($shifter_is_current_user && $this->forbid_own_shift_book_admin && !$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $success = false;
                $message = "Vous ne pouvez pas réserver votre propre créneau.";
            } else {
                $shift->setBooker($current_user);
                $shift->setBookedTime(new DateTime('now'));
                $shift->setShifter($beneficiary);
                $shift->setLastShifter(null);
                $shift->setFixe($fixe);

                $em->persist($shift);

                $member = $beneficiary->getMembership();
                if ($member->getFirstShiftDate() == null) {
                    $firstDate = clone($shift->getStart());
                    $firstDate->setTime(0, 0, 0);
                    $member->setFirstShiftDate($firstDate);
                    $em->persist($member);
                }
                $em->flush();

                $dispatcher = $this->get('event_dispatcher');
                $dispatcher->dispatch(ShiftBookedEvent::NAME, new ShiftBookedEvent($shift, true));

                $message = "Créneau réservé avec succès pour " . $shift->getShifter();
                $success = true;
            }
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de réserver le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $bucket = $this->get('shift_service')->getShiftBucketFromShift($shift);
                $card =  $this->get('twig')->render('admin/booking/_partial/bucket_card.html.twig', array(
                    'bucket' => $bucket,
                    'start' => 6,
                    'end' => 22,
                    'line' => 0,
                ));
                $modal = $this->forward('AppBundle\Controller\BookingController::showBucketAction', [
                    'bucket' => $bucket->getShiftWithMinId()
                ])->getContent();
                return new JsonResponse(array('message' => $message, 'card' => $card, 'modal' => $modal), 200);
            } else {
                return new JsonResponse(array('message' => $message), 400);
            }
        } else {
            $session->getFlashBag()->add($success ? 'success' : 'error', $message);
            return $this->redirectToRoute('booking_admin');
        }
    }

    /**
     * Free a shift.
     *
     * @Route("/{id}/free", name="shift_free", methods={"POST"})
     */
    public function freeShiftAction(Request $request, Shift $shift)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $this->denyAccessUnlessGranted(ShiftVoter::FREE, $shift);

        $form = $this->createShiftFreeForm($shift);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // check if beneficiary can free this shift
            $shift_can_be_freed = $this->get('shift_service')->canFreeShift($current_user->getBeneficiary(), $shift);
            if (!$shift_can_be_freed['result']) {
                $session->getFlashBag()->add("error", $shift_can_be_freed['message'] || "Impossible d'annuler ce créneau.");
                return $this->redirectToRoute("homepage");
            }

            // store shift beneficiary & reason (before shift free())
            $beneficiary = $shift->getShifter();
            $fixe = $shift->isFixe();
            $reason = $form->get("reason")->getData();

            // free shift
            $shift->free($reason);

            $em->persist($shift);
            $em->flush();

            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(ShiftFreedEvent::NAME, new ShiftFreedEvent($shift, $beneficiary, $fixe, $reason));
        } else {
            return $this->redirectToRoute('homepage');
        }

        $session->getFlashBag()->add('success', "Le créneau a été annulé !");
        if ($this->use_time_log_saving) {
            $session->getFlashBag()->add("warning", "Grâce au compteur épargne, votre créneau a été comptabilisé.<br />En échange, votre compteur épargne a été décrémenté de la durée du créneau.");
        }
        return $this->redirectToRoute('homepage');
    }

    /**
     * Free a shift (admin side).
     *
     * @Route("/{id}/free_admin", name="shift_free_admin", methods={"POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function freeShiftAdminAction(Request $request, Shift $shift)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $this->denyAccessUnlessGranted(ShiftVoter::FREE, $shift);

        $form = $this->createShiftFreeAdminForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $shifter_is_current_user = $current_user->getBeneficiary() == $shift->getShifter();
            $shift_can_be_freed = $this->get('shift_service')->canFreeShift($shift->getShifter(), $shift, true);
            // check if user is allowed to free shift
            if ($shifter_is_current_user && $this->forbid_own_shift_free_admin && !$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $success = false;
                $message = "Vous ne pouvez pas annuler votre propre créneau.";
            }
            // check if shift can be freed
            elseif (!$shift_can_be_freed['result']) {
                $success = false;
                $message = $shift_can_be_freed['message'] || "Impossible d'annuler ce créneau.";
            }
            else {
                // store shift beneficiary & reason (before shift free())
                $beneficiary = $shift->getShifter();
                $fixe = $shift->isFixe();
                $reason = $form->get("reason")->getData();

                // shouldn't happen: in the UI, you need to first invalidate a shift before being able to free it
                $wasCarriedOut = $shift->getWasCarriedOut() == 1;
                if ($wasCarriedOut) {
                    $shift->invalidateShiftParticipation();
                }

                // free shift
                $shift->free($reason);

                $em->persist($shift);
                $em->flush();

                $dispatcher = $this->get('event_dispatcher');
                if ($wasCarriedOut) {
                    $dispatcher->dispatch(ShiftInvalidatedEvent::NAME, new ShiftInvalidatedEvent($shift, $beneficiary));
                }
                $dispatcher->dispatch(ShiftFreedEvent::NAME, new ShiftFreedEvent($shift, $beneficiary, $fixe, $reason));

                $success = true;
                $message = "Le créneau a bien été libéré !";
            }
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de libérer le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $bucket = $this->get('shift_service')->getShiftBucketFromShift($shift);
                $card =  $this->get('twig')->render('admin/booking/_partial/bucket_card.html.twig', array(
                    'bucket' => $bucket,
                    'start' => 6,
                    'end' => 22,
                    'line' => 0,
                ));
                $modal = $this->forward('AppBundle\Controller\BookingController::showBucketAction', [
                    'bucket' => $bucket->getShiftWithMinId()
                ])->getContent();
                return new JsonResponse(array('message' => $message, 'card' => $card, 'modal' => $modal), 200);
            } else {
                return new JsonResponse(array('message' => $message), 400);
            }
        } else {
            $session->getFlashBag()->add($success ? 'success' : 'error', $message);
            $referer = $request->headers->get('referer');
            return new RedirectResponse($referer);
        }
    }

    /**
     * validate / invalidate a shift.
     *
     * @Route("/{id}/validate_admin", name="shift_validate_admin", methods={"POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function validateShiftAction(Request $request, Shift $shift)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $this->denyAccessUnlessGranted(ShiftVoter::VALIDATE, $shift);

        $form = $this->createShiftValidateInvalidateAdminForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $validate = $form->get('validate')->getData() == 1;
            $current = $shift->getWasCarriedOut() == 1;
            $shifter_is_current_user = $current_user->getBeneficiary() == $shift->getShifter();
            // check if user is allowed to (in)validate shift
            if ($shifter_is_current_user && $this->forbid_own_shift_validate_admin && !$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $success = false;
                $message = "Vous ne pouvez pas " . ($validate ? "valider" : "invalider") . " votre propre créneau.";
            }
            // check mismatch between $validate & $current
            elseif ($validate == $current) {
                $success = false;
                $message = "La participation au créneau a déjà été " . ($validate ? "validée" : "invalidée");
            }
            else {
                if ($validate) {
                    $shift->validateShiftParticipation();
                } else {
                    $shift->invalidateShiftParticipation();
                }

                $em->persist($shift);
                $em->flush();

                $dispatcher = $this->get('event_dispatcher');
                if ($validate) {
                    $dispatcher->dispatch(ShiftValidatedEvent::NAME, new ShiftValidatedEvent($shift));
                } else {
                    $beneficiary = $shift->getShifter();
                    $dispatcher->dispatch(ShiftInvalidatedEvent::NAME, new ShiftInvalidatedEvent($shift, $beneficiary));
                }

                $message = "La participation au créneau a bien été " . ($validate ? "validée" : "invalidée") . " !";
                $success = true;
            }
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de valider/invalider le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $bucket = $this->get('shift_service')->getShiftBucketFromShift($shift);
                $card =  $this->get('twig')->render('admin/booking/_partial/bucket_card.html.twig', array(
                    'bucket' => $bucket,
                    'start' => 6,
                    'end' => 22,
                    'line' => 0,
                ));
                $modal = $this->forward('AppBundle\Controller\BookingController::showBucketAction', [
                    'bucket' => $bucket->getShiftWithMinId()
                ])->getContent();
                return new JsonResponse(array('message' => $message, 'card' => $card, 'modal' => $modal), 200);
            } else {
                return new JsonResponse(array('message' => $message), 400);
            }
        } else {
            $session->getFlashBag()->add($success ? 'success' : 'error', $message);
            $referer = $request->headers->get('referer');
            return new RedirectResponse($referer);
        }
    }

    /**
     * Accept a reserved shift
     *
     * @Route("/{id}/accept", name="shift_accept_reserved", methods={"GET"})
     */
    public function acceptReservedShiftAction(Request $request, Shift $shift)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        if (!$shift->getId()) {
            $session->getFlashBag()->add('error', "Créneau pas trouvé");
            return $this->redirectToRoute("homepage");
        }
        if (!$this->isGranted('accept', $shift)) {
            $session->getFlashBag()->add("error", "Impossible d'accepter la réservation");
            return $this->redirectToRoute("homepage");
        }
        if (!$shift->getLastShifter()) {
            $session->getFlashBag()->add('error', "Oups, ce créneau a déjà été confirmé / refusé, ou le délai de reservation est écoulé.");
            return $this->redirectToRoute("homepage");
        }

        $shift->setBooker($current_user);
        $beneficiary = $shift->getLastShifter();
        $shift->setShifter($beneficiary);
        $shift->setBookedTime(new DateTime('now'));
        $shift->setLastShifter(null);
        $shift->setFixe(false);

        $em->persist($shift);
        $em->flush();

        $dispatcher = $this->get('event_dispatcher');
        $dispatcher->dispatch(ShiftBookedEvent::NAME, new ShiftBookedEvent($shift, false));

        $session->getFlashBag()->add('success', "Créneau réservé ! Merci " . $shift->getShifter()->getFirstname());
        return $this->redirectToRoute('homepage');
    }

    /**
     * Reject a reserved shift
     *
     * @Route("/{id}/reject", name="shift_reject_reserved", methods={"GET"})
     */
    public function rejectReservedShiftAction(Request $request, Shift $shift)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        if (!$shift->getId()) {
            $session->getFlashBag()->add('error', "Créneau pas trouvé");
            return $this->redirectToRoute("homepage");
        }
        if (!$this->isGranted('reject', $shift)) {
            $session->getFlashBag()->add("error", "Impossible de rejeter la réservation");
            return $this->redirectToRoute("homepage");
        }
        if (!$shift->getLastShifter()) {
            $session->getFlashBag()->add('error', "Oups, ce créneau a déjà été confirmé / refusé, ou le délai de reservation est écoulé.");
            return $this->redirectToRoute("homepage");
        }

        $shift->setLastShifter(null);
        $shift->setFixe(false);

        $em->persist($shift);
        $em->flush();

        $session->getFlashBag()->add('success', "Créneau libéré !");
        $session->getFlashBag()->add('warning', "Pense à revenir dans quelques jours choisir un autre créneau pour ton bénévolat :)");
        return $this->redirectToRoute('homepage');
    }

    /**
     * delete a shift.
     *
     * @Route("/{id}", name="shift_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deleteShiftAction(Request $request, Shift $shift)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createShiftDeleteForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $beneficiary = $shift->getShifter();
            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(ShiftDeletedEvent::NAME, new ShiftDeletedEvent($shift, $beneficiary));
            $em->remove($shift);
            $em->flush();

            $success = true;
            $message = 'Le créneau a bien été supprimé !';
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de supprimer le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $bucket = $this->get('shift_service')->getShiftBucketFromShift($shift);
                if (count($bucket->getShifts()) > 0) {
                    $card =  $this->get('twig')->render('admin/booking/_partial/bucket_card.html.twig', array(
                        'bucket' => $bucket,
                        'start' => 6,
                        'end' => 22,
                        'line' => 0,
                    ));
                    $modal = $this->forward('AppBundle\Controller\BookingController::showBucketAction', [
                        'bucket' => $bucket->getShiftWithMinId()
                    ])->getContent();
                } else {
                    $card = null;
                    $modal = null;
                }
                return new JsonResponse(array('message'=>$message, 'card' => $card, 'modal' => $modal), 200);
            } else {
                return new JsonResponse(array('message'=>$message), 400);
            }
        } else {
            $session->getFlashBag()->add($success ? 'success' : 'error', $message);
            return $this->redirectToRoute('booking_admin');
        }
    }

    /**
     * @Route("/{id}/contact_form", name="shift_contact_form", methods={"GET","POST"})
     */
    public function contactFormAction(Request $request, Shift $shift, \Swift_Mailer $mailer)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $coShifters = $em->getRepository('AppBundle:Beneficiary')->findCoShifters($shift);
        $form = $this->createShiftContactForm($shift, $coShifters);

        if ($form->handleRequest($request)->isValid()) {
            $beneficiaries = $form->get('to')->getData();
            $from = $form->get('from')->getData();
            $from = $em->getRepository('AppBundle:Beneficiary')->findOneBy(array('id' => $from));
            $emails = array();
            $firstnames = array();
            foreach ($beneficiaries as $beneficiary) {
                $emails[] = $beneficiary->getEmail();
                $firstnames[] = $beneficiary->getFirstname();
            }
            $message = (new \Swift_Message('[ESPACE MEMBRES] Un message de ' . $from->getFirstName() . " " . substr($from->getLastName(),0,1)))
                ->setFrom($this->getParameter('transactional_mailer_user'))
                ->setReplyTo($from->getEmail())
                ->setBcc($emails)
                ->setBody(
                    $this->renderView(
                        'emails/coshifter_message.html.twig',
                        array(
                            'message' => trim($form->get('message')->getData()),
                            'from' => $from,
                            'firstnames' => $firstnames,
                            'shift' => $shift
                        )
                    ),
                    'text/html'
                );
            $mailer->send($message);

            if (count($firstnames) > 1) {
                $last_firstname = array_pop($firstnames);
                $firstnames = implode(', ', $firstnames);
                $firstnames .= ' et ' . $last_firstname;
            } else {
                $firstnames = $firstnames[0];
            }

            $session->getFlashBag()->add('success', 'Ton message a été transmis à ' . $firstnames);
            return $this->redirectToRoute('homepage');
        }

        return $this->render('booking/_partial/home_shift_contactform.html.twig', array(
            'shift' => $shift,
            'form' => $form->createView()
        ));
    }

    /**
     * Widget display
     * 
     * @Route("/widget", name="shift_widget", methods={"GET"})
     */
    public function widgetAction(Request $request)
    {
        $buckets = array();
        $job = null;

        $job_id = $request->get('job_id');
        $display_end = $request->query->has('display_end') ? ($request->get('display_end') == 1) : false;
        $display_on_empty = $request->query->has('display_on_empty') ? ($request->get('display_on_empty') == 1) : false;
        $title = $request->query->has('title') ? ($request->get('title') == 1) : true;

        if ($job_id) {
            $em = $this->getDoctrine()->getManager();
            $job = $em->getRepository('AppBundle:Job')->find($job_id);
            if ($job) {
                $shifts = $em->getRepository('AppBundle:Shift')->findFutures(null, $job);
                foreach ($shifts as $shift) {
                    $day = $shift->getStart()->format("d m Y");
                    $interval = $shift->getIntervalCode();
                    if (!isset($buckets[$interval . $day])) {
                        $buckets[$interval . $day] = new ShiftBucket();
                    }
                    $buckets[$interval . $day]->addShift($shift);
                }
            }
        }

        return $this->render('admin/shift/widget/widget.html.twig', [
            'job' => $job,
            'buckets' => $buckets,
            'display_end' => $display_end,
            'display_on_empty' => $display_on_empty,
            'title' => $title
        ]);
    }

    /**
     * Creates a form to book a shift entity.
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftBookAdminForm(Shift $shift)
    {
        $form = $this->get('form.factory')->createNamedBuilder('shift_book_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_book_admin', array('id' => $shift->getId())))
            ->add('shifter', AutocompleteBeneficiaryType::class, array('label' => 'Numéro d\'adhérent ou nom du membre', 'required' => true));

        if ($this->use_fly_and_fixed) {
            $form = $form->add('fixe', RadioChoiceType::class, [
                'choices'  => [
                    'Volant' => 0,
                    'Fixe' => 1,
                ],
                'data' => 0
            ]);
        } else {
            $form = $form->add('fixe', HiddenType::class, [
                'data' => 0
            ]);
        }

        return $form->getForm();
    }

    /**
     * Creates a form to delete a shift entity.
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftDeleteForm(Shift $shift)
    {
        return $this->get('form.factory')->createNamedBuilder('shift_delete_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_delete', array('id' => $shift->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Creates a form to free a shift entity.
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftFreeForm(Shift $shift)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('shift_free', array('id' => $shift->getId())))
            ->add('reason', TextareaType::class, array('required' => false))
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Creates a form to free a shift entity (admin side).
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftFreeAdminForm(Shift $shift)
    {
        return $this->get('form.factory')->createNamedBuilder('shift_free_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_free_admin', array('id' => $shift->getId())))
            ->add('reason', TextareaType::class, array('required' => false, 'label' => 'Justification éventuelle', 'attr' => array('class' => 'materialize-textarea')))
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Creates a form to validate / invalidate a shift entity.
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftValidateInvalidateAdminForm(Shift $shift)
    {
        return $this->get('form.factory')->createNamedBuilder('shift_validate_invalidate_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_validate_admin', array('id' => $shift->getId())))
            ->add('validate', HiddenType::class, [
                'data'  => ($shift->getWasCarriedOut() ? 0 : 1),
            ])
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Create a form to contact co shifters.
     * 
     * @param Shift $shift The shift entity
     * @param $coShifters
     * 
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftContactForm(Shift $shift, $coShifters = null)
    {
        return $this->get('form.factory')->createNamedBuilder('shift_contact_form_' . $shift->getId())
            ->add('from', HiddenType::class, array('data' => $shift->getShifter()->getId()))
            ->add('to', AutocompleteBeneficiaryCollectionType::class, [
                'label' => 'A',
                'data' => $coShifters
            ])
            ->add('message', TextareaType::class, [
                'attr' => ['class' => 'materialize-textarea'],
                'label' => 'Message',
                'data' => 'Bonjour XX,'.PHP_EOL."Tu n'es toujours pas arrivé pour notre créneau.".PHP_EOL."Est-ce que tout va bien ?".PHP_EOL."A très vite,".PHP_EOL.$shift->getShifter()->getFirstName().PHP_EOL.PHP_EOL."Bonjour à tou.te.s,".PHP_EOL."Je vais en être en retard pour mon créneau.".PHP_EOL."Je serai à l'épicerie d'ici XX minutes.".PHP_EOL."A tout de suite,".PHP_EOL.$shift->getShifter()->getFirstName()
            ])
            ->setAction($this->generateUrl('shift_contact_form', array('id' => $shift->getId())))
            ->setMethod('POST')
            ->getForm();
    }
}
