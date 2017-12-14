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
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
 * @Security("has_role('ROLE_ADMIN')")
 */
class AdminController extends Controller
{
    /**
     * Admin panel
     *
     * @Route("/", name="admin")
     * @Method("GET")
     */
    public function indexAction()
    {
        return $this->render('admin/index.html.twig');
    }

    /**
     * Lists all user entities.
     *
     * @Route("/users", name="user_index")
     * @Method({"GET","POST"})
     */
    public function usersAction(Request $request)
    {

//        <th>Role(s)</th>
//        <th>Commission(s)</th>

        $form = $this->createFormBuilder()
            ->add('withdrawn', CheckboxType::class, array('label' => 'fermé','required' => false))
            ->add('enabled', CheckboxType::class, array('label' => 'activé','required' => false))
            ->add('frozen', CheckboxType::class, array('label' => 'gelé','required' => false))
            ->add('membernumber', IntegerType::class, array('label' => '#','required' => false))
            ->add('membernumber', IntegerType::class, array('label' => '#','required' => false))
            ->add('username', TextType::class, array('label' => 'username','required' => false))
            ->add('firstname', TextType::class, array('label' => 'prénom','required' => false))
            ->add('lastname', TextType::class, array('label' => 'nom','required' => false))
            ->add('email', TextType::class, array('label' => 'email','required' => false))
            ->add('email', TextType::class, array('label' => 'email','required' => false))
            ->add('roles',EntityType::class, array(
                'class' => 'AppBundle:Role',
                'choice_label'     => 'name',
                'multiple'     => true,
                'required' => false,
                'label'=>'Role(s)'
            ))
            ->add('commissions',EntityType::class, array(
                'class' => 'AppBundle:Commission',
                'choice_label'     => 'name',
                'multiple'     => true,
                'required' => false,
                'label'=>'Commissions(s)'
            ))
            ->add('submit', SubmitType::class, array('label' => 'OK','attr' => array('class' => 'btn')))
            ->getForm();

        $form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();

        if ($form->isSubmitted() && $form->isValid()) {
            //$session = new Session();

            $qb = $em->getRepository("AppBundle:User")->createQueryBuilder('o')
                ->leftJoin("o.beneficiaries", "b")->addSelect("b")
                ->where('o.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $form->get('withdrawn')->getData());

            if ($form->get('enabled')->getData() > 0){
                $qb = $qb->andWhere('o.enabled = :enabled')
                    ->setParameter('enabled', $form->get('enabled')->getData());
            }
            if ($form->get('frozen')->getData() > 0){
                $qb = $qb->andWhere('o.frozen = :frozen')
                    ->setParameter('frozen', $form->get('frozen')->getData());
            }
            if ($form->get('username')->getData()){
                $qb = $qb->andWhere('o.username LIKE :username')
                    ->setParameter('username', '%'.$form->get('username')->getData().'%');
            }

            if ($form->get('firstname')->getData()){
                $qb = $qb->andWhere('b.firstname LIKE :firstname')
                    ->setParameter('firstname', '%'.$form->get('firstname')->getData().'%');
            }
            if ($form->get('lastname')->getData()){
                $qb = $qb->andWhere('b.lastname LIKE :lastname')
                    ->setParameter('lastname', '%'.$form->get('lastname')->getData().'%');
            }
            if ($form->get('email')->getData()){
                $qb = $qb->andWhere('b.email LIKE :email')
                    ->setParameter('email', '%'.$form->get('email')->getData().'%');
            }
            if ($form->get('roles')->getData() && count($form->get('roles')->getData())){
                $qb = $qb->leftJoin("b.roles", "r")->addSelect("r")
                    ->andWhere('r.id IN (:ids)')
                    ->setParameter('ids',$form->get('roles')->getData() );
            }
            if ($form->get('commissions')->getData() && count($form->get('commissions')->getData())){
                $qb = $qb->leftJoin("b.commissions", "c")->addSelect("c")
                    ->andWhere('c.id IN (:ids)')
                    ->setParameter('ids',$form->get('commissions')->getData() );
            }

            $users = $qb
                ->orderBy('o.member_number', 'ASC')
                ->getQuery()
                ->getResult();

        }else{
            $users = $em->getRepository('AppBundle:User')->findBy(array(), array('member_number' => 'ASC'));
        }

        return $this->render('admin/user/list.html.twig', array(
            'users' => $users,
            'form' => $form->createView(),
            'nb_of_result' => count($users) //todo counting user not beneficiary
        ));
    }

    /**
     * Registrations list
     *
     * @Route("/registrations", name="admin_registrations")
     * @Method("GET")
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

            $owners = $commission->getOwners();
            foreach ($owners as $beneficiary){
                $beneficiary->setOwn($commission);
                $em->persist($beneficiary);
            }

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
                if (!$beneficiary->getUser()->isWithdrawn()){
                    $r = preg_match_all('/(membres\\+[0-9]+@lelefan\\.org)/i', $beneficiary->getEmail(), $matches, PREG_SET_ORDER, 0); //todo put regex in conf
                    if (!count($matches)&&filter_var($beneficiary->getEmail(),FILTER_VALIDATE_EMAIL)) { //was not a temp mail
                        $return .= $beneficiary->getFirstname().$d.$beneficiary->getLastname().$d.$beneficiary->getEmail()."\n";
                    }
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
