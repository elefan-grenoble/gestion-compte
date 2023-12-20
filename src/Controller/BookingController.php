<?php

namespace App\Controller;

use DateTime;
use Exception;
use App\Entity\Beneficiary;
use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\ShiftBucket;
use App\Event\ShiftDeletedEvent;
use App\Form\AutocompleteBeneficiaryType;
use App\Form\RadioChoiceType;
use App\Form\ShiftType;
use App\Repository\JobRepository;
use App\Security\ShiftVoter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Booking controller.
 *
 * @Route("booking")
 */
class BookingController extends Controller
{
    /**
     * @var boolean
     */
    private $use_fly_and_fixed;
    private $display_name_shifters;

    public function __construct(bool $use_fly_and_fixed, bool $display_name_shifters)
    {
        $this->use_fly_and_fixed = $use_fly_and_fixed;
        $this->display_name_shifters = $display_name_shifters;
    }

    /**
     * @return Response
     */
    public function homepageDashboardAction(): Response
    {
        return $this->render('booking/home_dashboard.html.twig');
    }

    /**
     * @return Response
     */
    public function homepageShiftsAction(): Response
    {
        $em = $this->getDoctrine()->getManager();

        $member = $this->getUser()->getBeneficiary()->getMembership();

        $period_positions = $this->get('membership_service')->getPeriodPositions($member);
        $preceding_previous_cycle_start = $this->get('membership_service')->getStartOfCycle($member, -1 * $this->getParameter('max_nb_of_past_cycles_to_display'));
        $next_cycle_end = $this->get('membership_service')->getEndOfCycle($member, 1);
        $shifts_by_cycle = $em->getRepository('App:Shift')->findShiftsByCycles($member, $preceding_previous_cycle_start, $next_cycle_end);

        $shiftFreeForms = [];
        foreach ($shifts_by_cycle as $key => $shifts) {
            foreach ($shifts as $shift) {
                $shiftFreeForms[$shift->getId()] = $this->createShiftFreeForm($shift)->createView();
            }
        }

        return $this->render('booking/home_booked_shifts.html.twig', array(
            'period_positions' => $period_positions,
            'shifts_by_cycle' => $shifts_by_cycle,
            'shift_free_forms' => $shiftFreeForms,
        ));
    }

