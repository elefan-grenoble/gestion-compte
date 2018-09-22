<?php

namespace AppBundle\Controller;

use AppBundle\Entity\BookedShift;
use AppBundle\Entity\Code;
use AppBundle\Entity\HelloassoPayment;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Entity\User;
use AppBundle\Event\HelloassoEvent;
use AppBundle\Twig\Extension\AppExtension;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Serializer\Encoder\JsonDecode;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $first = null;
        $em = $this->getDoctrine()->getManager();
        $securityContext = $this->container->get('security.authorization_checker');
        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $session = new Session();
            $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
            if ($current_app_user->getWithdrawn()){
                $this->container->get('security.token_storage')->setToken(null);
                $this->container->get('session')->invalidate();
                $session->getFlashBag()->add('error', 'Compte fermé !');
                return $this->redirectToRoute('homepage');
            }
            $remainder = $current_app_user->getRemainder();
            if ($current_app_user->getMemberNumber()>0) { //member only
                if ($remainder->format("%R%a") < \DateInterval::createFromDateString('1 month')){
                    if (intval($remainder->format("%R%a"))<0)
                        $session->getFlashBag()->add('error', 'Oups, ton adhésion  a expiré il y a '.$remainder->format('%a jours').'... n\'oublie pas de ré-adhérer !');
                    elseif (intval($remainder->format("%R%a"))<15) //todo put this in conf
                        $session->getFlashBag()->add('warning', 'Ton adhésion expire dans '.$remainder->format('%a jours').'...');
                }else{
                    $session->getFlashBag()->add('error', 'Aucune adhésion enregistrée !');
                }
                $dayAfterEndOfCycle = clone $current_app_user->endOfCycle();
                $dayAfterEndOfCycle->modify('+1 day');
                if ($current_app_user->getFrozenChange() && !$current_app_user->getFrozen()){
                    $now = new \DateTime('now');
                    $session->getFlashBag()->add('warning',
                        'Comme demandé, ton compte sera gelé dans '.
                        date_diff($now,$current_app_user->endOfCycle())->format('%a jours').
                        ', le <strong>'.AppExtension::date_fr_long($dayAfterEndOfCycle).'</strong>'.
                        " Pour annuler, visite <a style=\"text-decoration:underline;color:white;\" href=\"".
                         $this->get('router')->generate('fos_user_profile_edit')
                        ."\">ton profil <i class=\"material-icons tiny\">settings</i></a>");
                }
                if ($current_app_user->getFrozenChange() && $current_app_user->getFrozen()){
                    $now = new \DateTime('now');
                    $session->getFlashBag()->add('notice',
                        'Comme demandé, ton compte sera dégelé dans '.
                        date_diff($now,$current_app_user->endOfCycle())->format('%a jours').
                        ', le <strong>'.AppExtension::date_fr_long($dayAfterEndOfCycle).'</strong>'.
                        " Pour annuler, visite <a style=\"text-decoration:underline;color:white;\" href=\"".
                        $this->get('router')->generate('fos_user_profile_edit')
                        ."\">ton profil <i class=\"material-icons tiny\">settings</i></a>");
                }
            }
        }else{
            //TMP to remove in a few weeks (set on 22 sept 2018)
            // document.cookie = "MEMBRESSID=;domain=.lelefan.org; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
            unset($_COOKIE['MEMBRESSID']);
            setcookie ( 'MEMBRESSID','', time() - 3600, '/', '.lelefan.org' );

            $today = strtotime('today');
            $from = new \DateTime();
            $from->setTimestamp($today);
//            $nextMonday = strtotime('next monday');
//            $to = new \DateTime();
//            $to->setTimestamp($nextMonday);
            $to = new \DateTime();
            $to->modify('+7 days');
            $shifts = $em->getRepository('AppBundle:Shift')->findFrom($from,$to);

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
            return $this->render('default/index.html.twig', [
                'bucketsByDay' => $bucketsByDay,
                'hours' => $hours
            ]);
        }
        $qb = $em->createQueryBuilder();
        $futur_events = $qb->select('e')->from('AppBundle\Entity\Event', 'e')
            ->Where("e.date > :now" )
            ->orderBy("e.id", 'ASC')
            ->setParameter('now',new \DateTime())
            ->getQuery()
            ->getResult();

        $undismiss_shift_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('undismiss_shift'))
            ->setMethod('POST')
            ->add('shift_id',HiddenType::class)
            ->getForm();

        $codes = $em->getRepository('AppBundle:Code')->findBy(array('closed'=>null),array('createdAt'=>'DESC'));
        if (!$codes){
            $codes[] = new Code();
        }
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
            'undismiss_shift_form' => $undismiss_shift_form->createView(),
            'events' => $futur_events,
            'codes' => $codes
        ]);
    }

    /**
     * @Route("/check", name="check")
     * @Method({"POST","GET"})
     */
    public function checkAction(Request $request){
        $session = new Session();
        $code = $request->get('swipe_code');
        if (!$code){
            return $this->redirectToRoute('homepage');
        }
        $em = $this->getDoctrine()->getManager();
        $card = $em->getRepository('AppBundle:SwipeCard')->findOneBy(array('code'=>$code,'enable'=>1));
        if (!$card){
            $session->getFlashBag()->add("error","Oups, ce badge n'est pas actif ou n'existe pas");
        }else{
            $beneficiary = $card->getBeneficiary();
            return $this->render('user/check.html.twig', [
                'beneficiary' => $beneficiary,
                'counter' => $beneficiary->getUser()->getTimeCount($beneficiary->getUser()->endOfCycle(0))
            ]);
        }

        return $this->redirectToRoute('homepage');
    }

    /**
     * @Route("/find_me", name="find_me")
     */
    public function activeUserAccountAction(Request $request){
        $form = $this->createFormBuilder()
            ->add('member_number', IntegerType::class, array('label' => 'Numéro d\'adhérent','attr' => array(
                'placeholder' => '0',
            )))
            ->add('find', SubmitType::class, array('label' => 'Activer mon compte'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {
            $member_number = $form->get('member_number')->getData();
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository('AppBundle:User')->findOneBy(array('member_number'=>$member_number));

            return $this->render('user/confirm.html.twig', array(
                'user' => $user,
            ));
        }
        return $this->render('user/find_me.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * @Route("/{member_number}/confirm", name="confirm")
     * @Method({"POST"})
     */
    public function confirmAction(User $user,Request $request){

        return $this->render('user/confirm.html.twig', array(
            'user' => $user,
        ));
    }

    /**
     * @Route("/{member_number}/set_email", name="set_email")
     * @Method({"POST"})
     */
    public function setEmailAction(User $user,Request $request){
        $email = $request->request->get('email');
        $oldEmail = $user->getEmail();
        $r = preg_match_all('/(membres\\+[0-9]+@lelefan\\.org)/i', $oldEmail, $matches, PREG_SET_ORDER, 0); //todo put regex in conf
        if (count($matches) && filter_var($email,FILTER_VALIDATE_EMAIL)){ //was a temp mail
            $user->setEmail($email);
            $user->getMainBeneficiary()->setEmail($email);
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();
            $request->getSession()->getFlashBag()->add('success', 'Merci ! votre email a bien été entregistré');
        }elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)){
            $request->getSession()->getFlashBag()->add('warning', 'Oups, le format du courriel entré semble problèmatique');
        }
        return $this->render('user/confirm.html.twig', array(
            'user' => $user,
        ));
    }

    /**
     * @Route("/find_user_number", name="find_user_number")
     */
    public function findUserNumberAction(Request $request){
        $securityContext = $this->container->get('security.authorization_checker');
        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $form = $this->createFormBuilder()
                ->add('firstname', TextType::class, array('label' => 'Le prénom','attr' => array(
                    'placeholder' => 'babar',
                )))
                ->add('find', SubmitType::class, array('label' => 'Trouver le numéro'))
                ->getForm();
        }else{
            $form = $this->createFormBuilder()
                ->add('firstname', TextType::class, array('label' => 'Mon prénom','attr' => array(
                    'placeholder' => 'babar',
                )))
                ->add('find', SubmitType::class, array('label' => 'Trouver mon numéro'))
                ->getForm();
        }

        if ($form->handleRequest($request)->isValid()) {
            $firstname = $form->get('firstname')->getData();
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            $beneficiaries = $qb->select('b')->from('AppBundle\Entity\Beneficiary', 'b')
                ->join('b.user', 'u')
                ->where( $qb->expr()->like('b.firstname', $qb->expr()->literal('%'.$firstname.'%')))
                ->andWhere("u.withdrawn != 1 or u.withdrawn is NULL" )
                ->orderBy("u.member_number", 'ASC')
                ->getQuery()
                ->getResult();
            return $this->render('user/find_user_number.html.twig', array(
                'form' => null,
                'beneficiaries' => $beneficiaries,
                'return_path' => 'confirm',
                'params' => array()
            ));
        }
        return $this->render('user/find_user_number.html.twig', array(
            'form' => $form->createView(),
            'beneficiaries' => ''
        ));
    }
    /**
     * @Route("/help_find_user", name="find_user_help")
     */
    public function findUserHelpAction(Request $request){

        return $this->render('default/find_user_number.html.twig');
    }

    /**
     * @Route("/find_user", name="find_user")
     */
    public function findUserAction(Request $request){
        die($request->getName());
    }

    /**
     * @Route("/helloassoNotify", name="helloasso_notify")
     * @Method({"POST"})
     * inspiré de
     * https://github.com/Breizhicoop/HelloDoli/blob/master/adhesion.php
     * https://github.com/Mailforgood/HelloAsso.Api.Doc/blob/master/HelloAsso.Api.Samples/php/helloasso_stat.php
     */
    public function helloassoNotify(Request $request){

        $logger = $this->get('logger');
        $logger->info('helloasso notify',$_POST);

        $actionId = $_POST['action_id'];

        if (!$actionId){ //missing notification id
            $logger->info("missing action id");
            return $this->json(array('success' => false, "message"=> "missing action id in POST content"));
        }

        $actionId = str_pad($actionId, 12, '0', STR_PAD_LEFT);

        $action_json = $this->container->get('AppBundle\Helper\Helloasso')->get('actions/'.$actionId);

        if (!isset($action_json->id)){
            if(isset($action_json->code)){
                return $this->json(array('success' => false, "code"=>$action_json->code, "message"=> $action_json->message));
            }
            return $this->json(array('success' => false, "message"=> "wrong api response"));
        }
        $payment_json = $this->container->get('AppBundle\Helper\Helloasso')->get('payments/'.$action_json->id_payment);
        if (!isset($payment_json->id)){
            if(isset($payment_json->code)){
                return $this->json(array('success' => false, "code"=>$payment_json->code, "message"=> $payment_json->message));
            }
            return $this->json(array('success' => false, "message"=> "wrong api response"));
        }

        $em = $this->getDoctrine()->getManager();
        $exist = $em->getRepository('AppBundle:HelloassoPayment')->findOneBy(array('paymentId'=>$payment_json->id));

        if ($exist){ //notification already exist
            $logger->info("notification already exist");
            return $this->json(array('success' => false, "message"=> "notification already exist"));
        }

        $payments = array();
        $action_json = null;
        $dispatcher = $this->get('event_dispatcher');
        foreach ($payment_json->actions as $action){
            $action_json = $this->container->get('AppBundle\Helper\Helloasso')->get('actions/' . $action->id);
            $payment = $em->getRepository('AppBundle:HelloassoPayment')->findOneBy(array('paymentId'=>$payment_json->id));
            if ($payment){ //payment already exist (created from a previous actions in THIS loop)
                $amount = $action_json->amount;
                $amount = str_replace(',', '.', $amount);
                $payment->setAmount($payment->getAmount()+$amount);
            }else{
                $payment = new HelloassoPayment();
                $payment->fromActionObj($action_json);
            }
            $em->persist($payment);
            $em->flush();
            $payments[$payment->getId()] = $payment;
        }
        foreach ($payments as $payment){
            $dispatcher->dispatch(
                HelloassoEvent::PAYMENT_AFTER_SAVE,
                new HelloassoEvent($payment)
            );
        }

        return $this->json(array('success' => true));

    }

    /**
     * @Route("/shift/{id}/contact_form", name="shift_contact_form")
     * @Method({"GET","POST"})
     */
    public function shiftContactFormAction(Shift $shift,Request $request, \Swift_Mailer $mailer){

        $formBuilder = $this->createFormBuilder();
        $formBuilder->add('from',HiddenType::class,array('data'=>$shift->getShifter()->getId()));
        $formBuilder->add('to',HiddenType::class);
        $formBuilder->add('message',TextareaType::class,array('attr'=>array('class'=>'materialize-textarea','label'=>'message')));
        $formBuilder->setAction($this->generateUrl('shift_contact_form',array('id'=>$shift->getId())));
        $formBuilder->setMethod('POST');
        $form = $formBuilder->getForm();

        if ($form->handleRequest($request)->isValid()) {
            $to = $form->get('to')->getData();
            $to = json_decode($to);
            $from = $form->get('from')->getData();
            $em = $this->getDoctrine()->getManager();
            $beneficiaries = $em->getRepository('AppBundle:Beneficiary')->findBy(array('id'=>$to));
            $from = $em->getRepository('AppBundle:Beneficiary')->findOneBy(array('id'=>$from));
            $emails = array();
            $firstnames = array();
            foreach ($beneficiaries as $beneficiary){
                $emails[] = $beneficiary->getEmail();
                $firstnames[] = $beneficiary->getFirstname();
            }
            $message = (new \Swift_Message('[ESPACE MEMBRES] Un message de '.$from->getFirstName()))
                ->setFrom($this->getParameter('transactional_mailer_user'))
                ->setReplyTo($this->getParameter('transactional_mailer_user'))
                ->setBcc($emails)
                ->setBody(
                    $this->renderView(
                        'emails/coshifter_message.html.twig',
                        array(
                            'message' => trim($form->get('message')->getData()),
                            'from' => $from,
                            'shift' => $shift)
                    ),
                    'text/html'
                );
            $mailer->send($message);
            $session = new Session();
            if (count($firstnames) > 1){
                $last_firstname = array_pop($firstnames);
                $firstnames = implode(', ',$firstnames);
                $firstnames .= ' et '.$last_firstname;
            }else{
                $firstnames = $firstnames[0];
            }

            $session->getFlashBag()->add('success','Ton message a été transmit à '.$firstnames);
            return $this->redirectToRoute('homepage');
        }else{
            $em = $this->getDoctrine()->getManager();
            $shifts = $em->getRepository('AppBundle:Shift')->findBy(array('start'=>$shift->getStart(),'end'=>$shift->getEnd()));
            $coShifts = array();
            foreach ($shifts as $s){
                if ($s->getBooker() != null && $s->getId()!=$shift->getId()){
                    $coShifts[] = $s;
                }
            }
            return $this->render('booking/_partial/home_shift_contactform.html.twig', array(
                'shift' => $shift,
                'coShifts' => $coShifts,
                'form' => $form->createView()
            ));
        }
    }
}
