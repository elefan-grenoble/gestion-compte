<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Commission;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Role;
use AppBundle\Entity\User;
use AppBundle\Form\BeneficiaryType;
use AppBundle\Form\UserType;
use OAuth2\OAuth2;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("admin")
 */
class AdminController extends Controller
{
    /**
     * Admin panel
     *
     * @Route("/", name="admin")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction()
    {
        return $this->render('admin/index.html.twig');
    }

    /**
     * Registrations list
     *
     * @Route("/registrations", name="admin_registrations")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function registrationsAction(Request $request)
    {
        if (!($page = $request->get('page')))
            $page = 1;
        $limit = 50;
        $max = $this->getDoctrine()->getManager()->createQueryBuilder()->from('AppBundle\Entity\Registration', 'u')
            ->select('count(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $nb_of_pages = intval($max/$limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;
        $registrations = $this->getDoctrine()->getManager()
            ->getRepository('AppBundle:Registration')
            ->findBy(array(),array('date' => 'DESC'),$limit,($page-1)*$limit);
        return $this->render('admin/registrations.html.twig',array('registrations'=>$registrations,'page'=>$page,'nb_of_pages'=>$nb_of_pages));
    }

    /**
     * Comissions list
     *
     * @Route("/commissions", name="admin_commissions")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function commissionsAction(Request $request)
    {
        $commissions = $this->getDoctrine()->getManager()->getRepository('AppBundle:Commission')->findAll();
        return $this->render('admin/commission/list.html.twig',array('commissions'=>$commissions));
    }

    /**
     * Comission new
     *
     * @Route("/commission/new", name="commission_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function commissionNewAction(Request $request)
    {

        $session = new Session();

        $commission = new Commission();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm('AppBundle\Form\CommissionType', $commission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($commission);
            $em->flush();

            $session->getFlashBag()->add('success', 'La nouvelle commission a bien été créée !');

            return $this->redirectToRoute('commission_edit', array('id' => $commission->getId()));

        }

        return $this->render('admin/commission/new.html.twig', array(
            'commission' => $commission,
            'form' => $form->createView(),
        ));
    }

    /**
     * Comission edit
     *
     * @Route("/commission/{id}/edit", name="commission_edit")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function commissionEditAction(Request $request,Commission $commission)
    {
        $session = new Session();

        $form = $this->createForm('AppBundle\Form\CommissionType', $commission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($commission);
            $em->flush();

            $session->getFlashBag()->add('success', 'La commission a bien été éditée !');

            return $this->redirectToRoute('admin_commissions');

        }

        return $this->render('admin/commission/edit.html.twig', array(
            'commission' => $commission,
            'form' => $form->createView(),
        ));
    }

    /**
     * Roles list
     *
     * @Route("/roles", name="admin_roles")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function rolesAction(Request $request)
    {
        $roles = $this->getDoctrine()->getManager()->getRepository('AppBundle:Role')->findAll();
        return $this->render('admin/role/list.html.twig',array('roles'=>$roles));
    }

    /**
     * role new
     *
     * @Route("/role_new", name="role_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function roleNewAction(Request $request)
    {
        $session = new Session();

        $role = new Role();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm('AppBundle\Form\RoleType', $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($role);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le nouveau role a bien été créé !');

            return $this->redirectToRoute('role_edit', array('id' => $role->getId()));

        }

        return $this->render('admin/role/new.html.twig', array(
            'role' => $role,
            'form' => $form->createView(),
        ));
    }

    /**
     * Comission edit
     *
     * @Route("/role/{id}/edit", name="role_edit")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function roleEditAction(Request $request,Role $role)
    {
        $session = new Session();

        $form = $this->createForm('AppBundle\Form\RoleType', $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($role);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le role a bien été édité !');

            return $this->redirectToRoute('admin_roles');

        }

        return $this->render('admin/role/edit.html.twig', array(
            'role' => $role,
            'form' => $form->createView(),
        ));
    }

    /**
     *
     *
     * @Route("/clients", name="admin_clients")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function clientsAction()
    {
        $clients = $this->getDoctrine()->getManager()->getRepository('AppBundle:Client')->findAll();
        return $this->render('admin/clients.html.twig',array('clients'=>$clients));
    }

    /**
     * Add new Client //todo put this auto in service création
     *
     * @Route("/client_new", name="admin_client_new")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newClientAction(Request $request){

        $form = $this->createFormBuilder()
            ->add('url', TextType::class, array('label' => 'url','attr' => array(
                'placeholder' => 'http://www.example.com',
            )))
            ->add('grant_types', ChoiceType::class,array('choices'  => array(
                OAuth2::GRANT_TYPE_AUTH_CODE => OAuth2::GRANT_TYPE_AUTH_CODE,
                OAuth2::GRANT_TYPE_IMPLICIT => OAuth2::GRANT_TYPE_IMPLICIT,
                OAuth2::GRANT_TYPE_USER_CREDENTIALS => OAuth2::GRANT_TYPE_USER_CREDENTIALS,
                OAuth2::GRANT_TYPE_CLIENT_CREDENTIALS => OAuth2::GRANT_TYPE_CLIENT_CREDENTIALS,
                OAuth2::GRANT_TYPE_REFRESH_TOKEN => OAuth2::GRANT_TYPE_REFRESH_TOKEN,
                OAuth2::GRANT_TYPE_EXTENSIONS => OAuth2::GRANT_TYPE_EXTENSIONS),'multiple'=>true))
            ->add('add', SubmitType::class, array('label' => 'Ajouter'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {

            $url = $form->get('url')->getData();

            $clientManager = $this->container->get('fos_oauth_server.client_manager.default');
            $client = $clientManager->createClient();
            $client->setRedirectUris(array($url));
            $client->setAllowedGrantTypes($form->get('grant_types')->getData());
            $clientManager->updateClient($client);

            return $this->redirect($this->generateUrl('fos_oauth_server_authorize', array(
                'client_id' => $client->getPublicId(),
                'redirect_uri' => $url,
                'response_type' => 'code'
            )));
        }
        return $this->render('admin/client_new.html.twig', array(
            'form' => $form->createView()
        ));

    }

    /**
     * export all emails of members (including beneficiary)
     *
     * @Route("/emails_csv", name="admin_emails_csv")
     * @Method({"GET"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function exportEmails(Request $request){
        $beneficiaries = $this->getDoctrine()->getRepository("AppBundle:Beneficiary")->findAll();

        $return = '';
        if($beneficiaries) {

            $d = ','; // this is the default but i like to be explicit
            $e = '"'; // this is the default but i like to be explicit

            foreach($beneficiaries as $beneficiary) {
                $r = preg_match_all('/(membres\\+[0-9]+@lelefan\\.org)/i', $beneficiary->getEmail(), $matches, PREG_SET_ORDER, 0); //todo put regex in conf
                if (!count($matches)&&filter_var($beneficiary->getEmail(),FILTER_VALIDATE_EMAIL)) { //was not a temp mail
                    $return .= $beneficiary->getFirstname().",".$beneficiary->getLastname().",".$beneficiary->getEmail()."\n";
                }

            }
        }
        return new Response($return, 200, array(
            'Content-Encoding: UTF-8',
            'Content-Type' => 'application/force-download; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="emails_'.date('dmyhis').'.csv"'
        ));
    }


    /**
     * Join two user
     *
     * @Route("/join", name="user_join")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function joinAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('from_text', TextType::class, array('label' => 'Adhérent a joindre'))
            ->add('dest_text', TextType::class, array('label' => 'au compte de l\'adhérent'))
            ->add('join', SubmitType::class, array('label' => 'Joindre les deux comptes','attr' => array('class' => 'btn')))
            ->getForm();
        $form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $re = '/#([0-9]+).*/';
            $str = $form->get('from_text')->getData()."\n".$form->get('dest_text')->getData();
            preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);
            if (count($matches)>=2){
                $fromUser = $em->getRepository('AppBundle:User')->findOneBy(array("member_number"=>$matches[0][1]));
                if ($fromUser){
                    $destUser = $em->getRepository('AppBundle:User')->findOneBy(array("member_number"=>$matches[1][1]));
                    if ($destUser){
                        foreach ($fromUser->getBeneficiaries() as $beneficiary){
                            $destUser->addBeneficiary($beneficiary); //in
                            $fromUser->removeBeneficiary($beneficiary); //out
                            $beneficiary->setUser($destUser);
                            $em->persist($beneficiary);
                        }
                        $em->persist($destUser);
                        $em->flush();
                        $fromUser->setMainBeneficiary(null);
                        $em->remove($fromUser);
                        $em->flush();

                        $session->getFlashBag()->add('success', 'Les deux adhérents ont bien été fusionnés');

                        return $this->redirectToRoute('user_edit',array('username'=>$destUser->getUsername()));
                    }else{
                        $session->getFlashBag()->add('error', 'impossible de trouver le compte de destination');
                    }
                }else{
                    $session->getFlashBag()->add('error', 'impossible de trouver le compte à lier');
                }
            }

        }

        $users = $em->getRepository('AppBundle:User')->findAll(); //todo exclude closed
        return $this->render('admin/user/join.html.twig',array('form'=>$form->createView(),'users'=>$users));
    }
}
