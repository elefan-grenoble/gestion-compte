<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Registration;
use AppBundle\Entity\User;
use AppBundle\Form\BeneficiaryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
 * @Route("user")
 */
class UserController extends Controller
{
    /**
     * Lists all user entities.
     *
     * @Route("/", name="user_index")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $users = $em->getRepository('AppBundle:User')->findBy(array(), array('member_number' => 'ASC'));

        return $this->render('user/index.html.twig', array(
            'users' => $users,
        ));
    }

    /**
     * Lists all user entities.
     *
     * @Route("/office_tools", name="user_office_tools")
     * @Method("GET")
     */
    public function officeToolsAction(Request $request)
    {
        return $this->render('user/office_tools.html.twig', array(
            'ip' => $request->getClientIp()
        ));
    }

    /**
     * install admin
     *
     * @Route("/install_admin", name="user_install_admin")
     * @Method("GET")
     */
    public function installAdminAction()
    {
        $em = $this->getDoctrine()->getManager();

        $user = $em->getRepository('AppBundle:User')->findOneBy(array("member_number"=>0));

        if ($user){
            return $this->redirectToRoute('homepage');
        }

        $admin = new User();
        $admin->setEmail("admin@lelefan.org");
        $admin->setPlainPassword("password");
        $admin->setUsername("babar");
        $admin->setMemberNumber(0);
        $admin->setEnabled(true);
        $admin->addRole('ROLE_SUPER_ADMIN');
        $em->persist($admin);
        $em->flush();

        return $this->redirectToRoute('homepage');
    }

