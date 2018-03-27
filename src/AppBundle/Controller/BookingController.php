<?php

namespace AppBundle\Controller;

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

/**
 * User controller.
 *
 * @Route("booking")
 */
class BookingController extends Controller
{
    /**
     * @Route("/", name="booking")
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED', user)")
     * @Method({"GET","POST"})
     */
    public function indexAction(Request $request)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $mode = null;
        if ($current_app_user->getBeneficiaries()->count()<1){
            $session->getFlashBag()->add('error', 'Oups, tu n\'as pas de bénéficiaire enregistré ! MODE ADMIN');
            return $this->redirectToRoute('booking_admin');
        }else{
            $remainder = $current_app_user->getRemainder();
            if (intval($remainder->format("%R%a"))<0){
                $session->getFlashBag()->add('warning', 'Oups, ton adhésion  a expiré il y a '.$remainder->format('%a jours').'... n\'oublie pas de ré-adhérer pour effectuer ton bénévolat !');
                return $this->redirectToRoute('homepage');
            }
        }

        $beneficiaryForm = $this->createFormBuilder()
            ->setAction($this->generateUrl('booking'))
            ->setMethod('POST')
            ->add('beneficiary', EntityType::class, array(
                'label' => 'Réserver un créneau pour',
                'required' => true,
                'class' => 'AppBundle:Beneficiary',
                'choices' => $current_app_user->getBeneficiaries(),
                'choice_label' => 'firstname',
                'multiple' => false,
            ))
            ->getForm();

        $beneficiaryForm->handleRequest($request);

