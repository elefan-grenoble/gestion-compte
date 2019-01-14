<?php

namespace AppBundle\Controller;

use AppBundle\Event\ShiftBookedEvent;
use AppBundle\Event\ShiftDeletedEvent;
use AppBundle\Event\ShiftDismissedEvent;
use AppBundle\Event\ShiftFreedEvent;
use AppBundle\Security\MembershipVoter;
use DateTime;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\Date;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Doctrine\ORM\EntityManager;

/**
 * User controller.
 *
 * @Route("booking")
 */
class BookingController extends Controller
{

    public function homepageAction()
    {
        $undismissShiftForm = $this->createFormBuilder()
            ->setAction($this->generateUrl('undismiss_shift'))
            ->setMethod('POST')
            ->add('shift_id', HiddenType::class)
            ->getForm();

        return $this->render('booking/home_booked_shifts.html.twig', [
            'undismiss_shift_form' => $undismissShiftForm->createView()
        ]);
    }

    /**
     * @Route("/", name="booking")
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED', user)")
     * @Method({"GET","POST"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $mode = null;
        if ($current_app_user->getBeneficiary() == null) {
            $session->getFlashBag()->add('error', 'Oups, tu n\'as pas de bénéficiaire enregistré ! MODE ADMIN');
            return $this->redirectToRoute('booking_admin');
        } else {
            $remainder = $current_app_user->getBeneficiary()->getMembership()->getRemainder();
            if (intval($remainder->format("%R%a")) < 0) {
                $session->getFlashBag()->add('warning', 'Oups, ton adhésion  a expiré il y a ' . $remainder->format('%a jours') . '... n\'oublie pas de ré-adhérer pour effectuer ton bénévolat !');
                return $this->redirectToRoute('homepage');
            }
        }

        $beneficiaries = $current_app_user->getBeneficiary()->getMembership()->getBeneficiaries();

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
                'jobs' => $em->getRepository('AppBundle:Job')->findAll()
            ]);

        } else { // no beneficiary selected

            return $this->render('booking/index.html.twig', [
                'beneficiary_form' => $beneficiaryForm->createView(),
            ]);
        }

    }

    /**
     * @Route("/admin", name="booking_admin")
     * @Security("has_role('ROLE_ADMIN')")
     * @Method({"GET","POST"})
     */
    public function adminAction(Request $request)
    {
        $monday = strtotime('last monday', strtotime('tomorrow'));
        $defaultFrom = new DateTime();
        $defaultFrom->setTimestamp($monday);

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('booking_admin'))
            ->add('from', TextType::class, [
                'label' => 'A partir de',
                'required' => true,
                'data' => $defaultFrom->format('Y-m-d'),
                'attr' => array('class' => 'datepicker')])
            ->add('to', TextType::class, [
                'label' => 'Jusqu\'à',
                'required' => false,
                'data' => '',
                'attr' => array('class' => 'datepicker')])
            ->add('action', HiddenType::class, array())
            ->add('filter', SubmitType::class, array('label' => 'Filtrer', 'attr' => array('class' => 'btn', 'value' => 'filtrer')))
            ->add('booker', SubmitType::class, array('label' => 'Voir les booker', 'attr' => array('class' => 'btn', 'value' => 'booker')))
            ->getForm();
        $form->handleRequest($request);

