<?php

namespace AppBundle\Controller;

use DateTime;
use AppBundle\Entity\Job;
use AppBundle\Entity\Shift;
use AppBundle\Event\ShiftBookedEvent;
use AppBundle\Event\ShiftFreedEvent;
use AppBundle\Event\ShiftValidatedEvent;
use AppBundle\Event\ShiftInvalidatedEvent;
use AppBundle\Form\AutocompleteBeneficiaryType;
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
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;


/**
 * @Route("shift")
 */
class ShiftController extends Controller
{
    /**
     * @var boolean
     */
    private $useFlyAndFixed;

    public function __construct(bool $useFlyAndFixed)
    {
        $this->useFlyAndFixed = $useFlyAndFixed;
    }

    /**
     * @Route("/new", name="shift_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();

        $em = $this->getDoctrine()->getManager();
        $job = $em->getRepository(Job::class)->findOneBy(array());

        if (!$job) {
            $session->getFlashBag()->add('warning', 'Commençons par créer un poste de bénévolat');
            return $this->redirectToRoute('job_new');
        }

        $shift = new Shift();
        $form = $this->get('form.factory')->createNamed('bucket_add_form', ShiftType::class, $shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $number = $form->get('number')->getData();
            while (1 < $number) {
                $s = clone($shift);
                $em->persist($s);
                $number --;
            }
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

        $content = json_decode($request->getContent());
        $beneficiaryId = $content->beneficiaryId;
        $isFixe = $content->typeService;

        $em = $this->getDoctrine()->getManager();
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
            $current_user = $this->get('security.token_storage')->getToken()->getUser();
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
        $form = $this->createShiftBookForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fixe = $form->get("fixe")->getData();
            $beneficiary = $form->get("shifter")->getData();

            if ($shift->getShifter()) {
                $message = "Désolé, ce créneau est déjà réservé";
                $success = false;
            } elseif ($shift->getFormation() && !$beneficiary->getFormations()->contains($shift->getFormation())) {
                $message = "Désolé, ce bénévole n'a pas la qualification necessaire (" . $shift->getFormation()->getName() . ")";
                $success = false;
            } elseif ($beneficiary->getMembership()->isCurrentlyExemptedFromShifts($shift->getStart())) {
                $message = "Désolé, ce bénévole est exempté de créneau sur cette période";
                $success = false;
            } else {
                $current_user = $this->get('security.token_storage')->getToken()->getUser();
                $shift->setBooker($current_user);
                $shift->setBookedTime(new DateTime('now'));
                $shift->setShifter($beneficiary);
                $shift->setLastShifter(null);
                $shift->setFixe($fixe);

                $em = $this->getDoctrine()->getManager();
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
            $message = "Une erreur s'est produite... Impossible de réserver le créneau. " . (string) $form->getErrors(true, false);
            $success = false;
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
                return new JsonResponse(array('message'=>$message, 'card' => $card, 'modal' => $modal), 200);
            } else {
                return new JsonResponse(array('message'=>$message), 400);
            }
        } else {
            $session = new Session();
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

        $this->denyAccessUnlessGranted(ShiftVoter::FREE, $shift);

        $form = $this->createShiftFreeForm($shift);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if($shift->isFixe()) {
                $session->getFlashBag()->add("error", "Impossible d'annuler un créneau fixe !");
                return $this->redirectToRoute("homepage");
            }
            if (!$shift->getShifter()) {
                $session->getFlashBag()->add("error", "Impossible de libérer le créneau car il n'est actuellement pas réservé.");
                return $this->redirectToRoute("homepage");
            }
            // store shift beneficiary & reason
            $beneficiary = $shift->getShifter();
            $reason = $form->get("reason")->getData();

            // free shift
            $shift->free($reason);

            $em = $this->getDoctrine()->getManager();
            $em->persist($shift);
            $em->flush();

            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(ShiftFreedEvent::NAME, new ShiftFreedEvent($shift, $beneficiary, $reason));
        } else {
            return $this->redirectToRoute('homepage');
        }

        $session->getFlashBag()->add('success', "Le créneau a été annulé !");
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
        $this->denyAccessUnlessGranted(ShiftVoter::FREE, $shift);

        $form = $this->createShiftFreeAdminForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$shift->getShifter()) {
                $success = false;
                $message = "Impossible de libérer le créneau car il n'est actuellement pas réservé.";
            } else {
                // store shift beneficiary & reason
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

                $em = $this->getDoctrine()->getManager();
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
                return new JsonResponse(array('message'=>$message, 'card' => $card, 'modal' => $modal), 200);
            } else {
                return new JsonResponse(array('message'=>$message), 400);
            }
        } else {
            $session = new Session();
            $session->getFlashBag()->add($success ? 'success' : 'error', $message);
            $referer = $request->headers->get('referer');
            return new RedirectResponse($referer);
        }
    }

    /**
     * validate / invalidate a shift.
     *
     * @Route("/{id}/validate", name="shift_validate", methods={"POST"})
     */
    public function validateShiftAction(Request $request, Shift $shift)
    {
        $this->denyAccessUnlessGranted(ShiftVoter::VALIDATE, $shift);

        $form = $this->createShiftValidateInvalidateForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $validate = $form->get('validate')->getData() == 1;
            $current = $shift->getWasCarriedOut() == 1;
            if ($validate == $current) {
                $success = false;
                $message = "La participation au créneau a déjà été " . ($validate ? "validée" : "invalidée");
            } else {
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
                return new JsonResponse(array('message'=>$message, 'card' => $card, 'modal' => $modal), 200);
            } else {
                return new JsonResponse(array('message'=>$message), 400);
            }
        } else {
            $session = new Session();
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

        if (!$shift->getId() || !$this->isGranted('accept', $shift)) {
            $session->getFlashBag()->add("error", "Impossible d'accepter la réservation");
            return $this->redirectToRoute("homepage");
        }

        if ($shift->getId()) {
            if ($shift->getLastShifter()) {
                $current_user = $this->get('security.token_storage')->getToken()->getUser();
                $shift->setBooker($current_user);
                $beneficiary = $shift->getLastShifter();
                $shift->setShifter($beneficiary);
                $shift->setBookedTime(new DateTime('now'));
                $shift->setLastShifter(null);
                $shift->setFixe(false);
                $em = $this->getDoctrine()->getManager();
                $em->persist($shift);
                $em->flush();

                $dispatcher = $this->get('event_dispatcher');
                $dispatcher->dispatch(ShiftBookedEvent::NAME, new ShiftBookedEvent($shift, false));

                $session->getFlashBag()->add('success', "Créneau réservé ! Merci " . $shift->getShifter()->getFirstname());
            } else {
                $session->getFlashBag()->add('error', "Oups, ce créneau a déjà été confirmé / refusé ou le délais de reservation est écoulé.");
            }
        } else {
            $session->getFlashBag()->add('error', "Créneau pas trouvé");
        }

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

        if (!$this->isGranted('reject', $shift)) {
            $session->getFlashBag()->add("error", "Impossible de rejeter la réservation");
            return $this->redirectToRoute("homepage");
        }

        if ($shift->getId()) {
            if ($shift->getLastShifter()) {
                $shift->setLastShifter(null);
                $shift->setFixe(false);
                $em = $this->getDoctrine()->getManager();
                $em->persist($shift);
                $em->flush();
                $session->getFlashBag()->add('success', "Créneau libéré");
                $session->getFlashBag()->add('warning', "Pense à revenir dans quelques jours choisir un autre créneau pour ton bénévolat");
            } else {
                $session->getFlashBag()->add('error', "Oups, ce créneau a déjà été confirmé / refusé ou le délais de reservation est écoulé.");
            }
        } else {
            $session->getFlashBag()->add('error', "Créneau pas trouvé");
        }

        return $this->redirectToRoute('homepage');
    }