    /**
     * Import from CSV
     *
     * @Route("/importcsv", name="user_import_csv")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function csvImportAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('submitFile', FileType::class, array('label' => 'File to Submit'))
            ->add('delimiter', TextType::class, array('label' => 'delimiter','attr' => array(
                'placeholder' => ',',
            ),'data'=>','))
            ->add('persist',CheckboxType::class,array('required'=>false,'label'=>'Sauver en base'))
            ->add('compute', SubmitType::class, array('label' => 'compute'))
        ->getForm();

        if ($form->handleRequest($request)->isValid()) {

            // Get file
            $file = $form->get('submitFile');
            $delimiter = ($form->get('delimiter'))? $form->get('delimiter')->getData() : ',';
            $persist = ($form->get('persist'))? $form->get('persist')->getData() : false;

            // Your csv file here when you hit submit button
            $data = $file->getData();
            $filename = $file->getData()->getPathName();

            $row = 1;
            $lastdate = DateTime::createFromFormat('d/m/Y', '04/05/2016');
            $em = $this->getDoctrine()->getManager();
            $return = array();
            $usernames = array();
            $emails = array();
            if (($handle = fopen($filename, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE /*
                    && $row<10 //*/
                    ) {
                    /*
                     Array
                    (
                    [0] => compare
                    [1] => Date d'adhésion
                    [2] => Type Adhésion
                    [3] => Nom
                    [4] => Prénom
                    [5] => Adresse1
                    [6] => CP
                    [7] => Ville
                    [8] => Téléphone
                    [9] => Mail
                    [10] => Montant
                    [11] => Mode de réglement
                    [12] => A intégrer?
                    [13] => Renouvellement adhésion - Date
                    [14] => Montant
                    [15] => Mode de réglement
                    [16] => Qualité
                    [17] => Bénévole Ressource
                    [18] => Ambassadeur
                    [19] =>
                    )*/
                    preg_match_all('/^[0-9]+$/', $data[0], $matches, PREG_SET_ORDER, 0);
                    if (count($data)>11&&isset($data[3])&&isset($data[4])&&count($matches)&&strlen($data[3])>1&&strlen($data[4])>1){ // on ne traite que les colonnes qui commence par un numéro d'adhérent valide (entier)
                        $member_number = $data[0];
                        $user = $em->getRepository('AppBundle:User')->findOneBy(array("member_number"=>$member_number));
                        if ($user){
                            $mail = $data[9];
                            if (isset($data[9])&&filter_var($mail, FILTER_VALIDATE_EMAIL)&&($user->getEmail() != $mail)) {
                                $user_exist = $em->getRepository('AppBundle:User')->findOneBy(array("email"=>$mail));
                                if (!$user_exist){
                                    $user->setEmail($mail);
                                    if ($persist)
                                        $em->persist($user);
                                    $return[] = array($user,array("error","user with same member number already exist, email updated"));
                                }else{
                                    $return[] = array($user,array("error","user with same member number already exist, email change but already in use"));
                                }
                            }else{
                                $return[] = array($user,array("error","user with same member number already exist"));
                            }
                        } else {
                            $mail = $data[9];
                            $validator = $this->container->get('validator');
                            $constraints = array(
                                new EmailConstraint(),
                                new NotBlank()
                            );
                            $error = $validator->validate($mail, $constraints);
                            if ($error->count()){
                                $return[] = array($user,array("error","email is not valid (".$mail.")"));
                            }else{
                                $user = $em->getRepository('AppBundle:User')->findOneBy(array("email"=>$mail));
                                $already_registred = (isset($emails[$mail])) ? true : false;
                                if ($user||$already_registred)
                                    $return[] = array($user,array("error","user with same email already exist"));
                                else {
                                    $user = new User();
                                    $firstname = trim(preg_replace('/\s\s+/', ' ', $data[4]));
                                    $lastname = trim(preg_replace('/\s\s+/', ' ', $data[3]));
                                    $username = User::makeUsername($firstname,$lastname);
                                    $qb = $em->createQueryBuilder();
                                    $users = $qb->select('u')->from('AppBundle\Entity\User', 'u')
                                        ->where( $qb->expr()->like('u.username', $qb->expr()->literal($username.'%')) )
                                        ->getQuery()
                                        ->getResult();
                                    //$users = $em->getRepository('AppBundle:User')->findBy(array("username"=>$username));
                                    $already_registred = (isset($usernames[$username])) ? $usernames[$username]  : 0;
                                    if (count($users)||$already_registred){
                                        $username = User::makeUsername($firstname,$lastname,count($users)+1+$already_registred);
                                    }
                                    if (strlen($username)>3){
                                        $user->setUsername($username);
                                        $user->setEmail($mail);
                                        $user->setMemberNumber($member_number);
                                        $password = User::randomPassword();
                                        $user->setPassword($password);
                                        //beneficiary
                                        $beneficiary = new Beneficiary();
                                        $beneficiary->setFirstname($firstname);
                                        $beneficiary->setLastname($lastname);
                                        $beneficiary->setPhone($data[8]);
                                        $beneficiary->setEmail($mail);
                                        $beneficiary->setAmbassador(($data[8]!='')&&$data[8]=='1');
                                        $beneficiary->setExpert(false);//default all false
                                        $beneficiary->setUser($user);
                                        $user->setMainBeneficiary($beneficiary);
                                        //address
                                        $address = new Address();
                                        $address->setStreet1($data[5]);
                                        $address->setStreet2('');
                                        $address->setZipcode($data[6]);
                                        $address->setCity($data[7]);
                                        $address->setUser($user);
                                        $user->setAddress($address);
                                        //registration
                                        $registration = new Registration();
                                        $date = $data[1];
                                        if (!$date)
                                            $date = $lastdate;
                                        else {
                                            $date = DateTime::createFromFormat('d/m/Y', $date);
                                            if (!$date)
                                                $date = $lastdate;
                                        }
                                        $lastdate = $date;
                                        $registration->setDate($date); //Y-m-d H:i:s
                                        $registration->setAmount(intval($data[10]));
                                        $reglement = $data[11];
                                        if (!$reglement&&strtolower($data[2])=='site')
                                            $reglement = 'cb';
                                        switch ($reglement){
                                            case 'chq' :
                                            case 'CHQ' :
                                            case 'ch' :
                                                $registration->setMode(Registration::TYPE_CHECK);
                                                break;
                                            case 'EPP':
                                            case 'ESP':
                                            case 'esp':
                                            case 'Espèce':
                                                $registration->setMode(Registration::TYPE_CASH);
                                                break;
                                            case 'Site':
                                            case 'site':
                                            case 'cb':
                                                $registration->setMode(Registration::TYPE_CREDIT_CARD);
                                                break;
                                            default:
                                                $registration->setMode(Registration::TYPE_DEFAULT);
                                        }
                                        $registration->setUser($user);
                                        $user->addRegistration($registration);
                                        $return[] = array($user,array("check","user added"));
                                        $usernames[$user->getUsername()] = (isset($usernames[$user->getUsername()])) ? $usernames[$user->getUsername()] +1 : 1;
                                        $emails[$user->getEmail()] = true;
                                        if ($persist)
                                            $em->persist($user);
                                    }else{
                                        $return[] = array($user,array("error","username build to short"));
                                    }
                                }
                            }
                        }
                    }
                    $row++;
                }
                fclose($handle);
                $em->flush();
            }

            if ($persist){
                $request->getSession()->getFlashBag()->add('notice', 'Le fichier a été traité complétement.');
                return $this->redirectToRoute('user_index');
            }else{
                return $this->render('user/test_import.html.twig', array(
                    'users' => $return,
                ));
            }

        }

        return $this->render('user/import.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Creates a new user entity.
     *
     * @Route("/new", name="user_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $securityContext = $this->container->get('security.authorization_checker');
        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $user = new User();

            $em = $this->getDoctrine()->getManager();

            //todo use the first available, not the bigest plus one
            $users = $em->getRepository('AppBundle:User')->findBy(array(), array('member_number' => 'DESC'));
            $mm = 1;
            if (count($users) && isset($users[0]))
                $mm = $users[0]->getMemberNumber() + 1;
            $user->setMemberNumber($mm);

            $form = $this->createForm('AppBundle\Form\UserType', $user);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $username = User::makeUsername($user->getFirstname(),$user->getLastname());
                $qb = $em->createQueryBuilder();
                $users = $qb->select('u')->from('AppBundle\Entity\User', 'u')
                    ->where( $qb->expr()->like('u.username', $qb->expr()->literal($username.'%')) )
                    ->getQuery()
                    ->getResult();
                $already_registred = (isset($usernames[$username])) ? $usernames[$username]  : 0;
                if (count($users)||$already_registred){
                    $username = User::makeUsername($user->getFirstname(),$user->getLastname(),count($users)+1+$already_registred);
                }
                $user->setUsername($username);
                $password = User::randomPassword();
                $user->setPassword($password);
                $user->getMainBeneficiary()->setUser($user);
                $user->setEmail($user->getMainBeneficiary()->getEmail());

                $em->persist($user);
                $em->flush();

                if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                    return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
                else{
                    $session->set('token_key',uniqid());
                    return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
                }
            }

            return $this->render('user/new.html.twig', array(
                'user' => $user,
                'form' => $form->createView(),
            ));
        }else{
            return $this->redirectToRoute('fos_user_security_login');
        }
    }

    /**
     * Displays a form to edit an existing user entity.
     *
     * @Route("/edit/firewall", name="user_edit_firewall")
     * @Method({"GET", "POST"})
     */
    public function editFirewallAction(Request $request)
    {
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $username = $request->request->get('username');
        if ($username){
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository('AppBundle:User')->findOneBy(array('username'=>$username));
            $form = $this->createFormBuilder()
                ->add('member_number', TextType::class, array('label' => 'Numéro d\'adhérent','disabled' => true,'attr' => array( 'value'=>$user->getMemberNumber())))
                ->add('username', HiddenType::class, array('attr' => array( 'value'=>$user->getUsername())))
                ->add('email', EmailType::class, array('label' => 'Courriel complet','attr' => array('placeholder' => $user->getAnonymousEmail())))
                ->add('edit', SubmitType::class, array('label' => 'Editer la fiche de '.$user->getFirstname(),'attr' => array('class' => 'btn')))
                ->getForm();
        }else{
            $form = $this->createFormBuilder()
                ->add('member_number', TextType::class, array('label' => 'Numéro d\'adhérent'))
                ->add('username', HiddenType::class, array('attr' => array( 'value'=>'')))
                ->add('email', EmailType::class, array('label' => 'email'))
                ->add('edit', SubmitType::class, array('label' => 'Editer','attr' => array('class' => 'btn')))
                ->getForm();
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $member_number = $form->get('member_number')->getData();
            $username = $form->get('username')->getData();
            $email = $form->get('email')->getData();

            $em = $this->getDoctrine()->getManager();
            $user = null;
            if ($username)
                $user = $em->getRepository('AppBundle:User')->findOneBy(array('username'=>$username));
            else if($member_number)
                $user = $em->getRepository('AppBundle:User')->findOneBy(array('member_number'=>$member_number));
            if ($user&&$email&&($user->getEmail()==$email)){
                $session = new Session();
                $session->set('token_key',uniqid());
                return $this->redirectToRoute('user_edit',array(
                    'username'=>$user->getUsername(),
                    'token'=>$user->getTmpToken($session->get('token_key').$this->get('security.token_storage')
                            ->getToken()->getUser()->getUsername())));
            }
            $session = new Session();
            $session->getFlashBag()->add('error', 'cet email n\'est pas associé à ce numéro');
        }

        return $this->render('user/edit_firewall.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing user entity.
     *
     * @Route("/{username}/edit", name="user_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, User $user)
    {
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $session = new Session();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN') && ( !$session->get('token_key') ||
            ($request->query->get('token') != $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())) ) ) {
            throw $this->createAccessDeniedException();
        }

        $editForm = $this->createForm('AppBundle\Form\UserType', $user);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $email = $editForm->get('mainBeneficiary')->get('email')->getData();
            $otherUser = $em->getRepository('AppBundle:User')->findBy(array("email"=>$email));
            $otherBeneficiary = $em->getRepository('AppBundle:Beneficiary')->findBy(array("email"=>$email));
            if ($email != $user->getEmail()){
                if (!$otherBeneficiary && !$otherUser){
                    $user->setEmail($email);
                    $em->persist($user);
                    $session->getFlashBag()->add('warning', 'l\'email principal a changé');
                }else{
                    $session->getFlashBag()->add('error', 'cet email est déjà utilisé');
                }
            }
            $phone = $editForm->get('mainBeneficiary')->get('phone')->getData();
            if ($phone){
                $em->flush();
                $session->getFlashBag()->add('success', 'Mise à jour effectuée');
            }else{
                $session->getFlashBag()->add('error', 'Le numéro de téléphone est demandé');
            }

            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
            else
                return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
        }

        $beneficiary = new Beneficiary();
        $beneficiaryForm = $this->createForm('AppBundle\Form\BeneficiaryType',$beneficiary);
        $beneficiaryForm->handleRequest($request);
        if ($beneficiaryForm->isSubmitted() && $beneficiaryForm->isValid()) {

            if (count($user->getBeneficiaries())<4){ //todo put this in conf

                $beneficiary->setUser($user);
                $user->addBeneficiary($beneficiary);

                $em = $this->getDoctrine()->getManager();
                $otherUser = $em->getRepository('AppBundle:User')->findBy(array("email"=>$beneficiary->getEmail()));
                $otherBeneficiary = $em->getRepository('AppBundle:Beneficiary')->findBy(array("email"=>$beneficiary->getEmail()));
                if (!$otherUser && !$otherBeneficiary){
                    $em->persist($beneficiary);
                    $em->flush();

                    $session->getFlashBag()->add('success', 'Beneficiaire ajouté');
                }else{
                    $session->getFlashBag()->add('error', 'Cet email est déjà utilisé');
                }
            }else{
                $session->getFlashBag()->add('error', 'Maximum '.(5-1).' beneficiaires enregistrés'); //todo put this in conf
            }

            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
            else
                return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
        }

        $newReg = new Registration();
        $newReg->setDate(new DateTime('now'));
        $newReg->setRegistrar($current_app_user);
        $registrationForm = $this->createForm('AppBundle\Form\RegistrationType', $newReg);
        $registrationForm->add('is_new',HiddenType::class,array('attr'=>array('value'=>'1')));
        $registrationForm->handleRequest($request);
        if ($registrationForm->isSubmitted() && $registrationForm->isValid() && $registrationForm->get('is_new')->getData() != null) {
            $amount = floatval($registrationForm->get('amount')->getData());
            if ($amount<=0){
                $session->getFlashBag()->add('error', 'Adhésion prix libre & non gratuit !');
                if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                    return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
                else
                    return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));

            }

            if ($current_app_user->getId()==$user->getId()){
                $session->getFlashBag()->add('error', 'Tu ne peux pas enregistrer ta propre réadhésion, demande à un autre adhérent :)');
                if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                    return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
                else
                    return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));

            }
            $newReg->setRegistrar($current_app_user);

            $date  =$registrationForm->get('date')->getData();
            $r = $user->getLastRegistration();
            if ($r){
                $Y = $r->getDate()->format('Y');
                if ($Y == $date->format('Y')){
                    $session->getFlashBag()->add('warning', 'l\'adhésion précédente du '.$r->getDate()->format('d F Y').' est encore valable !');
                    if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                        return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
                    else
                        return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));

                }
            }
            $newReg->setUser($user);

            $em = $this->getDoctrine()->getManager();
            $em->persist($newReg);
            $em->flush();

            $session->getFlashBag()->add('success', 'Enregistrement effectuée');
            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
            else
                return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
        }

        $registrationForms = array();
        foreach ($user->getRegistrations() as $registration){
            $form = $this->createForm('AppBundle\Form\RegistrationType', $registration);
            $registrationForms[$registration->getId()] = $form->createView();
        }

        $id = $request->request->get("registration_id");
        if ($id){
            $em = $this->getDoctrine()->getManager();
            $registration= $em->getRepository('AppBundle:Registration')->find($id);
            if ($registration){
                $form = $this->createForm('AppBundle\Form\RegistrationType', $registration);
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    if ($current_app_user->getId()==$user->getId()){
                        $session->getFlashBag()->add('error', 'Tu ne peux pas modifier tes propres adhésions :)');
                        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                            return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
                        else
                            return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
                    }
                    $em->persist($registration);
                    $em->flush();
                    $session->getFlashBag()->add('success', 'Mise à jour effectuée');
                    if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                        return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
                    else
                        return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
                }
            }
        }


        $deleteBeneficiaryForms = array();
        foreach ($user->getBeneficiaries() as $beneficiary){
            $deleteBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                ->setAction($this->generateUrl('user_edit_beneficiary_delete', array('username' => $beneficiary->getUser()->getUsername(),'id' => $beneficiary->getId())))
                ->setMethod('DELETE')
                ->getForm()->createView();
        }

        return $this->render('user/edit.html.twig', array(
            'user' => $user,
            'edit_form' => $editForm->createView(),
            'new_registration_form' => $registrationForm->createView(),
            'new_beneficiary_form' => $beneficiaryForm->createView(),
            'delete_beneficiary_forms' => $deleteBeneficiaryForms,
            'registration_forms' => $registrationForms
        ));
    }

    /**
     * Displays a form to edit an existing user entity.
     *
     * @Route("/beneficiary/{id}", name="user_edit_beneficiary_edit")
     * @Method({"GET", "POST"})
     */
    public function editBeneficiaryAction(Request $request, Beneficiary $beneficiary)
    {
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $session = new Session();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        //todo protect it from direct access
//        if (!$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN') && ( !$session->get('token_key') ||
//                ($request->query->get('token') != $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())) ) ) {
//            throw $this->createAccessDeniedException();
//        }

        $editForm = $this->createForm('AppBundle\Form\BeneficiaryType', $beneficiary);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $otherUser = $em->getRepository('AppBundle:User')->findOneBy(array("email"=>$beneficiary->getEmail()));
            $otherBeneficiary = $em->getRepository('AppBundle:Beneficiary')->findOneBy(array("email"=>$beneficiary->getEmail()));
            if ((!$otherUser && !$otherBeneficiary) || ($otherBeneficiary->getId() == $beneficiary->getId())){
                $em->flush();
                $session->getFlashBag()->add('success', 'Mise à jour effectuée');
            }else{
                $session->getFlashBag()->add('error', 'Cet email est déjà utilisé');
            }

            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                return $this->redirectToRoute('user_edit', array('username' => $beneficiary->getUser()->getUsername()));
            else
                return $this->redirectToRoute('user_edit', array(
                    'username' => $beneficiary->getUser()->getUsername(),
                    'token' => $beneficiary->getUser()->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
        }

        return $this->render('user/edit_beneficiary.html.twig', array(
            'user' => $beneficiary->getUser(),
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Deletes a beneficiary entity.
     *
     * @Route("/beneficiary/{id}", name="user_edit_beneficiary_delete")
     * @Method("DELETE")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deleteBeneficiaryAction(Request $request, Beneficiary $beneficiary)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('user_edit_beneficiary_delete', array('username' => $beneficiary->getUser()->getUsername(),'id' => $beneficiary->getId())))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($beneficiary);
            $em->flush();
        }

        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('user_edit', array('username' => $beneficiary->getUser()->getUsername()));
        else
            return $this->redirectToRoute('user_edit', array('username' => $beneficiary->getUser()->getUsername(),'token' => $beneficiary->getUser()->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
    }

    /**
     * Finds and displays a user entity.
     *
     * @Route("/{username}", name="user_show")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function showAction(User $user)
    {
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $deleteForm = $this->createDeleteForm($user);

        return $this->render('user/show.html.twig', array(
            'user' => $user,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a user entity.
     *
     * @Route("/{username}", name="user_delete")
     * @Method("DELETE")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deleteAction(Request $request, User $user)
    {
        $form = $this->createDeleteForm($user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($user);
            $em->flush();
        }

        return $this->redirectToRoute('user_index');
    }

    /**
     * Creates a form to delete a user entity.
     *
     * @param User $user The user entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(User $user)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('user_delete', array('id' => $user->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