        $from = $defaultFrom;
        $to = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $dateStr = $form->get('from')->getData();
            $from = new DateTime($dateStr);
            $to = $form->get('to')->getData();
            if ($to)
                $to = new DateTime($to);
        }

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $jobs = $em->getRepository('AppBundle:Job')->findAll();


        $beneficiariesQb = $em->getRepository('AppBundle:Beneficiary')
            ->createQueryBuilder('b')
            ->select('b, m')
            ->join('b.user', 'u')
            ->join('b.membership', 'm');
        $beneficiaries = $beneficiariesQb->getQuery()->getResult();

        $shifts = $em->getRepository('AppBundle:Shift')->findFrom($from, $to);

        $action = $form->get('action')->getData();

        if ($action == "booker") {
            $mm = array();
            foreach ($shifts as $shift) {
                if ($shift->getBooker()) {
                    $mm[] = $shift->getBooker()->getMembership()->getMemberNumber();
                }
            }
            return $this->redirectToRoute('user_index', array('membernumber' => implode(',', $mm)));
        } else {
            $hours = array();
            for ($i = 6; $i < 22; $i++) { //todo put this in conf
                $hours[] = $i;
            }

            $bucketsByDay = array();
            foreach ($shifts as $shift) {
                $day = $shift->getStart()->format("d m Y");
                $job = $shift->getJob()->getId();
                $interval = $shift->getIntervalCode();
                if (!isset($bucketsByDay[$day])) {
                    $bucketsByDay[$day] = array();
                }
                if (!isset($bucketsByDay[$day][$job])) {
                    $bucketsByDay[$day][$job] = array();
                }
                if (!isset($bucketsByDay[$day][$job][$interval])) {
                    $bucket = new ShiftBucket();
                    $bucketsByDay[$day][$job][$interval] = $bucket;
                }
                $bucketsByDay[$day][$job][$interval]->addShift($shift);
            }

            $delete_bucket_form = $this->createFormBuilder()
                ->setAction($this->generateUrl('delete_bucket'))
                ->setMethod('DELETE')
                ->add('shift_id', HiddenType::class)
                ->getForm();

            return $this->render('admin/booking/index.html.twig', [
                'form' => $form->createView(),
                'bucketsByDay' => $bucketsByDay,
                'hours' => $hours,
                'jobs' => $jobs,
                'delete_bucket_form' => $delete_bucket_form->createView(),
                'beneficiaries' => $beneficiaries
            ]);
        }
    }

    /**
     * delete all shifts in bucket.
     *
     * @Route("/delete_bucket/", name="delete_bucket")
     * @Security("has_role('ROLE_ADMIN')")
     * @Method("DELETE")
     */
    public function deleteBucketAction(Request $request, \Swift_Mailer $mailer)
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
                $session->getFlashBag()->add('xarning', "shift not found");
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
    public function bookShiftAction(Request $request, Shift $shift)
    {
        $beneficiaryId = $request->get("beneficiaryId");
        $em = $this->getDoctrine()->getManager();
        $beneficiary = $em->getRepository('AppBundle:Beneficiary')->find($beneficiaryId);

        // Check if the shift is bookable by the given beneficiary
        // Also check if the beneficiary belongs to the same membership as the current user
        if (!$beneficiary
            || !$this->get('shift_service')->isShiftBookable($shift, $beneficiary)
            || !$this->isGranted(MembershipVoter::EDIT, $beneficiary->getMembership())
        ) {
            $session = new Session();
            $session->getFlashBag()->add("error", "Impossible de réserver ce créneau");
            return $this->redirectToRoute("booking");
        }

        if (!$shift->getBooker()) {
            $shift->setBooker($beneficiary);
            $shift->setBookedTime(new DateTime('now'));
        }
        $shift->setShifter($beneficiary);
        $shift->setIsDismissed(false);
        $shift->setDismissedReason(null);
        $shift->setDismissedTime(null);
        $shift->setLastShifter(null);
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

        return $this->redirectToRoute('homepage');
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

        $membership = $shift->getShifter()->getMembership();

        $em = $this->getDoctrine()->getManager();

        $shift->setIsDismissed(true);
        $shift->setDismissedTime(new DateTime('now'));
        $shift->setDismissedReason($request->get("reason"));
        $shift->setShifter($shift->getBooker());

        $em->persist($shift);
        $em->flush();

        $dispatcher = $this->get('event_dispatcher');
        $dispatcher->dispatch(ShiftDismissedEvent::NAME, new ShiftDismissedEvent($shift, $membership));

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
                $session->getFlashBag()->add('xarning', "shift not found");
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
                $beneficiary = $shift->getLastShifter();
                $shift->setBooker($beneficiary);
                $shift->setShifter($beneficiary);
                $shift->setBookedTime(new DateTime('now'));
                $shift->setLastShifter(null);
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
     * @Security("has_role('ROLE_ADMIN')")
     * @Method("POST")
     */
    public function bookShiftAdminAction(Request $request, Shift $shift)
    {
        $session = new Session();

        if ($shift->getShifter() && !$shift->getIsDismissed()) {
            $session->getFlashBag()->add("error", "Désolé, ce créneau est déjà réservé");
            return $this->redirectToRoute("booking_admin");
        }

        $str = $request->get('beneficiary');
        $em = $this->getDoctrine()->getManager();
        $beneficiary = $em->getRepository('AppBundle:Beneficiary')->findFromAutoComplete($str);

        if ($beneficiary) {

            if ($shift->getFormation() && !$beneficiary->getFormations()->contains($shift->getFormation())) {
                $session->getFlashBag()->add("error", "Désolé, ce bénévole n'a pas la qualification necessaire (" . $shift->getFormation()->getName() . ")");
                return $this->redirectToRoute("booking_admin");
            }

            if (!$shift->getBooker()) {
                $shift->setBooker($beneficiary);
                $shift->setBookedTime(new DateTime('now'));
            }
            $shift->setShifter($beneficiary);
            $shift->setIsDismissed(false);
            $shift->setDismissedReason(null);
            $shift->setDismissedTime(null);
            $shift->setLastShifter(null);

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
            return $this->redirectToRoute('booking_admin');
        }
    }

    /**
     * free a shift.
     *
     * @Route("/free_shift/{id}", name="free_shift")
     * @Method("POST")
     */
    public function freeShiftAction(Request $request, Shift $shift)
    {
        $this->denyAccessUnlessGranted('free', $shift);

        $session = new Session();

        $membership = $shift->getShifter()->getMembership();

        $em = $this->getDoctrine()->getManager();
        $shift->free();
        $em->persist($shift);
        $em->flush();

        $dispatcher = $this->get('event_dispatcher');
        $dispatcher->dispatch(ShiftFreedEvent::NAME, new ShiftFreedEvent($shift, $membership));

        $session->getFlashBag()->add('success', "Le shift a bien été libéré");

        return $this->redirectToRoute('member_show', array('member_number' => $membership->getMemberNumber()));

    }

}