    /**
     * delete a shift.
     *
     * @Route("/{id}", name="shift_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function removeShiftAction(Request $request, Shift $shift)
    {
        $form = $this->createShiftDeleteForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
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
            $session = new Session();
            $session->getFlashBag()->add($success ? 'success' : 'error', $message);
            return $this->redirectToRoute('booking_admin');
        }
    }

    /**
     * Creates a form to book a shift entity.
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftBookForm(Shift $shift)
    {
        $form = $this->get('form.factory')->createNamedBuilder('shift_book_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_book_admin', array('id' => $shift->getId())))
            ->add('shifter', AutocompleteBeneficiaryType::class, array('label' => 'Numéro d\'adhérent ou nom du membre', 'required' => true));

        if ($this->useFlyAndFixed) {
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
        $form = $this->get('form.factory')->createNamedBuilder('shift_delete_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_delete', array('id' => $shift->getId())))
            ->setMethod('DELETE');

        return $form->getForm();
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
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('shift_free', array('id' => $shift->getId())))
            ->add('reason', TextareaType::class, array('required' => false))
            ->setMethod('POST');

        return $form->getForm();
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
        $form = $this->get('form.factory')->createNamedBuilder('shift_free_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_free_admin', array('id' => $shift->getId())))
            ->add('reason', TextareaType::class, array('required' => false, 'label' => 'Justification éventuelle', 'attr' => array('class' => 'materialize-textarea')))
            ->setMethod('POST');

        return $form->getForm();
    }

    /**
     * Creates a form to validate / invalidate a shift entity.
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftValidateInvalidateForm(Shift $shift)
    {
        $form = $this->get('form.factory')->createNamedBuilder('shift_validate_invalidate_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_validate', array('id' => $shift->getId())))
            ->add('validate', HiddenType::class, [
                'data'  => ($shift->getWasCarriedOut() ? 0 : 1),
            ])
            ->setMethod('POST');

        return $form->getForm();
    }
}