    /**
     * @Route("/", name="booking", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function indexAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $mode = null;
        if ($this->getUser()->getBeneficiary() == null) {
            $session->getFlashBag()->add('error', 'Oups, tu n\'as pas de bénéficiaire enregistré ! MODE ADMIN');
            return $this->redirectToRoute('booking_admin');
        } else {
            if (!$this->get('membership_service')->isUptodate($this->getUser()->getBeneficiary()->getMembership())) {
+               $remainder = $this->get('membership_service')->getRemainder($this->getUser()->getBeneficiary()->getMembership());
                $session->getFlashBag()->add('warning', 'Oups, ton adhésion a expiré il y a ' . $remainder->format('%a jours') . '... n\'oublie pas de ré-adhérer pour effectuer ton bénévolat !');
                return $this->redirectToRoute('homepage');
            }
            if ($this->getUser()->getBeneficiary()->getMembership()->getFrozen()){
                $session->getFlashBag()->add('warning', 'Oups, ton compte est gelé ❄️ !<br />Dégel pour réserver 😉');
                return $this->redirectToRoute('homepage');
            }
        }

        $beneficiaries = $this->getUser()->getBeneficiary()->getMembership()->getBeneficiaries();

        $beneficiaryForm = $this->createFormBuilder()
            ->setAction($this->generateUrl('booking'))
            ->setMethod('POST')
            ->add('beneficiary', EntityType::class, array(
                'label' => 'Réserver un créneau pour',
                'required' => true,
                'class' => 'App:Beneficiary',
                'choices' => $beneficiaries,
                'choice_label' => 'firstname',
                'multiple' => false,
            ))
            ->getForm();

        $beneficiaryForm->handleRequest($request);

        //beneficiary selected, or only one beneficiary
        if ($beneficiaryForm->isSubmitted() || $beneficiaries->count() == 1) {
            if ($beneficiaries->count() > 1) {
                $beneficiary = $beneficiaryForm->get('beneficiary')->getData();
            } else {
                $beneficiary = $beneficiaries->first();
            }

            $shifts = $em->getRepository('App:Shift')->findFutures(null, null, $this->display_name_shifters);
            $bucketsByDay = $this->get('shift_service')->generateShiftBucketsByDayAndJob($shifts);

            $hours = array();
            for ($i = 6; $i < 22; $i++) { //todo put this in conf
                $hours[] = $i;
            }

            return $this->render('booking/index.html.twig', [
                'bucketsByDay' => $bucketsByDay,
                'hours' => $hours,
                'beneficiary' => $beneficiary,
                'jobs' => $em->getRepository(Job::class)->findByEnabled(true)
            ]);

        } else { // no beneficiary selected
            return $this->render('booking/index.html.twig', [
                'beneficiary_form' => $beneficiaryForm->createView(),
            ]);
        }

    }

    /**
     * Build the filter form for the admin main page (route /booking/admin)
     * and rerun an array with the form object and the date range and the action
     *
     * the return object :
     * array(
     *      "form":FormBuilderInterface
     *      "from" => DateTime,
     *      "to" => DateTime,
     *      "job" => Job|null,
     *      "filling" => str|null,
     *      )
     */
    private function adminFilterFormFactory($em, Request $request): array
    {
        // default values
        $defaultFrom = new DateTime();
        $defaultFrom->setTimestamp(strtotime('last monday', strtotime('tomorrow')));
        $defaultTo = null;
        $defaultWeek = (new DateTime())->format('W');
        $defaultYear = (new DateTime())->format('Y');
        $years = $em->getRepository(Shift::class)->getYears();

        $filterForm = $this->createFormBuilder()
            ->setAction($this->generateUrl('booking_admin'))
            ->add('type', ChoiceType::class, array(
                'label' => 'Type de filtre',
                'required' => true,
                'data' => "Date",
                'choices' => array(
                    'Date' => "date",
                    'Semaine' => "week",
                ),
            ))
            ->add('from', TextType::class, array(
                'label' => 'A partir de',
                'required' => true,
                'data' => $defaultFrom->format('Y-m-d'),
                'attr' => array('class' => 'datepicker'),
            ))
            ->add('to', TextType::class, array(
                'label' => 'Jusqu\'à',
                'required' => false,
                'attr' => array('class' => 'datepicker'),
            ))
            ->add('year', ChoiceType::class, array(
                'required' => false,
                'choices' => array_combine($years, $years),
                'label' => 'Année',
                'data' => $defaultYear,
                'placeholder' => false,
            ))
            ->add('week', IntegerType::class, array(
                'required' => false,
                'label' => 'Numéro de semaine',
                'scale' => 0,
                'data' => $defaultWeek,
                'attr' => [
                    'min' => 1,
                    'max' => 52,
                ],
            ))
            ->add('job', EntityType::class, array(
                'label' => 'Type de créneau',
                'class' => 'App:Job',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
                'query_builder' => function(JobRepository $repository) {
                    $qb = $repository->createQueryBuilder('j');
                    return $qb
                        ->where($qb->expr()->eq('j.enabled', '?1'))
                        ->setParameter('1', '1')
                        ->orderBy('j.name', 'ASC');
                }
            ))
            ->add('filling', ChoiceType::class, array(
                'label' => 'Remplissage',
                'required' => false,
                'choices' => array(
                    'Complet' => 'full',
                    'Partiel' => 'partial',
                    'Vide' => 'empty',
                ),
            ))
            ->add('filter', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'filtrer')
            ))
            ->getForm();

        $filterForm->handleRequest($request);
        $from = $defaultFrom;
        $to = $defaultTo;
        $job = null;
        $filling = null;

        try {
            if ($filterForm->isSubmitted() && $filterForm->isValid()) {
                $job = $filterForm->get("job")->getData();
                $filling = $filterForm->get("filling")->getData();

                // selection mode based on dates
                if ($filterForm->get("type")->getData() == "date") {
                    $from = new DateTime($filterForm->get('from')->getData());
                    $to = $filterForm->get('to')->getData();
                    if ($to) {
                        $to = new DateTime($to);
                    }
                // selection mode based on week number
                } else {
                    $week = $filterForm->get("week")->getData();
                    $year = $filterForm->get("year")->getData();
                    $from = new DateTime();
                    $from->setISODate($year, $week, 1);
                    $from->setTime(0,0);
                    $to = clone $from;
                    $to->modify('+6 days');
                }
            }
        } catch (Exception $ex) {
            $from = $defaultFrom;
            $to = $defaultTo;
            $job = null;
        }

        return array(
            "form" => $filterForm,
            "from" => $from,
            "to" => $to,
            "job" => $job,
            "filling" => $filling,
        );
    }

    /**
     * main administration page for booking shift
     *
     * @Route("/admin", name="booking_admin", methods={"GET","POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function adminAction(Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();
        $filter = $this->adminFilterFormFactory($em, $request);

        $shifts = $em->getRepository(Shift::class)->findFrom($filter["from"], $filter["to"], $filter["job"], true);
        $bucketsByDay = $this->get('shift_service')->generateShiftBucketsByDayAndJob($shifts);
        $bucketsByDay = $this->get('shift_service')->filterBucketsByDayAndJobByFilling($bucketsByDay, $filter["filling"]);

        return $this->render('admin/booking/index.html.twig', [
            'filterForm' => $filter["form"]->createView(),
            'bucketsByDay' => $bucketsByDay,
        ]);
    }

    /**
     * @Route("/bucket/{id}/show", name="bucket_show", methods={"GET"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function showBucketAction(Request $request, Shift $bucket)
    {
        $em = $this->getDoctrine()->getManager();
        $shifts = $em->getRepository('App:Shift')->findBucket($bucket);

        $shiftBookForms = [];
        $shiftDeleteForms = [];
        $shiftFreeForms = [];
        $shiftValidateInvalidateForms = [];
        foreach ($shifts as $shift) {
            $shiftBookForms[$shift->getId()] = $this->createShiftBookAdminForm($shift)->createView();
            $shiftDeleteForms[$shift->getId()] = $this->createShiftDeleteForm($shift)->createView();
            $shiftFreeForms[$shift->getId()] = $this->createShiftFreeAdminForm($shift)->createView();
            $shiftValidateInvalidateForms[$shift->getId()] = $this->createShiftValidateInvalidateAdminForm($shift)->createView();
        }
        $bucketShiftAddForm = $this->createBucketShiftAddForm($bucket);
        $bucketDeleteform = $this->createBucketDeleteForm($bucket);
        $bucketLockUnlockForm = $this->createBucketLockUnlockForm($bucket);

        return $this->render('admin/booking/_partial/bucket_modal.html.twig', [
            'shifts' => $shifts,
            'bucket_shift_add_form' => $bucketShiftAddForm->createView(),
            'shift_book_forms' => $shiftBookForms,
            'shift_delete_forms' => $shiftDeleteForms,
            'shift_free_forms' => $shiftFreeForms,
            'shift_validate_invalidate_forms' => $shiftValidateInvalidateForms,
            'bucket_delete_form' => $bucketDeleteform->createView(),
            'bucket_lock_unlock_form' => $bucketLockUnlockForm->createView(),
        ]);
    }

    /**
     * When the user click on the 'edit' button on the bucket popup.
     *
     * @Route("/bucket/{id}/edit", name="bucket_edit", methods={"GET", "POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function editBucketAction(Request $request,Shift $shift)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(ShiftType::class, $shift);
        // Keep a record of the shift before update
        $bucket = clone($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $shifts = $em->getRepository('App:Shift')->findBy([
                'job' => $bucket->getJob(),
                'start' => $bucket->getStart(),
                'end' => $bucket->getEnd()
            ]);
            foreach ($shifts as $s) {
                $s->setStart($form->get('start')->getData());
                $s->setEnd($form->get('end')->getData());
                $s->setJob($form->get('job')->getData());
                $em->persist($s);
            }
            $em->flush();

            $session->getFlashBag()->add('success', 'Le créneau a bien été édité !');
            return $this->redirectToRoute('booking_admin');
        }

        return $this->render('admin/shift/edit.html.twig', array(
            "form" => $form->createView(),
            "shift" => $shift
        ));
    }

    /**
     * lock a bucket, used when the user click on the 'verouiller' button
     * on the bucket popup.
     *
     * @Route("/bucket/{id}/lock", name="bucket_lock_unlock", methods={"POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function lockUnlockBucketAction(Request $request, Shift $shift)
    {
        $this->denyAccessUnlessGranted(ShiftVoter::LOCK, $shift);

        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createBucketLockUnlockForm($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lock = $form->get('lock')->getData() == 1;
            $bucket = $this->get('shift_service')->getShiftBucketFromShift($shift);
            $current = $bucket->getFirst()->isLocked() == 1;
            if ($lock == $current) {
                $success = false;
                $message = "Le créneau a déjà été " . ($lock ? "verrouillé" : "déverrouillé");
            } else {
                foreach ($bucket->getShifts() as $s) {
                    $s->setLocked($lock);
                }
                $em->flush();
                $message = "Le créneau a été " . ($lock ? "verrouillé" : "déverrouillé");
                $success = true;
            }
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de verrouiller/déverouiller le créneau. " . (string) $form->getErrors(true, false);
        }

        if ($request->isXmlHttpRequest()) {
            if ($success) {
                $card =  $this->get('twig')->render('admin/booking/_partial/bucket_card.html.twig', array(
                    'bucket' => $bucket,
                    'start' => 6,
                    'end' => 22,
                    'line' => 0,
                ));
                $modal = $this->forward('App\Controller\BookingController::showBucketAction', [
                    'bucket' => $bucket->getShiftWithMinId()
                ])->getContent();
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
     * delete all shifts in bucket
     * (used when the user clicks on the 'supprimer' button in the bucket popup)
     *
     * @Route("/bucket/{id}", name="bucket_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deleteBucketAction(Request $request, Shift $bucket)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createBucketDeleteForm($bucket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $shifts = $em->getRepository('App:Shift')->findBy([
                'job' => $bucket->getJob(),
                'start' => $bucket->getStart(),
                'end' => $bucket->getEnd()
            ]);
            $count = 0;
            foreach ($shifts as $shift) {
                $beneficiary = $shift->getShifter();
                $dispatcher = $this->get('event_dispatcher');
                $dispatcher->dispatch(ShiftDeletedEvent::NAME, new ShiftDeletedEvent($shift, $beneficiary));
                $em->remove($shift);
                $count++;
            }
            $em->flush();
            $success = true;
            $message = $count . (($count > 1) ? " créneaux ont été supprimés" : " créneau a été supprimé") . " !";
        } else {
            $success = false;
            $message = "Une erreur s'est produite... Impossible de supprimer le créneau. " . (string) $form->getErrors(true, false);
        }
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(array('message'=>$message), $success ? 200 : 400);
        } else {
            $session = new Session();
            $session->getFlashBag()->add($success ? 'success' : 'error', $message);
            return $this->redirectToRoute('booking_admin');
        }
    }

    /**
     * Creates a form to lock/unlock a bucket entity.
     *
     * @param Shift $bucket One shift of the bucket
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createBucketLockUnlockForm(Shift $bucket)
    {
        return $this->get('form.factory')->createNamedBuilder('bucket_lock_unlock_form')
            ->setAction($this->generateUrl('bucket_lock_unlock', array('id' => $bucket->getId())))
            ->add('lock', HiddenType::class, [
                'data'  => ($bucket->isLocked() ? 0 : 1),
            ])
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Creates a form to add a bucket (shift(s)).
     *
     * @param Shift $bucket One shift of the bucket
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createBucketShiftAddForm(Shift $bucket)
    {
        return $this->get('form.factory')->createNamed(
            'bucket_shift_add_form',
            ShiftType::class,
            $bucket,
            array(
                'action' => $this->generateUrl('shift_new'),
                'only_add_formation' => true,
            ));
    }

    /**
     * Creates a form to delete a bucket entity.
     *
     * @param Shift $bucket One shift of the bucket
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createBucketDeleteForm(Shift $bucket)
    {
        return $this->get('form.factory')->createNamedBuilder('bucket_delete_form')
            ->setAction($this->generateUrl('bucket_delete', array('id' => $bucket->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Creates a form to book a shift entity.
     * // TODO: how to avoid having same createShiftBookAdminForm in ShiftController ?
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
     * // TODO: how to avoid having same createShiftDeleteForm in ShiftController ?
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
     * // TODO: how to avoid having similar createShiftFreeForm in ShiftController ?
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftFreeForm(Shift $shift)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('shift_free', array('id' => $shift->getId())))
            ->add('reason', TextareaType::class, array('required' => false, 'label' => 'Justification éventuelle', 'attr' => array('class' => 'materialize-textarea')))
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Creates a form to free a shift entity (admin side).
     * // TODO: how to avoid having same createShiftFreeAdminForm in ShiftController ?
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
     * // TODO: how to avoid having same createShiftValidateInvalidateAdminForm in ShiftController ?
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
                'data' => ($shift->getWasCarriedOut() ? 0 : 1),
            ])
            ->setMethod('POST')
            ->getForm();
    }
}
