<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Client;
use AppBundle\Entity\Note;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use AppBundle\Form\BeneficiaryType;
use AppBundle\Form\NoteType;
use AppBundle\Form\UserType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
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
     * @Route("/office_tools", name="user_office_tools")
     * @Method({"GET","POST"})
     */
    public function officeToolsAction(Request $request)
    {
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $this->denyAccessUnlessGranted('access_tools',$current_app_user);
        $note = new Note();
        $note->setAuthor($current_app_user);
        $note_form = $this->createForm('AppBundle\Form\NoteType', $note);
        $note_form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();

        if ($note_form->isSubmitted() && $note_form->isValid()) {
            $existing_note = $em->getRepository('AppBundle:Note')->findOneBy(array("subject"=>null,"author"=>$current_app_user,"text"=>$note->getText()));
            $session = new Session();
            if ($existing_note){
                $session->getFlashBag()->add('error','Ce post-it existe déjà');
            }else{
                $em->persist($note);
                $em->flush();
                $session->getFlashBag()->add('success','Post-it ajouté');
            }
        }

        $notes = $em->getRepository('AppBundle:Note')->findBy(array("subject"=>null));
        $notes_form = array();
        $notes_delete_form = array();
        $new_notes_form = array();
        foreach ($notes as $n){
            $notes_form[$n->getId()] = $this->createForm('AppBundle\Form\NoteType', $n,array('action'=>$this->generateUrl('note_edit', array('id' => $n->getId()))))->createView();
            $notes_delete_form[$n->getId()] = $this->createNoteDeleteForm($n)->createView();

            $response_note = clone $note;
            $response_note->setParent($n);
            $response_note_form = $this->createForm(NoteType::class, $response_note,
                array('action' => $this->generateUrl('note_reply', array('id' => $n->getId()))));

            $new_notes_form[$n->getId()] = $response_note_form->createView();
        }
        return $this->render('default/tools/office_tools.html.twig', array(
            'note_form' => $note_form->createView(),
            'notes_form' => $notes_form,
            'notes_delete_form' => $notes_delete_form,
            'new_notes_form' => $new_notes_form,
            'notes' => $notes
        ));
    }

    /**
     * reply to a note
     *
     * @Route("/note/{id}/reply", name="note_reply")
     * @Method({"POST"})
     */
    public function noteReplyAction(Request $request, Note $note)
    {
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $this->denyAccessUnlessGranted('access_tools',$current_app_user);

        $new_note = new Note();
        $new_note->setParent($note);
        $new_note->setAuthor($current_app_user);
        $new_note->setCreatedAt(new \DateTime());
        $new_note->setSubject($note->getSubject());

        $note_form = $this->createForm('AppBundle\Form\NoteType', $new_note);
        $note_form->handleRequest($request);

        if ($note_form->isSubmitted() && $note_form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();
            $em->persist($new_note);
            $em->flush();
            if ($new_note->getSubject()){
                $session->getFlashBag()->add('success','réponse enregistrée');
                return $this->redirectToShow($note->getSubject(),$session,$current_app_user);
            }
            $session->getFlashBag()->add('success','Post-it réponse enregistré');
        }
        return $this->redirectToRoute('user_office_tools');
    }

    /**
     * edit a note
     *
     * @Route("/note/{id}/edit", name="note_edit")
     * @Method({"GET","POST"})
     */
    public function noteEditAction(Request $request, Note $note)
    {
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $this->denyAccessUnlessGranted('edit', $note);

        $note_form = $this->createForm('AppBundle\Form\NoteType', $note);
        $note_form->handleRequest($request);

        if ($note_form->isSubmitted() && $note_form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();
            $em->persist($note);
            $em->flush();
            if ($note->getSubject()){
                $session->getFlashBag()->add('success','note éditée');
                return $this->redirectToShow($note->getSubject(),$session,$current_app_user);
            }
            $session->getFlashBag()->add('success','Post-it édité');
        }
        return $this->redirectToRoute('user_office_tools');
    }

    /**
     * Creates a form to delete a note entity.
     *
     * @param Note $note the note entity
     *
     * @return \Symfony\Component\Form\FormInterface The form
     */
    private function createNoteDeleteForm(Note $note)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('note_delete', array('id' => $note->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Deletes a user entity.
     *
     * @Route("/note/{id}", name="note_delete")
     * @Method("DELETE")
     */
    public function deleteNoteAction(Request $request, Note $note)
    {
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $this->denyAccessUnlessGranted('delete', $note);

        $form = $this->createNoteDeleteForm($note);
        $form->handleRequest($request);
        $session = new Session();

        $user = $note->getSubject();

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($note);
            $em->flush();
            $session->getFlashBag()->add('success',"la note a bien été supprimée");
        }

        if ($user){
            return $this->redirectToShow($user,$session,$current_app_user);
        }
        return $this->redirectToRoute('user_office_tools');
    }


    /**
     * install admin
     *
     * @Route("/install_admin", name="user_install_admin")
     * @Method({"GET","POST"})
     */
    public function installAdminAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $session = new Session();
        $user = $em->getRepository('AppBundle:User')->findOneBy(array("member_number"=>0));

        if ($user){ //main super admin exist
            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $form = $this->createFormBuilder()
                    ->add('username',TextType::class,array('label'=>"Nom d'utilisateur"))
                    ->add('password',PasswordType::class,array('label'=>"Mot de passe"))
                    ->add('email',EmailType::class,array('label'=>"Adresse email", "required" => false ))
                    ->getForm();
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()){

                    $existing_user = $em->getRepository('AppBundle:User')->findOneBy(array("username"=>$form->get('username')->getData()));
                    if ($existing_user){
                        $session->getFlashBag()->add('error','A user with this username already exist !');
                        return $this->render('admin/user/new_admin.html.twig', array(
                            'form' => $form->createView(),
                        ));
                    }
                    $existing_user = $em->getRepository('AppBundle:User')->findOneBy(array("email"=>$form->get('email')->getData()));
                    if ($existing_user){
                        $session->getFlashBag()->add('error','A user with this email already exist !');
                        $session->getFlashBag()->add('warning','Put an empty email, we will provide one for your');
                        return $this->render('admin/user/new_admin.html.twig', array(
                            'form' => $form->createView(),
                        ));
                    }
                    $last_admin_recorded = $em->getRepository('AppBundle:User')->findBy(array(),array('member_number'=>'ASC'),1);
                    $lowest_member_number_yet = $last_admin_recorded[0]->getMemberNumber();

                    $email = $form->get('email')->getData();
                    if (!$form->get('email')->getData()) {
                        $email = "membres+admin".($lowest_member_number_yet-1)."@lelefan.org";//todo put this in conf
                    }

                    $new_admin = new User();
                    $new_admin->setEmail($email);
                    $new_admin->setPlainPassword($form->get('password')->getData());
                    $new_admin->setUsername($form->get('username')->getData());
                    $new_admin->setMemberNumber($lowest_member_number_yet-1);
                    $new_admin->setEnabled(true);
                    $new_admin->addRole('ROLE_ADMIN');
                    $em->persist($new_admin);
                    $em->flush();

                    $session->getFlashBag()->add('success','new user admin created with success !');
                    return $this->redirectToRoute('admin');
                }else{
                    return $this->render('admin/user/new_admin.html.twig', array(
                        'form' => $form->createView(),
                    ));
                }
            }else{
                $session->getFlashBag()->add('error','Main super admin user already exist !');
                return $this->redirectToRoute('homepage');
            }
        }else{ //main super user not created yet
            $admin = new User();
            $admin->setEmail("admin@lelefan.org"); //todo put this in conf
            $admin->setPlainPassword("password");
            $admin->setUsername("babar"); //todo put this in conf
            $admin->setMemberNumber(0);
            $admin->setEnabled(true);
            $admin->addRole('ROLE_SUPER_ADMIN');
            $em->persist($admin);
            $em->flush();

            $session->getFlashBag()->add('success','user super admin created with success !');

            return $this->redirectToRoute('homepage');
        }

    }

    /**
     * Creates a new user entity.
     *
     * @Route("/new", name="user_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request, \Swift_Mailer $mailer)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $this->denyAccessUnlessGranted('create', $current_app_user);
        $user = new User();

        $em = $this->getDoctrine()->getManager();

        //todo use the first available, not the bigest plus one
        $users = $em->getRepository('AppBundle:User')->findBy(array(), array('member_number' => 'DESC'));
        $mm = 1;
        if (count($users) && isset($users[0]))
            $mm = $users[0]->getMemberNumber() + 1;
        $user->setMemberNumber($mm);

        $registration = new Registration();
        $registration->setDate(new DateTime('now'));
        $registration->setUser($user);
        $registration->setRegistrar($current_app_user);
        $user->addRegistration($registration);

        $form = $this->createForm('AppBundle\Form\UserType', $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $user->getMainBeneficiary()->getEmail();
            if (!filter_var($email,FILTER_SANITIZE_EMAIL)||!filter_var($email,FILTER_VALIDATE_EMAIL)){
                $session->getFlashBag()->add('error', 'cet adresse email n\'est pas valide');
            }else{
                $other_user = $em->getRepository('AppBundle:User')->findOneBy(array("email"=>$email));
                if ($other_user){
                    $session->getFlashBag()->add('error', 'Oups, un membres utilise déjà cet email ! ('.'#'.$other_user->getMemberNumber()." ".$other_user->getFirstName()." ".$other_user->getLastName()[0].')');
                }else{
                    $beneficiary = $em->getRepository('AppBundle:Beneficiary')->findOneBy(array("email"=>$email));
                    if ($beneficiary){
                        $session->getFlashBag()->add('error', 'Oups, un beneficiaire est déjà enregistré avec cet email !('.'#'.$beneficiary->getUser()->getMemberNumber()." ".$beneficiary->getUser()->getFirstName()." ".$beneficiary->getUser()->getLastName()[0].')');
                    }else{
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

                        if (!$user->getLastRegistration()->getRegistrar())
                            $user->getLastRegistration()->setRegistrar($current_app_user);

                        $em->persist($user);
                        $em->flush();

                        $session->getFlashBag()->add('success', 'La nouvelle adhésion a bien été prise en compte !');

                        $welcome = (new \Swift_Message('Bienvenu à l\'éléfàn'))
                            ->setFrom('membres@lelefan.org')
                            ->setTo($user->getEmail())
                            ->setBody(
                                $this->renderView(
                                    'emails/welcome.html.twig',
                                    array('user' => $user)
                                ),
                                'text/html'
                            );
                        $mailer->send($welcome);

                        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                            return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
                        else{
                            $session->set('token_key',uniqid());
                            return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
                        }
                    }
                }
            }
        }elseif ($form->isSubmitted()){
            foreach ($this->getErrorMessages($form) as $key => $errors){
                foreach ($errors as $error)
                    $session->getFlashBag()->add('error', $key." : ".$error);
            }
        }

        return $this->render('user/new.html.twig', array(
            'user' => $user,
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing user entity.
     *
     * @Route("/edit/firewall", name="user_edit_firewall")
     * @Method({"GET", "POST"})
     */
    public function editFirewallAction(Request $request)
    {
        $session = new Session();
        $username = $request->request->get('username');
        if ($username){
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository('AppBundle:User')->findOneBy(array('username'=>$username));
            if ($this->isGranted('view', $user)){
                return $this->redirectToRoute('user_edit',array(
                    'username'=>$user->getUsername(),
                    'token'=>$user->getTmpToken($session->get('token_key').$this->get('security.token_storage')
                            ->getToken()->getUser()->getUsername())));
            }
            $form = $this->createFormBuilder()
                ->add('member_number', TextType::class, array('label' => 'Numéro d\'adhérent','disabled' => true,'attr' => array( 'value'=>$user->getMemberNumber())))
                ->add('username', HiddenType::class, array('attr' => array( 'value'=>$user->getUsername())))
                ->add('email', EmailType::class, array('label' => 'Courriel complet','attr' => array('placeholder' => $user->getAnonymousEmail())))
                ->add('edit', SubmitType::class, array('label' => 'Editer la fiche de '.$user->getFirstname(),'attr' => array('class' => 'btn')))
                ->getForm();
        }else{
            if ($this->isGranted('view', new User())){
                $form = $this->createFormBuilder()
                    ->add('member_number', TextType::class, array('label' => 'Numéro d\'adhérent'))
                    ->add('username', HiddenType::class, array('attr' => array( 'value'=>'')))
                    ->add('email', HiddenType::class, array('label' => 'email'))
                    ->add('edit', SubmitType::class, array('label' => 'Editer','attr' => array('class' => 'btn')))
                    ->getForm();
            }else{
                $form = $this->createFormBuilder()
                    ->add('member_number', TextType::class, array('label' => 'Numéro d\'adhérent'))
                    ->add('username', HiddenType::class, array('attr' => array( 'value'=>'')))
                    ->add('email', EmailType::class, array('label' => 'email'))
                    ->add('edit', SubmitType::class, array('label' => 'Editer','attr' => array('class' => 'btn')))
                    ->getForm();
            }

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

            if ($user&&($this->isGranted('view',$user) || ($email&&($user->getEmail()==$email)))){
                $session->set('token_key',uniqid());
                return $this->redirectToRoute('user_edit',array(
                    'username'=>$user->getUsername(),
                    'token'=>$user->getTmpToken($session->get('token_key').$this->get('security.token_storage')
                            ->getToken()->getUser()->getUsername())));
            }
            if ($email)
                $session->getFlashBag()->add('error', 'cet email n\'est pas associé à ce numéro');
            if (!$user)
                $session->getFlashBag()->add('error', 'membre non trouvé');
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
        $this->denyAccessUnlessGranted('edit', $user);

        $re = '/(membres\+.*[0-9]+@lelefan\.org)/i';
        $email = $user->getEmail();
        preg_match($re, $email, $matches, PREG_OFFSET_CAPTURE, 0);
        if (count($matches)){
            $session->getFlashBag()->add('warning',
                'Oups, on ne connait pas l\'adresse courriel de ce membre. A toi de jouer pour le renseigner !');
            if ($user->getMainBeneficiary())
                $user->getMainBeneficiary()->setEmail('');
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
            $em->flush();
            $session->getFlashBag()->add('success', 'Mise à jour effectuée');

            return $this->redirectToEdit($user,$session,$current_app_user);
        }

        $note = new Note();
        $noteForm = $this->createForm('AppBundle\Form\NoteType',$note);

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
            return $this->redirectToEdit($user,$session,$current_app_user);
        }elseif ($beneficiaryForm->isSubmitted()){
            foreach ($this->getErrorMessages($beneficiaryForm) as $key => $errors){
                foreach ($errors as $error)
                    $session->getFlashBag()->add('error', $key." : ".$error);
            }
        }

        $newReg = new Registration();
        $remainder = $user->getRemainder();
        if ( ! $remainder->invert ){ //still some days
            $date = clone $user->getLastRegistration()->getDate();
            $newReg->setDate($date->add(\DateInterval::createFromDateString('1 year')));
        }
        else { //register now !
            $newReg->setDate(new DateTime('now'));
        }
        $newReg->setRegistrar($current_app_user);
        $registrationForm = $this->createForm('AppBundle\Form\RegistrationType', $newReg);
        $registrationForm->add('is_new',HiddenType::class,array('attr'=>array('value'=>'1')));
        $registrationForm->handleRequest($request);
        if ($registrationForm->isSubmitted() && $registrationForm->isValid() && $registrationForm->get('is_new')->getData() != null) {
            $amount = floatval($registrationForm->get('amount')->getData());
            if ($amount<=0){
                $session->getFlashBag()->add('error', 'Adhésion prix libre & non gratuit !');
                return $this->redirectToEdit($user,$session,$current_app_user);
            }

            if ($current_app_user->getId()==$user->getId()){
                $session->getFlashBag()->add('error', 'Tu ne peux pas enregistrer ta propre réadhésion, demande à un autre adhérent :)');
                return $this->redirectToEdit($user,$session,$current_app_user);
            }
            $newReg->setRegistrar($current_app_user);

            $date  = $registrationForm->get('date')->getData();
            if (!$user->canRegister($date)){
                $session->getFlashBag()->add('warning', 'l\'adhésion précédente du est encore valable à cette date !');
                return $this->redirectToEdit($user,$session,$current_app_user);
            }
            $newReg->setUser($user);
            $user->addRegistration($newReg);

            $em = $this->getDoctrine()->getManager();
            $em->persist($newReg);
            $em->flush();

            $session->getFlashBag()->add('success', 'Enregistrement effectuée');
            return $this->redirectToEdit($user,$session,$current_app_user);
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
                    return $this->redirectToEdit($user,$session,$current_app_user);
                }
            }
        }

        $deleteBeneficiaryForms = array();
        foreach ($user->getBeneficiaries() as $beneficiary){
            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                $deleteBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                    ->setAction($this->generateUrl('user_edit_beneficiary_delete', array('username' => $beneficiary->getUser()->getUsername(),'id' => $beneficiary->getId())))
                    ->setMethod('DELETE')->getForm()->createView();
            else
                $deleteBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                    ->setAction($this->generateUrl('user_edit_beneficiary_delete', array(
                        'username' => $beneficiary->getUser()->getUsername(),
                        'id' => $beneficiary->getId(),
                        'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())
                    )))
                    ->setMethod('DELETE')->getForm()->createView();
        }

        if ($user->isWithdrawn())
            $session->getFlashBag()->add('warning', 'Ce compte est fermé');

        return $this->render('user/edit.html.twig', array(
            'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername()),
            'user' => $user,
            'edit_form' => $editForm->createView(),
            'new_registration_form' => $registrationForm->createView(),
            'new_beneficiary_form' => $beneficiaryForm->createView(),
            'new_note_form' => $noteForm->createView(),
            'delete_beneficiary_forms' => $deleteBeneficiaryForms,
            'registration_forms' => $registrationForms
        ));
    }

    private function redirectToEdit($user,$session,$current_app_user)
    {
        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('user_edit', array('username' => $user->getUsername()));
        else
            return $this->redirectToRoute('user_edit', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
    }
    private function redirectToShow($user,$session,$current_app_user)
    {
        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('user_show', array('username' => $user->getUsername()));
        else
            return $this->redirectToRoute('user_show', array('username' => $user->getUsername(),'token' => $user->getTmpToken($session->get('token_key').$current_app_user->getUsername())));
    }

    /**
     * close user
     *
     * @Route("/close/{id}", name="user_close")
     * @Method({"GET"})
     */
    public function closeAction(User $user){
        $this->denyAccessUnlessGranted('close',$user);
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        $user->setWithdrawn(true);
        $em->persist($user);
        $em->flush();
        $session->getFlashBag()->add('success', 'Compte fermé');
        return $this->redirectToShow($user,$session,$current_app_user);
    }

    /**
     * open user
     *
     * @Route("/open/{id}", name="user_open")
     * @Method({"GET"})
     */
    public function openAction(User $user){
        $this->denyAccessUnlessGranted('close',$user);
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        $user->setWithdrawn(false);
        $em->persist($user);
        $em->flush();
        $session->getFlashBag()->add('success', 'Compte fermé');
        return $this->redirectToShow($user,$session,$current_app_user);
    }

    /**
     * close user
     *
     * @Route("/freeze/{id}", name="user_freeze")
     * @Method({"GET"})
     */
    public function freezeAction(User $user){
        $this->denyAccessUnlessGranted('freeze',$user);
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        $user->setFrozen(true);
        $em->persist($user);
        $em->flush();
        $session->getFlashBag()->add('success', 'Compte gelé');
        return $this->redirectToShow($user,$session,$current_app_user);
    }

    /**
     * close user
     *
     * @Route("/unfreeze/{id}", name="user_unfreeze")
     * @Method({"GET"})
     */
    public function unfreezeAction(User $user){
        $this->denyAccessUnlessGranted('freeze',$user);
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        $user->setFrozen(false);
        $em->persist($user);
        $em->flush();
        $session->getFlashBag()->add('success', 'Compte dégelé');
        return $this->redirectToShow($user,$session,$current_app_user);
    }

    /**
     * remove role of user
     *
     * @Route("/removeRole/{id}/{role}", name="user_remove_role")
     * @Method({"GET"})
     */
    public function removeRoleAction(User $user,$role){
        $this->denyAccessUnlessGranted('role_remove',$user);
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        if (!$user->hasRole($role)) {
            $session->getFlashBag()->add('success', 'Cet utilisateur ne possède pas le role '.$role);
            return $this->redirectToShow($user,$session,$current_app_user);
        }
        $user->removeRole($role);
        $em->persist($user);
        $em->flush();
        $session->getFlashBag()->add('success', 'Le Role '.$role.' a bien été retiré');
        return $this->redirectToShow($user,$session,$current_app_user);
    }

    /**
     * add role of user
     *
     * @Route("/addRole/{id}/{role}", name="user_add_role")
     * @Method({"GET"})
     */
    public function addRoleAction(User $user,$role){
        $this->denyAccessUnlessGranted('role_add',$user);
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        if ($user->hasRole($role)) {
            $session->getFlashBag()->add('success', 'Cet utilisateur possède déjà le role '.$role);
            return $this->redirectToShow($user,$session,$current_app_user);
        }
        $user->addRole($role);
        $em->persist($user);
        $em->flush();
        $session->getFlashBag()->add('success', 'Le Role '.$role.' a bien été ajouté');
        return $this->redirectToShow($user,$session,$current_app_user);
    }


    /**
     * self_register
     *
     * @Route("/self_register", name="user_self_register")
     * @Method({"GET"})
     */
    public function selfRegistrationAction(){
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        if (!$current_app_user->canRegister()) {
            $session->getFlashBag()->add('warning', 'Pas besoin de réadhérer pour le moment :)');
            return $this->redirectToRoute('homepage');
        }
        return $this->render('user/self_register.html.twig');
    }

    /**
     * remove client from user
     *
     * @Route("/{username}/remove_client/{client_id}", name="user_client_remove")
     * @Method({"GET", "POST"})
     */
    public function removeClientUserAction(User $user,$client_id){
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $session = new Session();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')&&($current_app_user != $user)) {
            throw $this->createAccessDeniedException();
        }
        if ($client_id){
            $client = $this->getDoctrine()->getManager()->getRepository('AppBundle:Client')->find($client_id);
            if ($client->getId()){
                if ($user->getClients()->contains($client)){
                    $user->removeClient($client);
                    $this->getDoctrine()->getManager()->flush($user);
                    $session->getFlashBag()->add('success','Le service a bien été supprimé de votre compte');
                }else{
                    $session->getFlashBag()->add('error','ce client n\'est pas associé à votre compte');
                }
            }else{
                $session->getFlashBag()->add('error','ce client n\'existe pas');
            }
        }else{
            $session->getFlashBag()->add('error','ce client n\'existe pas');
        }

        return $this->redirectToRoute('fos_user_profile_edit');
    }

    /**
     * Displays a form to edit an existing user entity.
     *
     * @Route("/beneficiary/{id}/edit", name="user_edit_beneficiary_edit")
     * @Method({"GET", "POST"})
     */
    public function editBeneficiaryAction(Request $request, Beneficiary $beneficiary)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $this->denyAccessUnlessGranted('edit', $beneficiary->getUser());

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
     */
    public function deleteBeneficiaryAction(Request $request, Beneficiary $beneficiary)
    {
        $session = new Session();

        $this->denyAccessUnlessGranted('edit', $beneficiary->getUser());

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
     */
    public function showAction(User $user)
    {
        $this->denyAccessUnlessGranted('view', $user);

        $deleteForm = $this->createDeleteForm($user);

        $note = new Note();
        $note_form = $this->createForm('AppBundle\Form\NoteType', $note,array(
            'action' => $this->generateUrl('ambassador_new_note',array("username"=>$user->getUsername())),
            'method' => 'POST',
        ));
        $notes_form = array();
        $notes_delete_form = array();
        $new_notes_form = array();
        foreach ($user->getNotes() as $n){
            $notes_form[$n->getId()] = $this->createForm('AppBundle\Form\NoteType', $n,array('action'=>$this->generateUrl('note_edit', array('id' => $n->getId()))))->createView();
            $notes_delete_form[$n->getId()] = $this->createNoteDeleteForm($n)->createView();

            $response_note = clone $note;
            $response_note->setParent($n);
            $response_note_form = $this->createForm(NoteType::class, $response_note,
                array('action' => $this->generateUrl('note_reply', array('id' => $n->getId()))));

            $new_notes_form[$n->getId()] = $response_note_form->createView();
        }

        return $this->render('user/show.html.twig', array(
            'user' => $user,
            'note' => $note,
            'note_form' => $note_form->createView(),
            'notes_form' => $notes_form,
            'notes_delete_form' => $notes_delete_form,
            'new_notes_form' => $new_notes_form,
            'delete_form' => $deleteForm->createView(),
        ));
    }


    /**
     * Deletes a user entity.
     *
     * @Route("/{username}", name="user_delete")
     * @Method("DELETE")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function deleteAction(Request $request, User $user)
    {
        $form = $this->createDeleteForm($user);
        $form->handleRequest($request);

        $session = new Session();
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            if (count($user->getBeneficiaries())){ //admin do not have any Beneficiary
                $em->remove($user->getMainBeneficiary());
                foreach ($user->getBeneficiaries() as $beneficiary){
                    $em->remove($beneficiary);
                }
            }
            $em->remove($user);
            $em->flush();

            $session->getFlashBag()->add('success',"L'utilisateur a bien été supprimé");
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
            ->setAction($this->generateUrl('user_delete', array('username' => $user->getUsername())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    private function getErrorMessages(Form $form) {
        $errors = array();

        foreach ($form->getErrors() as $key => $error) {
            if ($form->isRoot()) {
                $errors['#'][] = $error->getMessage();
            } else {
                $errors[] = $error->getMessage();
            }
        }

        foreach ($form->all() as $child) {
            if (!$child->isValid()) {
                $key = (isset($child->getConfig()->getOptions()['label'])) ? $child->getConfig()->getOptions()['label'] : $child->getName();
                $errors[$key] = $this->getErrorMessages($child);
            }
        }

        return $errors;
    }
}
