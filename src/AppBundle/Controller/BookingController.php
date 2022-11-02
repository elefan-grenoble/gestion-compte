<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Job;
use AppBundle\Entity\Shift;
use AppBundle\Event\ShiftBookedEvent;
use AppBundle\Event\ShiftDeletedEvent;
use AppBundle\Event\ShiftDismissedEvent;
use AppBundle\Event\ShiftFreedEvent;
use AppBundle\Event\ShiftValidatedEvent;
use AppBundle\Event\ShiftInvalidatedEvent;
use AppBundle\Repository\JobRepository;
use AppBundle\Security\MembershipVoter;
use AppBundle\Security\ShiftVoter;
use DateTime;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Form\ShiftType;
use Exception;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("booking")
 */
class BookingController extends Controller
{
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
        $undismissShiftForm = $this->createFormBuilder()
            ->setAction($this->generateUrl('undismiss_shift'))
            ->setMethod('POST')
            ->add('shift_id', HiddenType::class)
            ->getForm();

        $beneficiaries = $this->getUser()->getBeneficiary()->getMembership()->getBeneficiaries();

        $em = $this->getDoctrine()->getManager();
        $period_positions = $em->getRepository('AppBundle:PeriodPosition')->findByBeneficiaries($beneficiaries);

        return $this->render('booking/home_booked_shifts.html.twig', array(
            'undismiss_shift_form' => $undismissShiftForm->createView(),
            'period_positions' => $period_positions,
        ));
    }


    /**
     * @Route("/", name="booking")
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED', user)")
     * @Method({"GET","POST"})
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws \Exception
     */
    public function indexAction(Request $request)
    {
        $session = new Session();
        $mode = null;
        if ($this->getUser()->getBeneficiary() == null) {
            $session->getFlashBag()->add('error', 'Oups, tu n\'as pas de bénéficiaire enregistré ! MODE ADMIN');
            return $this->redirectToRoute('booking_admin');
        } else {
            $remainder = $this->get('membership_service')->getRemainder($this->getUser()->getBeneficiary()->getMembership());
            if (intval($remainder->format("%R%a")) < 0) {
                $session->getFlashBag()->add('warning', 'Oups, ton adhésion  a expiré il y a ' . $remainder->format('%a jours') . '... n\'oublie pas de ré-adhérer pour effectuer ton bénévolat !');
                return $this->redirectToRoute('homepage');
            }
            if ($this->getUser()->getBeneficiary()->getMembership()->getFrozen()){
                $session->getFlashBag()->add('warning', 'Oups, ton compte est gelé ❄️ ! Dégel pour réserver 😉');
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
                'class' => 'AppBundle:Beneficiary',
                'choices' => $beneficiaries,
                'choice_label' => 'firstname',
                'multiple' => false,
            ))
            ->getForm();

        $beneficiaryForm->handleRequest($request);

        //beneficiary selected, or only one beneficiary
        if ($beneficiaryForm->isSubmitted() || $beneficiaries->count() == 1) {

            $em = $this->getDoctrine()->getManager();
            if ($beneficiaries->count() > 1) {
                $beneficiary = $beneficiaryForm->get('beneficiary')->getData();
            } else {
                $beneficiary = $beneficiaries->first();
            }

            $shifts = $em->getRepository('AppBundle:Shift')->findFutures();
            $bucketsByDay = $this->get('shift_service')->generateShiftBucketsByDayAndJob($shifts);
            $dismissedShifts = array();
            foreach ($shifts as $shift) {
                if ($shift->getIsDismissed()) {
                    $dismissedShifts[] = $shift;
                }
            }

            $hours = array();
            for ($i = 6; $i < 22; $i++) { //todo put this in conf
                $hours[] = $i;
            }

            return $this->render('booking/index.html.twig', [
                'bucketsByDay' => $bucketsByDay,
                'dismissedShifts' => $dismissedShifts,
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
     * and rerun an array with  the form object and the date range and the action
     *
     * the return object :
     * array(
     *      "form":FormBuilderInterface
     *      "from" => DateTime,
     *      "to" => DateTime,
     *      "job"=> Job|null,
     *      "filling"=>str|null,
     *      )
     */
    private function adminFilterFormFactory(Request $request): array
    {
        // filter creation ----------------------
        $defaultFrom = new DateTime();
        $defaultFrom->setTimestamp(strtotime('last monday', strtotime('tomorrow')));

        $defaultTo = new DateTime();
        $defaultTo->setTimestamp(strtotime('next sunday', strtotime('tomorrow')));

        $defaultWeek = (new DateTime())->format('W');
        $defaultYear = (new DateTime())->format('Y');

        $filterForm = $this->createFormBuilder()
            ->setAction($this->generateUrl('booking_admin'))
            ->add('type', ChoiceType::class, array(
                'label' => 'Type de filtre',
                'required' => true,
                'data' => "Date",
                'choices' => array(
                    'Date' => true,
                    'Semaine' => false,
                ),
            ))
            ->add('from', TextType::class, [
                'label' => 'A partir de',
                'required' => true,
                'data' => $defaultFrom->format('Y-m-d'),
                'attr' => array('class' => 'datepicker'),
            ])
            ->add('to', TextType::class, [
                'label' => 'Jusqu\'à',
                'required' => false,
                'data' => $defaultTo->format('Y-m-d'),
                'attr' => array('class' => 'datepicker'),
            ])
            ->add('year', IntegerType::class, [
                'required' => false,
                'label' => 'Année',
                'scale' => 0,
                'data' => $defaultYear,
                'attr' => [
                    'min' => 2000,
                    'max' => (int)$defaultYear + 10,
                ],
            ])
            ->add('week', IntegerType::class, [
                'required' => false,
                'label' => 'Numéro de semaine',
                'scale' => 0,
                'data' => $defaultWeek,
                'attr' => [
                    'min' => 1,
                    'max' => 52,
                ],
            ])
            ->add('job', EntityType::class, array(
                'label' => 'Type de créneau',
                'class' => 'AppBundle:Job',
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
            ->add(
                'filter',
                SubmitType::class,
                array('label' => 'Filtrer', 'attr' => array('class' => 'btn', 'value' => 'filtrer'))
            )
            ->getForm();

        $filterForm->handleRequest($request);
        $from = $defaultFrom;
        $to = $defaultTo;
        $job = null;
        $filling=null;

        try {
            if ($filterForm->isSubmitted() && $filterForm->isValid()) {

                $job = $filterForm->get("job")->getData();
                $filling = $filterForm->get("filling")->getData();

                if ($filterForm->get("type")->getData()) {
                    // selection mode based on dates

                    $from = new DateTime($filterForm->get('from')->getData());
                    $to = $filterForm->get('to')->getData();
                    if ($to) {
                        $to = new DateTime($to);
                    }

                } else {
                    // selection mode based on week number

                    $week = $filterForm->get("week")->getData();
                    $year = $filterForm->get("year")->getData();

                    $dateTime = new DateTime();
                    $dateTime->setISODate($year, $week, 1);
                    $from = clone $dateTime;
                    $dateTime->modify('+6 days');
                    $to = $dateTime;

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
            "job"=> $job,
            "filling"=>$filling,
        );
    }

    /**
     * build the bucket (regrouping all the shift at the same time
     * with the same job)
     * @param array $shifts
     * @param string|null $filling
     * @return array
     */
    private function bucketFactory(array $shifts, string $filling = null): array
    {
        // TODO Maybe it should be but in the BucketRepository...

        $bucketsByDay = array();
        foreach ($shifts as $shift) {

            $day = $shift->getStart()->format("d m Y");
            $jobId = $shift->getJob()->getId();

            $interval = $shift->getIntervalCode();
            if (!isset($bucketsByDay[$day])) {
                $bucketsByDay[$day] = array();
            }
            if (!isset($bucketsByDay[$day][$jobId])) {
                $bucketsByDay[$day][$jobId] = array();
            }
            if (!isset($bucketsByDay[$day][$jobId][$interval])) {
                $bucket = new ShiftBucket();
                $bucketsByDay[$day][$jobId][$interval] = $bucket;
            }
            $bucketsByDay[$day][$jobId][$interval]->addShift($shift);
        }

        if ($filling) {
            $shiftService = $this->container->get('shift_service');
            foreach ($bucketsByDay as $day => $bucketsByJob) {
                foreach ($bucketsByJob as $jobId => $bucketByInterval) {
                    foreach ($bucketByInterval as $interval => $bucket) {
                        $nbShifts = count($bucket->getShifts());
                        $bookableShifts = count($shiftService->getBookableShifts($bucket));
                        if  (($filling == 'empty' and  $bookableShifts != $nbShifts  )
                          or ($filling == 'full' and $bookableShifts !=  0)
                            or ( $filling == 'partial' and ($bookableShifts == $nbShifts  or $bookableShifts ==  0))) {

                            unset($bucketsByDay[$day][$jobId][$interval]);
                            if (count($bucketsByDay[$day][$jobId])==0){
                                unset($bucketsByDay[$day][$jobId]);
                                if (count($bucketsByDay[$day])==0){
                                    unset($bucketsByDay[$day]);
                                }
                            }

                        }
                    }
                }
            }
        }

        return $bucketsByDay;
    }

    /**
     * main administration page for booking shift
     * @Route("/admin", name="booking_admin")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method({"GET","POST"})
     */
    public function adminAction(Request $request): Response
    {

        $filter = $this->adminFilterFormFactory($request);

        // calendar creation
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $jobs = $em->getRepository(Job::class)->findByEnabled(true);
        $beneficiaries = $em->getRepository(Beneficiary::class)->findAllActive();
        $shifts = $em
            ->getRepository(Shift::class)
            ->findFrom($filter["from"], $filter["to"], $filter["job"]);

        $bucketsByDay = $this->bucketFactory($shifts, $filter["filling"]);

        $shift_delete_form = array();
        $shift_add_form = array();
        foreach ($shifts as $shift) {
            $shift_delete_form[$shift->getId()] = $this->createDeleteForm($shift)->createView();
            $shift_add_form[$shift->getId()] = $this->createForm('AppBundle\Form\ShiftType', $shift, [
                'action' => $this->generateUrl('shift_new'),
                'only_add_formation' => true
            ])->createView();
        }

        $delete_bucket_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('delete_bucket'))
            ->setMethod('DELETE')
            ->add('shift_id', HiddenType::class)
            ->getForm();

        return $this->render('admin/booking/index.html.twig', [
            'filterForm' => $filter["form"]->createView(),
            'bucketsByDay' => $bucketsByDay,
            'jobs' => $jobs,
            'beneficiaries' => $beneficiaries,
            'shift_delete_form' => $shift_delete_form,
            'shift_add_form' => $shift_add_form,
            'delete_bucket_form' => $delete_bucket_form->createView(),
        ]);

    }


    /**
     * @Route("/edit_bucket/{id}", name="shift_edit")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method({"GET", "POST"})
     */
    public function editBucketAction(Request $request,Shift $shift)
    {
        $session = new Session();

        $form = $this->createForm(ShiftType::class, $shift);
        // Keep a record of the shift before update
        $bucket = clone($shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $shifts = $em->getRepository('AppBundle:Shift')->findBy(array('job' => $bucket->getJob(), 'start' => $bucket->getStart(), 'end' => $bucket->getEnd()));
            foreach ($shifts as $s) {
                $s->setStart($shift->getStart());
                $s->setEnd($shift->getEnd());
                $s->setJob($shift->getJob());
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
     * delete all shifts in bucket.
     *
     * @Route("/delete_bucket/", name="delete_bucket")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method("DELETE")
     */
    public function deleteBucketAction(Request $request)
    {

        $session = new Session();
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('delete_bucket'))
            ->setMethod('DELETE')
            ->add('shift_id', HiddenType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $shift_id = $form->get('shift_id')->getData();
            $shift = $em->getRepository('AppBundle:Shift')->find($shift_id);
            if ($shift) {
                $shifts = $em->getRepository('AppBundle:Shift')->findBy(array('job' => $shift->getJob(), 'start' => $shift->getStart(), 'end' => $shift->getEnd()));
                $count = 0;
                foreach ($shifts as $s) {

                    $dispatcher = $this->get('event_dispatcher');
                    $dispatcher->dispatch(ShiftDeletedEvent::NAME, new ShiftDeletedEvent($s));

                    $em->remove($s);
                    $count++;
                }
                $em->flush();
                $session->getFlashBag()->add('success', $count . " shifts removed");
            } else {
                $session->getFlashBag()->add('warning', "shift not found");
            }
        }
        return $this->redirectToRoute('booking_admin');
    }

    /**
     * Book a shift.
     *
     * @Route("/shift/{id}/book", name="shift_book")
     * @Method("POST")
     */
    public function bookShiftAction(Shift $shift,Request $request): Response
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
        $shift->setIsDismissed(false);
        $shift->setDismissedReason(null);
        $shift->setDismissedTime(null);
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
     * Dismiss a booked shift.
     *
     * @Route("/shift/{id}/dismiss", name="shift_dismiss")
     * @Method("POST")
     */
    public function dismissShiftAction(Request $request, Shift $shift)
    {
        if (!$this->isGranted('dismiss', $shift)) {
            $session = new Session();
            $session->getFlashBag()->add("error", "Impossible d'annuler ce créneau");
            return $this->redirectToRoute("booking");
        }

        $beneficiary = $shift->getShifter();
        $em = $this->getDoctrine()->getManager();
        if($shift->isFixe()) {
            $session = new Session();
            $session->getFlashBag()->add("error", "Impossible d'annuler un créneau fixe");
            return $this->redirectToRoute("booking");
        } else {
            $shift->setShifter(null);
            $shift->setBooker(null);
            $shift->setFixe(false);
        }
        $em->persist($shift);
        $em->flush();

        $reason = $request->get("reason");
        $dispatcher = $this->get('event_dispatcher');
        $dispatcher->dispatch(ShiftDismissedEvent::NAME, new ShiftDismissedEvent($shift, $beneficiary, $reason));

        return $this->redirectToRoute('homepage');
    }

    /**
     * Undismiss a shift
     *
     * @Route("/undismiss_shift/", name="undismiss_shift")
     * @Method("POST")
     */
    public function undismissShift(Request $request)
    {

        $session = new Session();
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('undismiss_shift'))
            ->setMethod('POST')
            ->add('shift_id', HiddenType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $shift_id = $form->get('shift_id')->getData();
            $shift = $em->getRepository('AppBundle:Shift')->find($shift_id);
            if ($shift) {
                $shift->setIsDismissed(false);
                $shift->setDismissedTime(null);
                $shift->setDismissedReason(null);
                $em->persist($shift);
                $em->flush();

                $dispatcher = $this->get('event_dispatcher');
                $dispatcher->dispatch(ShiftBookedEvent::NAME, new ShiftBookedEvent($shift, false));

            } else {
                $session->getFlashBag()->add('warning', "shift not found");
            }
        }
        return $this->redirectToRoute('homepage');
    }

    /**
     * Accept a reserved shift
     *
     * @Route("/shift/{id}/accept/", name="accept_reserved_shift")
     * @Method("GET")
     */
    public function acceptReservedShift(Request $request, Shift $shift)
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
            $session->getFlashBag()->add('error', "shift not found");
        }

        return $this->redirectToRoute('homepage');
    }

    /**
     * Reject a reserved shift
     *
     * @Route("/shift/{id}/reject/", name="reject_reserved_shift")
     * @Method("GET")
     */
    public function rejectReservedShift(Request $request, Shift $shift)
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
            $session->getFlashBag()->add('error', "shift not found");
        }

        return $this->redirectToRoute('homepage');
    }

    /**
     * Book a shift admin.
     *
     * @Route("/admin/shift/{id}/book", name="admin_shift_book")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method("POST")
     */
    public function bookShiftAdminAction(Request $request, Shift $shift)
    {
        $session = new Session();

        if ($shift->getShifter() && !$shift->getIsDismissed()) {
            $session->getFlashBag()->add("error", "Désolé, ce créneau est déjà réservé");
            return new Response($this->generateUrl("booking_admin"), 205);
        }

        $content = json_decode($request->getContent());
        $str = $content->beneficiary;
        $fixe = $content->typeService;

        $em = $this->getDoctrine()->getManager();
        /**@var  Beneficiary $beneficiary*/
        $beneficiary = $em->getRepository('AppBundle:Beneficiary')->findFromAutoComplete($str);

        if (!$beneficiary) {
            $session->getFlashBag()->add("error", "Impossible de trouve ce béneficiaire 😕");
            return new Response($this->generateUrl("booking_admin"), 205);
        }

        if ($shift->getFormation() && !$beneficiary->getFormations()->contains($shift->getFormation())) {
            $session->getFlashBag()->add("error", "Désolé, ce bénévole n'a pas la qualification necessaire (" . $shift->getFormation()->getName() . ")");
            return new Response($this->generateUrl("booking_admin"), 205);
        }

        if (!$shift->getBooker()) {
            $current_user = $this->get('security.token_storage')->getToken()->getUser();
            $shift->setBooker($current_user);
            $shift->setBookedTime(new DateTime('now'));
        }
        $shift->setShifter($beneficiary);
        $shift->setIsDismissed(false);
        $shift->setDismissedReason(null);
        $shift->setDismissedTime(null);
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

        $session->getFlashBag()->add("success", "Créneau réservé avec succès pour " . $shift->getShifter());
        return new Response($this->generateUrl("booking_admin"), 200);

    }

    /**
     * free a shift.
     *
     * @Route("/free_shift/{id}", name="free_shift")
     * @Method("POST")
     */
    public function freeShiftAction(Request $request, Shift $shift)
    {
        $this->denyAccessUnlessGranted(ShiftVoter::FREE, $shift);

        $session = new Session();

        $membership = $shift->getShifter()->getMembership();

        $em = $this->getDoctrine()->getManager();
        $shift->free();
        $shift->invalidateShiftParticipation();
        $em->persist($shift);
        $em->flush();

        $dispatcher = $this->get('event_dispatcher');
        $dispatcher->dispatch(ShiftFreedEvent::NAME, new ShiftFreedEvent($shift, $membership));

        $session->getFlashBag()->add('success', "Le shift a bien été libéré");

        $referer = $request->headers->get('referer');

        return new RedirectResponse($referer);

    }

    /**
     * validate a shift.
     *
     * @Route("/validate_shift/{id}", name="validate_shift")
     * @Method("POST")
     */
    public function validateShiftAction(Request $request, Shift $shift)
    {
        $this->denyAccessUnlessGranted(ShiftVoter::VALIDATE, $shift);
        $session = new Session();

        if ($shift->getWasCarriedOut() == 0) {
            $membership = $shift->getShifter()->getMembership();

            $em = $this->getDoctrine()->getManager();
            $shift->validateShiftParticipation();
            $em->persist($shift);
            $em->flush();

            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(ShiftValidatedEvent::NAME, new ShiftValidatedEvent($shift));

            $session->getFlashBag()->add('success', "La participation au créneau a bien été validée");
        } else {
            $session->getFlashBag()->add('error', "La participation au créneau a déjà été validée");
        }

        $referer = $request->headers->get('referer');
        return new RedirectResponse($referer);
    }

    /**
     * invalidate a shift.
     *
     * @Route("/invalidate_shift/{id}", name="invalidate_shift")
     * @Method("POST")
     */
    public function invalidateShiftAction(Request $request, Shift $shift)
    {
        $this->denyAccessUnlessGranted(ShiftVoter::INVALIDATE, $shift);
        $session = new Session();

        if ($shift->getWasCarriedOut() == 1) {
            $membership = $shift->getShifter()->getMembership();

            $em = $this->getDoctrine()->getManager();
            $shift->invalidateShiftParticipation();
            $em->persist($shift);
            $em->flush();

            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(ShiftInvalidatedEvent::NAME, new ShiftInvalidatedEvent($shift, $membership));

            $session->getFlashBag()->add('success', "La participation au créneau a bien été invalidée");
        } else {
            $session->getFlashBag()->add('error', "La participation au créneau a déjà été invalidée");
        }

        $referer = $request->headers->get('referer');
        return new RedirectResponse($referer);
    }

    /**
     * lock a shift.
     *
     * @Route("/lock_shift/{id}", name="lock_shift")
     * @Method("GET")
     */
    public function lockShiftAction(Request $request, Shift $shift)
    {
        $this->denyAccessUnlessGranted(ShiftVoter::LOCK, $shift);

        $em = $this->getDoctrine()->getManager();

        if ($shift) {
            $bucket = $this->get('shift_service')->getShiftBucketFromShift($shift);
            foreach ($bucket->getShifts() as $s) {
                $s->setLocked(true);
            }
            $em->flush();
        }

        return $this->redirectToRoute('booking_admin');
    }

    /**
     * free a shift.
     *
     * @Route("/unlock_shift/{id}", name="unlock_shift")
     * @Method("GET")
     */
    public function unlockShiftAction(Request $request, Shift $shift)
    {
        $this->denyAccessUnlessGranted(ShiftVoter::LOCK, $shift);

        $em = $this->getDoctrine()->getManager();

        if ($shift) {
            $bucket = $this->get('shift_service')->getShiftBucketFromShift($shift);
            foreach ($bucket->getShifts() as $s) {
                $s->setLocked(false);
            }
            $em->flush();
        }

        return $this->redirectToRoute('booking_admin');
    }

    /**
     * Creates a form to delete a shift entity.
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Shift $shift)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('shift_delete', array('id' => $shift->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