        //beneficiary selected, or only one beneficiary
        if ($beneficiaryForm->isSubmitted() && $beneficiaryForm->isValid() || $current_app_user->getBeneficiaries()->count()==1 ) {

            $em = $this->getDoctrine()->getManager();
            if ($current_app_user->getBeneficiaries()->count() > 1){
                $beneficiary = $beneficiaryForm->get('beneficiary')->getData();
                $roles = $beneficiary->getRoles();
            }else {
                $beneficiary = $current_app_user->getBeneficiaries()->first();
                $roles = $beneficiary->getRoles();
            }

            $shifts = $em->getRepository('AppBundle:Shift')->findFrom(new \Datetime('now'));

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

            return $this->render('booking/index.html.twig', [
                'bucketsByDay' => $bucketsByDay,
                'hours' => $hours,
                'beneficiary' => $beneficiary,
                'jobs' => $em->getRepository('AppBundle:Job')->findAll()
            ]);

        }else{ // no beneficiary selected

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
                'attr' => array( 'class' => 'datepicker')])
            ->add('to', TextType::class, [
                'label' => 'Jusqu\'à',
                'required' => false,
                'data' => '',
                'attr' => array( 'class' => 'datepicker')])
            ->add('action', HiddenType::class,array())
            ->add('filter', SubmitType::class, array('label' => 'Filtrer','attr' => array('class' => 'btn','value' => 'filtrer')))
            ->add('booker', SubmitType::class, array('label' => 'Voir les booker','attr' => array('class' => 'btn','value' => 'booker')))
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

        $em = $this->getDoctrine()->getManager();
        $jobs = $em->getRepository('AppBundle:Job')->findAll();
        $beneficiaries = $em->getRepository('AppBundle:Beneficiary')->findAll();

        $shifts = $em->getRepository('AppBundle:Shift')->findFrom($from,$to);

        $action = $form->get('action')->getData();

        if ($action == "booker"){
            $mm = array();
            foreach ($shifts as $shift) {
                if ($shift->getBooker()){
                    $mm[] = $shift->getBooker()->getUser()->getMemberNumber();
                }
            }
            return $this->redirectToRoute('user_index',array('membernumber'=>implode(',',$mm)));
        }else{
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
                ->add('shift_id',HiddenType::class)
                ->getForm();

            return $this->render('admin/booking/index.html.twig', [
                'form'=> $form->createView(),
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
    public function deleteBucketAction(Request $request, \Swift_Mailer $mailer){

        $session = new Session();
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('delete_bucket'))
            ->setMethod('DELETE')
            ->add('shift_id',HiddenType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() ) {
            $em = $this->getDoctrine()->getManager();
            $shift_id = $form->get('shift_id')->getData();
            $shift = $em->getRepository('AppBundle:Shift')->find($shift_id);
            if ($shift){
                $shifts = $em->getRepository('AppBundle:Shift')->findBy(array('job'=>$shift->getJob(),'start'=>$shift->getStart(),'end'=>$shift->getEnd()));
                $count = 0;
                foreach ($shifts as $s){
                    if ($s->getShifter()){ //warn shifter
                        $warn = (new \Swift_Message('[ESPACE MEMBRES] Crénéau supprimé'))
                            ->setFrom('membres@lelefan.org')
                            ->setTo($s->getShifter()->getEmail())
                            ->setBody(
                                $this->renderView(
                                    'emails/deleted_shift.html.twig',
                                    array('shift' => $shift)
                                ),
                                'text/html'
                            );
                        $mailer->send($warn);
                    }
                    $em->remove($s);
                    $count++;
                }
                $em->flush();
                $session->getFlashBag()->add('success', $count." shifts removed");
            }else{
                $session->getFlashBag()->add('xarning',"shift not found");
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
    public function bookShiftAction(Request $request, Shift $shift, \Swift_Mailer $mailer)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        if ($shift->getShifter() && !$shift->getIsDismissed()) {
            $session->getFlashBag()->add("error", "Désolé, ce créneau est déjà réservé");
            return $this->redirectToRoute("booking");   
        }

        $beneficiaryId = $request->get("beneficiaryId");

        $em = $this->getDoctrine()->getManager();

        $beneficiary = $em->getRepository('AppBundle:Beneficiary')->find($beneficiaryId);

        if (!$shift->getBooker()) {
            $shift->setBooker($beneficiary);
            $shift->setBookedTime(new DateTime('now'));
        }
        $shift->setShifter($beneficiary);
        $shift->setIsDismissed(false);
        $shift->setDismissedReason(null);
        $shift->setDismissedTime(null);

        $em->persist($shift);
        $em->flush();

        $archive = (new \Swift_Message('[ESPACE MEMBRES] BOOKING'))
            ->setFrom('membres@lelefan.org')
            ->setTo('creneaux@lelefan.org')
            ->setReplyTo($beneficiary->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/new_booking.html.twig',
                    array('shift' => $shift)
                ),
                'text/html'
            );
        $mailer->send($archive);

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
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        if (!$current_app_user->getBeneficiaries()->contains($shift->getShifter())) {
            $session->getFlashBag()->add('error', 'Oups, ce créneau ne vous appartient pas !');
            return $this->redirectToRoute('booking');
        }

        $em = $this->getDoctrine()->getManager();

        $shift->setIsDismissed(true);
        $shift->setDismissedTime(new DateTime('now'));
        $shift->setDismissedReason($request->get("reason"));
        $shift->setShifter($shift->getBooker());

        $em->persist($shift);
        $em->flush();

        return $this->redirectToRoute('homepage');
    }

    /**
     * Book a shift admin.
     *
     * @Route("/admin/shift/{id}/book", name="admin_shift_book")
     * @Security("has_role('ROLE_ADMIN')")
     * @Method("POST")
     */
    public function bookShiftAdminAction(Request $request, Shift $shift, \Swift_Mailer $mailer)
    {
        $session = new Session();

        if ($shift->getShifter() && !$shift->getIsDismissed()) {
            $session->getFlashBag()->add("error", "Désolé, ce créneau est déjà réservé");
            return $this->redirectToRoute("booking_admin");
        }

        $str = $request->get('beneficiary');
        $em = $this->getDoctrine()->getManager();
        $beneficiary = $em->getRepository('AppBundle:Beneficiary')->findFromAutoComplete($str);

        if ($beneficiary){

            if ($shift->getRole() && !$beneficiary->getRoles()->contains($shift->getRole())){
                $session->getFlashBag()->add("error", "Désolé, ce bénévole n'a pas la qualification necessaire (".$shift->getRole()->getName().")");
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

            $em->persist($shift);
            $em->flush();

            $archive = (new \Swift_Message('[ESPACE MEMBRES] BOOKING'))
                ->setFrom('membres@lelefan.org')
                ->setTo('creneaux@lelefan.org')
                ->setReplyTo($beneficiary->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/new_booking.html.twig',
                        array('shift' => $shift)
                    ),
                    'text/html'
                );
            $mailer->send($archive);

            $session->getFlashBag()->add("success", "Créneau réservé avec succès pour ".$shift->getShifter());
            return $this->redirectToRoute('booking_admin');
        }


    }

}
