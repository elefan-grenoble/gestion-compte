<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
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
            return $this->redirectToRoute('confirm',array('member_number'=>$user->getMemberNumber()));
        }
        return $this->render('user/find_me.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * @Route("/{member_number}/confirm", name="confirm")
     */
    public function confirmAction(User $user,Request $request){

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
                ->where( $qb->expr()->like('b.firstname', $qb->expr()->literal('%'.$firstname.'%')) )
                ->join('b.user', 'u')
                ->orderBy("u.member_number", 'ASC')
                ->getQuery()
                ->getResult();
            return $this->render('user/find_user_number.html.twig', array(
                'form' => null,
                'beneficiaries' => $beneficiaries
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
