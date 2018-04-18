<?php

namespace AppBundle\Controller;

use AppBundle\Entity\BookedShift;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Session\Session;

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
            $remainder = $current_app_user->getRemainder();
            if ($remainder->format("%R%a") < \DateInterval::createFromDateString('1 month')){
                if (intval($remainder->format("%R%a"))<0)
                    $session->getFlashBag()->add('error', 'Oups, ton adhésion  a expiré il y a '.$remainder->format('%a jours').'... n\'oublie pas de ré-adhérer !');
                elseif (intval($remainder->format("%R%a"))<15) //todo put this in conf
                    $session->getFlashBag()->add('warning', 'Ton adhésion expire dans '.$remainder->format('%a jours').'...');
            }else{
                $session->getFlashBag()->add('error', 'Aucune adhésion enregistrée !');
            }
        }else{
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

        $accept_reserved_shift_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('accept_reserved_shift'))
            ->setMethod('POST')
            ->add('accept_shift_id',HiddenType::class)
            ->getForm();

        $reject_reserved_shift_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('reject_reserved_shift'))
            ->setMethod('POST')
            ->add('reject_shift_id',HiddenType::class)
            ->getForm();


        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
            'undismiss_shift_form' => $undismiss_shift_form->createView(),
            'accept_reserved_shift_form' => $accept_reserved_shift_form->createView(),
            'reject_reserved_shift_form' => $reject_reserved_shift_form->createView(),
            'events' => $futur_events
        ]);
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
    
}
