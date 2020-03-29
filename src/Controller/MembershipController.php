<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\AnonymousBeneficiary;
use App\Entity\Beneficiary;
use App\Entity\Client;
use App\Entity\Membership;
use App\Entity\Note;
use App\Entity\Registration;
use App\Entity\Shift;
use App\Entity\TimeLog;
use App\Entity\User;
use App\Event\AnonymousBeneficiaryCreatedEvent;
use App\Event\BeneficiaryAddEvent;
use App\Event\MemberCreatedEvent;
use App\EventListener\SetFirstPasswordListener;
use App\Form\BeneficiaryType;
use App\Form\MembershipType;
use App\Form\NoteType;
use App\Form\RegistrationType;
use App\Form\TimeLogType;
use App\Security\MembershipVoter;
use App\Service\MailerService;
use App\Validator\Constraints\BeneficiaryCanHost;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\UserEvent;
use FOS\UserBundle\FOSUserBundle;
use FOS\UserBundle\FOSUserEvents;
use Spipu\Html2Pdf\Tag\Html\U;
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
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Twig\Sandbox\SecurityError;
use Symfony\Component\HttpFoundation\Response;

/**
 * User controller.
 *
 * @Route("member")
 */
class MembershipController extends Controller
{
    private $_current_app_user;

    /**
     * @return User mixed
     */
    public function getCurrentAppUser()
    {
        if (!$this->_current_app_user) {
            $this->_current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        }
        return $this->_current_app_user;
    }

    /**
     * Finds and displays a membership entity.
     *
     * @Route("/show/{member_number}", name="member_show")
     * @Method("GET")
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Membership $member)
    {
        $session = new Session();
        if ($member->getMemberNumber() <= 0) {
            return $this->redirectToRoute("homepage");
        }
        $this->denyAccessUnlessGranted('view', $member);

        $user = $member->getMainBeneficiary()->getUser(); // FIXME

        $deleteForm = $this->createDeleteForm($member);

        $note = new Note();
        $note_form = $this->createForm(NoteType::class, $note, array(
            'action' => $this->generateUrl('ambassador_new_note', array("member_number" => $member->getMemberNumber())),
            'method' => 'POST',
        ));
        $notes_form = array();
        $notes_delete_form = array();
        $new_notes_form = array();
        foreach ($member->getNotes() as $n) {
            $notes_form[$n->getId()] = $this->createForm(NoteType::class, $n, array('action' => $this->generateUrl('note_edit', array('id' => $n->getId()))))->createView();
            $notes_delete_form[$n->getId()] = $this->createNoteDeleteForm($n)->createView();

            $response_note = clone $note;
            $response_note->setParent($n);
            $response_note_form = $this->createForm(NoteType::class, $response_note,
                array('action' => $this->generateUrl('note_reply', array('id' => $n->getId()))));

            $new_notes_form[$n->getId()] = $response_note_form->createView();
        }
        $newReg = new Registration();
        $remainder = $this->get('membership_service')->getRemainder($member);
        if (!$remainder->invert) { //still some days
            $newReg->setDate($this->get('membership_service')->getExpire($member));
        } else { //register now !
            $newReg->setDate(new DateTime('now'));
        }
        $newReg->setRegistrar($this->get('security.token_storage')->getToken()->getUser());
        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            $action = $this->generateUrl('member_new_registration', array('member_number' => $member->getMemberNumber()));
        else
            $action = $this->generateUrl('member_new_registration', array('member_number' => $member->getMemberNumber(), 'token' => $member->getTmpToken($session->get('token_key') . $this->getCurrentAppUser()->getUsername())));


        $registrationForm = $this->createForm(RegistrationType::class, $newReg, array('action' => $action));
        $registrationForm->add('is_new', HiddenType::class, array('attr' => array('value' => '1')));

        $deleteBeneficiaryForms = array();
        foreach ($member->getBeneficiaries() as $beneficiary) {
            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                $deleteBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                    ->setAction($this->generateUrl('beneficiary_delete', array('id' => $beneficiary->getId())))
                    ->setMethod('DELETE')->getForm()->createView();
            else
                $deleteBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                    ->setAction($this->generateUrl('beneficiary_delete', array(
                        'id' => $beneficiary->getId(),
                        'token' => $user->getTmpToken($session->get('token_key') . $this->getCurrentAppUser()->getUsername())
                    )))
                    ->setMethod('DELETE')->getForm()->createView();
        }

        $beneficiaryForm = $this->createNewBeneficiaryForm($member);

        $timeLogForm = $this->createNewTimeLogForm($member);

        return $this->render('member/show.html.twig', array(
            'member' => $member,
            'note' => $note,
            'note_form' => $note_form->createView(),
            'new_registration_form' => $registrationForm->createView(),
            'new_beneficiary_form' => $beneficiaryForm->createView(),
            'notes_form' => $notes_form,
            'notes_delete_form' => $notes_delete_form,
            'new_notes_form' => $new_notes_form,
            'delete_beneficiary_forms' => $deleteBeneficiaryForms,
            'delete_form' => $deleteForm->createView(),
            'time_log_form' => $timeLogForm->createView()
        ));
    }

    private function createNewTimeLogForm(Membership $member)
    {
        $newTimeLogAction = $this->generateUrl('time_log_new', array('id' => $member->getId()));
        return $this->createForm(TimeLogType::class, new TimeLog(), array('action' => $newTimeLogAction));
    }


    /**
     * Add a new registration.
     *
     * @Route("/newRegistration/{member_number}/", name="member_new_registration")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newRegistration(Request $request, Membership $member)
    {
        $session = new Session();
        $this->denyAccessUnlessGranted('edit', $member);
        $newReg = new Registration();
        $remainder = $this->get('membership_service')->getRemainder($member);
        if (!$remainder->invert) { //still some days
            $newReg->setDate($this->get('membership_service')->getExpire($member));
        } else { //register now !
            $newReg->setDate(new DateTime('now'));
        }
        $newReg->setRegistrar($this->getCurrentAppUser());
        $registrationForm = $this->createForm(RegistrationType::class, $newReg);
        $registrationForm->add('is_new', HiddenType::class, array('attr' => array('value' => '1')));
        $registrationForm->handleRequest($request);
        if ($registrationForm->isSubmitted() && $registrationForm->isValid() && $registrationForm->get('is_new')->getData() != null) {
            $amount = floatval($registrationForm->get('amount')->getData());
            if ($amount <= 0) {
                $session->getFlashBag()->add('error', 'Adhésion prix libre & non gratuit !');
                return $this->redirectToShow($member);
            }

            if ($this->getCurrentAppUser()->getBeneficiary() && $this->getCurrentAppUser()->getBeneficiary()->getMembership()->getId() == $member->getId()) {
                $session->getFlashBag()->add('error', 'Tu ne peux pas enregistrer ta propre réadhésion, demande à un autre adhérent :)');
                return $this->redirectToShow($member);
            }
            $newReg->setRegistrar($this->getCurrentAppUser());

            $date = $registrationForm->get('date')->getData();
            if (!$this->get('membership_service')->canRegister($member,$date)) {
                $session->getFlashBag()->add('warning', 'l\'adhésion précédente est encore valable à cette date !');
                return $this->redirectToShow($member);
            }
            $newReg->setMembership($member);
            $member->addRegistration($newReg);

            $em = $this->getDoctrine()->getManager();
            $em->persist($newReg);
            $em->flush();

            $session->getFlashBag()->add('success', 'Enregistrement effectuée');
            return $this->redirectToShow($member);
        }

        $registrationForms = array();
        foreach ($member->getRegistrations() as $registration) {
            $form = $this->createForm(RegistrationType::class, $registration);
            $registrationForms[$registration->getId()] = $form->createView();
        }

        $id = $request->request->get("registration_id");
        if ($id) {
            $em = $this->getDoctrine()->getManager();
            $registration = $em->getRepository('App:Registration')->find($id);
            if ($registration) {
                $form = $this->createForm(RegistrationType::class, $registration);
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    if ($this->getCurrentAppUser()->getBeneficiary() && $this->getCurrentAppUser()->getBeneficiary()->getMembership()->getId() == $member->getId()) {
                        $session->getFlashBag()->add('error', 'Tu ne peux pas modifier tes propres adhésions :)');
                        return $this->redirectToShow($member);
                    }
                    $em->persist($registration);
                    $em->flush();
                    $session->getFlashBag()->add('success', 'Mise à jour effectuée');
                    return $this->redirectToShow($member);
                }
            }
        }

        if ($member->isWithdrawn())
            $session->getFlashBag()->add('warning', 'Ce compte est fermé');

        return $this->redirectToShow($member);
    }

    /**
     * Add a beneficiary from admin to a member
     *
     * @Route("/newBeneficiary/{member_number}/", name="member_new_beneficiary")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newBeneficiary(Request $request, Membership $member)
    {
        $session = new Session();
        $this->denyAccessUnlessGranted(MembershipVoter::BENEFICIARY_ADD, $member);

        //check if member can host
        $beneficiaryCanHostConstraint = new BeneficiaryCanHost();
        $violations = $this->get('validator')->validate(
            $member->getMainBeneficiary(),
            $beneficiaryCanHostConstraint
        );
        if (0 !== count($violations)) {
            // there are errors, now you can show them
            foreach ($violations as $violation) {
                $session->getFlashBag()->add('error',$violation->getMessage());
            }
            $session->getFlashBag()->add('warning','Veuillez réaliser une nouvelle adhésion');

            return $this->redirectToShow($member);
        }
        //yes he can

        $beneficiaryForm = $this->createNewBeneficiaryForm($member);
        $beneficiaryForm->handleRequest($request);
        if ($beneficiaryForm->isSubmitted() && $beneficiaryForm->isValid()) {
            $beneficiary = $beneficiaryForm->getData();

            $event = new FormEvent($beneficiaryForm->get('user'), $request);
            $this->get('event_dispatcher')->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

            if (count($member->getBeneficiaries()) <= $this->getParameter('maximum_nb_of_beneficiaries_in_membership')) {
                $beneficiary->setMembership($member);
                $member->addBeneficiary($beneficiary);
                $em = $this->getDoctrine()->getManager();
                $em->persist($beneficiary);
                $em->flush();

                $dispatcher = $this->get('event_dispatcher');
                $dispatcher->dispatch(BeneficiaryAddEvent::NAME, new BeneficiaryAddEvent($beneficiary));

                $session->getFlashBag()->add('success', 'Beneficiaire ajouté');
            } else {
                $session->getFlashBag()->add('error', 'Maximum ' . ($this->getParameter('maximum_nb_of_beneficiaries_in_membership')) . ' beneficiaires enregistrés');
            }
            return $this->redirectToShow($member);
        } elseif ($beneficiaryForm->isSubmitted()) {
            foreach ($beneficiaryForm->getErrors(true) as $key => $error) {
                $session->getFlashBag()->add('error', 'Erreur ' . ($key + 1) . " : " . $error->getMessage());
            }
        }

        return $this->redirectToShow($member);

    }

    private function createNewBeneficiaryForm(Membership $member)
    {
        $newBeneficiaryAction = $this->generateUrl('member_new_beneficiary', array('member_number' => $member->getMemberNumber()));
        return $this->createForm(BeneficiaryType::class, new Beneficiary(), array('action' => $newBeneficiaryAction));
    }

    /**
     * Displays a form to edit an existing member entity.
     *
     * @Route("/edit", name="member_edit_firewall")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function editFirewallAction(Request $request)
    {
        $session = new Session();

        if ($this->isGranted('view', new User())) {
            $form = $this->createFormBuilder()
                ->add('member_number', IntegerType::class, array('label' => 'Numéro d\'adhérent'))
                ->add('username', HiddenType::class, array('attr' => array('value' => '')))
                ->add('email', HiddenType::class, array('label' => 'email'))
                ->add('edit', SubmitType::class, array('label' => 'Editer', 'attr' => array('class' => 'btn')))
                ->getForm();
        } else {
            $form = $this->createFormBuilder()
                ->add('member_number', IntegerType::class, array('label' => 'Numéro d\'adhérent'))
                ->add('username', HiddenType::class, array('attr' => array('value' => '')))
                ->add('email', EmailType::class, array('label' => 'email'))
                ->add('edit', SubmitType::class, array('label' => 'Editer', 'attr' => array('class' => 'btn')))
                ->getForm();
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $member_number = $form->get('member_number')->getData();
            $username = $form->get('username')->getData();
            $email = $form->get('email')->getData();

            $em = $this->getDoctrine()->getManager();
            $member = null;
            if ($username)
                $member = $em->getRepository('App:User')->findOneBy(array('username' => $username));
            else if ($member_number) {
                $member = $em->getRepository('App:Membership')->findOneBy(array('member_number' => $member_number));
            }

            if ($member && ($this->isGranted('view', $member))) {
                $session->set('token_key', uniqid());
                return $this->redirectToShow($member);
            }

            if ($email)
                $session->getFlashBag()->add('error', 'cet email n\'est pas associé à ce numéro');
            if (!$member)
                $session->getFlashBag()->add('error', 'membre non trouvé');
        }

        return $this->render('user/edit_firewall.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * @Route("/{id}/set_email", name="set_email")
     * @Method({"POST"})
     * @param Beneficiary $beneficiary
     * @param Request $request
     * @return Response
     */
    public function setEmailAction(Beneficiary $beneficiary, Request $request)
    {
        $email = $request->request->get('email');
        $user = $beneficiary->getUser();
        $oldEmail = $user->getEmail();

        /** @var MailerService $mailerService */
        $mailerService = $this->get('mailer_service');

        if ($mailerService->isTemporaryEmail($oldEmail) && filter_var($email, FILTER_VALIDATE_EMAIL)) { //was a temp mail
            $user->setEmail($email);
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();
            $request->getSession()->getFlashBag()->add('success', 'Merci ! votre email a bien été enregistré');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $request->getSession()->getFlashBag()->add('warning', 'Oups, le format du courriel entré semble problématique');
        }
        return $this->render('beneficiary/confirm.html.twig', array(
            'beneficiary' => $beneficiary,
        ));
    }

    /**
     * @Route("/help_find_user", name="find_user_help")
     */
    public function findUserHelpAction(Request $request)
    {

        return $this->render('default/find_user_number.html.twig');
    }

    /**
     * @Route("/find_user", name="find_user")
     */
    public function findUserAction(Request $request)
    {
        die($request->getName());
    }


    /**
     * @Route("/find_me", name="find_me")
     * @param Request $request
     * @return Response
     */
    public function activeUserAccountAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('member_number', IntegerType::class, array('label' => 'Numéro d\'adhérent', 'attr' => array(
                'placeholder' => '0',
            )))
            ->add('find', SubmitType::class, array('label' => 'Activer mon compte'))
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member_number = $form->get('member_number')->getData();
            $em = $this->getDoctrine()->getManager();
            $ms = $em->getRepository('App:Membership')->findOneBy(array('member_number' => $member_number));

            if (!$ms){
                $request->getSession()->getFlashBag()->add('warning', 'Oups, aucun membre trouvé avec ce numéro d\'adhérent');
                return $this->render('user/tools/find_me.html.twig', array(
                    'form' => $form->createView(),
                ));
            }

            return $this->render('beneficiary/confirm.html.twig', array(
                'beneficiary' => $ms->getMainBeneficiary(),
            ));
        }
        return $this->render('user/tools/find_me.html.twig', array(
            'form' => $form->createView(),
        ));
    }


    /**
     * Close member
     *
     * @Route("/{id}/close/", name="member_close")
     * @Method({"GET"})
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function closeAction(Membership $member)
    {
        $this->denyAccessUnlessGranted('close', $member);
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $member->setWithdrawn(true);
        $em->persist($member);
        $em->flush();
        $session->getFlashBag()->add('success', 'Compte fermé');
        return $this->redirectToShow($member);
    }

    /**
     * Open member
     *
     * @Route("/{id}/open/", name="member_open")
     * @Method({"GET"})
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function openAction(Membership $member)
    {
        $this->denyAccessUnlessGranted('open', $member);
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $member->setWithdrawn(false);
        $em->persist($member);
        $em->flush();
        $session->getFlashBag()->add('success', 'Compte reouvert');
        return $this->redirectToShow($member);
    }

    /**
     * freeze user
     *
     * @Route("/{id}/freeze/", name="member_freeze")
     * @Method({"GET"})
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function freezeAction(Membership $member)
    {
        $this->denyAccessUnlessGranted('freeze', $member);
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $member->setFrozen(true);
        $member->setFrozenChange(false);
        $em->persist($member);
        $em->flush();
        $session->getFlashBag()->add('success', 'Compte gelé');
        return $this->redirectToShow($member);
    }

    /**
     * Unfreeze member
     *
     * @Route("/{id}/unfreeze/", name="member_unfreeze")
     * @Method({"GET"})
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function unfreezeAction(Membership $member)
    {
        $this->denyAccessUnlessGranted('freeze', $member);
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $member->setFrozen(false);
        $member->setFrozenChange(false);
        $em->persist($member);
        $em->flush();
        $session->getFlashBag()->add('success', 'Compte dégelé');
        return $this->redirectToShow($member);
    }

    /**
     * Ask freeze status change for user
     *
     * @Route("/{id}/freeze_change/}", name="member_freeze_change")
     * @Method({"GET"})
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function freezeChangeAction(Membership $member)
    {
        $this->denyAccessUnlessGranted('freeze_change', $member);
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $member->setFrozenChange(!$member->getFrozenChange());
        $em->persist($member);
        $em->flush();
        if ($member->getFrozenChange()) {
            $session->getFlashBag()->add('success', 'Le compte sera gelé à la fin du cycle');
        } else {
            $session->getFlashBag()->add('success', 'Le compte sera dégelé à la fin du cycle');
        }
        if ($this->getCurrentAppUser()->getBeneficiary() && $member === $this->getCurrentAppUser()->getBeneficiary()->getMembership()) {
            return $this->redirectToRoute("fos_user_profile_show");
        } else {
            return $this->redirectToShow($member);
        }
    }

    /**
     * Deletes a member entity.
     *
     * @Route("/delete/{id}", name="member_delete")
     * @Method("DELETE")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @param Request $request
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Request $request, Membership $member)
    {
        $form = $this->createDeleteForm($member);
        $form->handleRequest($request);

        $session = new Session();
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($member);
            $em->flush();

            $session->getFlashBag()->add('success', "Le membre a bien été supprimé");
        }

        return $this->redirectToRoute('user_index');
    }

    /**
     * Creates a new membership entity.
     *
     * @Route("/new", name="member_new")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @return Response
     * @throws
     */
    public function newAction(Request $request)
    {
        $session = new Session();

        $code = $request->query->get('code');
        $em = $this->getDoctrine()->getManager();
        $a_beneficiary = null;
        if ($code){
            $email = $this->get('App\Helper\SwipeCard')->vigenereDecode($code);
            if ($email){
                $a_beneficiary = $em->getRepository('App:AnonymousBeneficiary')->findOneBy(array('email'=>$email));
            }
            if (!$a_beneficiary){
                $session->getFlashBag()->add('error', 'Cette url n\'est plus valide');
                return $this->redirectToRoute("homepage");
            }else{
                if ($a_beneficiary->getJoinTo()){ //adding beneficiary to an existing membership : wrong place
                    return $this->redirectToRoute('member_add_beneficiary', array('code' => $this->container->get('App\Helper\SwipeCard')->vigenereEncode($email)));
                }
            }
        }

        if (!$a_beneficiary){
            $this->denyAccessUnlessGranted('create', $this->getCurrentAppUser());
        }

        $member = new Membership();
        if ($a_beneficiary){
            $user = new User();
            $user->setEmail($a_beneficiary->getEmail());
            $beneficiary = new Beneficiary();
            $beneficiary->setUser($user);
            $member->setMainBeneficiary($beneficiary);
        }

        $m = $em->getRepository('App:Membership')->findOneBy(array(), array('member_number' => 'DESC'));
        $mm = 1;
        if ($m)
            $mm = $m->getMemberNumber() + 1;
        $member->setMemberNumber($mm);

        $registration = new Registration();
        if ($a_beneficiary){
            $registration->setDate($a_beneficiary->getCreatedAt());
            $registration->setRegistrar($a_beneficiary->getRegistrar());
            $registration->setAmount($a_beneficiary->getAmount());
            $registration->setMode($a_beneficiary->getMode());
            if ($a_beneficiary->getMode()===Registration::TYPE_HELLOASSO){
                $registration->setAmount('--');
            }
        }else{
            $registration->setDate(new DateTime('now'));
            $registration->setRegistrar($this->getUser());
        }
        $registration->setMembership($member);

        $member->addRegistration($registration);

        $form = $this->createForm(MembershipType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $dispatcher = $this->get('event_dispatcher');

            if (!$a_beneficiary) {
                if (!$member->getLastRegistration()->getRegistrar())
                    $member->getLastRegistration()->setRegistrar($this->getUser());
            } else if ($a_beneficiary->getMode() === Registration::TYPE_HELLOASSO) {
                $member->removeRegistration($registration); //no registration yet
            }

            $member->setWithdrawn(false);
            $member->setFrozen(false);
            $member->setFrozenChange(false);

            $event = new FormEvent($form->get('mainBeneficiary')->get('user'), $request);
            $this->get('event_dispatcher')->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

            $em->persist($member);
            if ($a_beneficiary) {
                $beneficiaries_emails = $a_beneficiary->getBeneficiariesEmailsAsArray();
                foreach ($beneficiaries_emails as $email){
                    $new_anonymous_beneficiary = new AnonymousBeneficiary();
                    $new_anonymous_beneficiary->setCreatedAtValue(new \DateTime());
                    $new_anonymous_beneficiary->setEmail($email);
                    $new_anonymous_beneficiary->setJoinTo($member->getMainBeneficiary());
                    $new_anonymous_beneficiary->setRegistrar($a_beneficiary->getRegistrar());
                    $em->persist($new_anonymous_beneficiary);
                    //dispatch to send mail
                    $dispatcher->dispatch(AnonymousBeneficiaryCreatedEvent::NAME, new AnonymousBeneficiaryCreatedEvent($new_anonymous_beneficiary));
                }
                $em->remove($a_beneficiary);
            }
            $em->flush();

            $dispatcher->dispatch(MemberCreatedEvent::NAME, new MemberCreatedEvent($member));

            $securityContext = $this->container->get('security.authorization_checker');
            if (!$securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
                $session->getFlashBag()->add('success', 'Merci '.$member->getMainBeneficiary()->getFirstname().' ! Ton adhésion est maintenant finalisée. Verifie tes emails pour te connecter.');
                return $this->redirectToRoute('homepage');
            } else {
                $session->getFlashBag()->add('success', 'La nouvelle adhésion a bien été prise en compte !');
            }

            return $this->redirectToShow($member);
        } else if ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $key => $error) {
                $session->getFlashBag()->add('error', 'Erreur ' . ($key + 1) . " : " . $error->getMessage());
            }
        }

        return $this->render('member/new.html.twig', array(
            'member' => $member,
            'form' => $form->createView(),
        ));
    }

    /**
     * Add a new beneficiary from an anonymous one to an existing membership.
     *
     * @Route("/add_beneficiary", name="member_add_beneficiary")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @return Response
     * @throws
     */
    public function addBeneficiaryAction(Request $request)
    {
        $session = new Session();

        $code = $request->query->get('code');
        $em = $this->getDoctrine()->getManager();
        $a_beneficiary = null;
        if ($code) {
            $email = $this->get('App\Helper\SwipeCard')->vigenereDecode($code);
            if ($email) {
                $a_beneficiary = $em->getRepository('App:AnonymousBeneficiary')->findOneBy(array('email' => $email));
            }
            if (!$a_beneficiary) {
                $session->getFlashBag()->add('error', 'Cette url n\'est plus valide');
                return $this->redirectToRoute('homepage');
            }
        }

        if (!$a_beneficiary) {
            $this->createAccessDeniedException('Tu cherches ?');
        }
        if (!$a_beneficiary->getJoinTo()){
            $session->getFlashBag()->add('error','destination non trouvé');
            return $this->redirectToRoute('homepage');
        }
        $member = $a_beneficiary->getJoinTo()->getMembership();

        $beneficiaryCanHostConstraint = new BeneficiaryCanHost();
        $violations = $this->get('validator')->validate(
            $member->getMainBeneficiary(),
            $beneficiaryCanHostConstraint
        );
        if (0 !== count($violations)) {
            // there are errors, now you can show them
            foreach ($violations as $violation) {
                $session->getFlashBag()->add('error',$violation->getMessage());
            }
            $session->getFlashBag()->add('warning','Veuillez réaliser une nouvelle adhésion');
            $em->remove($a_beneficiary);
            $em->flush();

            return $this->redirectToRoute('homepage');
        }

        $form = $this->createFormBuilder()
            ->add('beneficiary', BeneficiaryType::class)
            ->getForm();

        $beneficiary = new Beneficiary();
        $beneficiary->setUser(new User());
        $beneficiary->setEmail($a_beneficiary->getEmail());

        $form->get('beneficiary')->setData($beneficiary);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $beneficiary = $form->get('beneficiary')->getData();
            $beneficiary->setMembership($member);

            $event = new FormEvent($form->get('beneficiary')->get('user'), $request);
            $this->get('event_dispatcher')->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

            $em->persist($beneficiary);
            $em->remove($a_beneficiary);
            $em->flush();

            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(BeneficiaryAddEvent::NAME, new BeneficiaryAddEvent($beneficiary));

            $session->getFlashBag()->add('success', 'Merci ' . $beneficiary->getFirstname() . ' ! Ton adhésion est maintenant finalisée');
            return $this->redirectToRoute('fos_user_registration_check_email');

        } else if ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $key => $error) {
                $session->getFlashBag()->add('error', 'Erreur ' . ($key + 1) . " : " . $error->getMessage());
            }
        }

        return $this->render('member/add_beneficiary.html.twig', array(
            'member' => $member,
            'form' => $form->createView(),
        ));
    }

    /**
     * Join two members
     *
     * @Route("/join", name="member_join")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function joinAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('from_text', TextType::class, array('label' => 'Adhérent a joindre'))
            ->add('dest_text', TextType::class, array('label' => 'au compte de l\'adhérent'))
            ->add('join', SubmitType::class, array('label' => 'Joindre les deux comptes', 'attr' => array('class' => 'btn')))
            ->getForm();
        $form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $re = '/#([0-9]+).*/';
            $str = $form->get('from_text')->getData() . "\n" . $form->get('dest_text')->getData();
            preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);
            if (count($matches) >= 2) {
                $fromMember = $em->getRepository('App:Membership')->findOneBy(array("member_number" => $matches[0][1]));
                if ($fromMember) {
                    $destMember = $em->getRepository('App:Membership')->findOneBy(array("member_number" => $matches[1][1]));
                    if ($destMember) {
                        foreach ($fromMember->getBeneficiaries() as $beneficiary) {
                            $destMember->addBeneficiary($beneficiary); //in
                            $fromMember->removeBeneficiary($beneficiary); //out
                            $beneficiary->setMembership($destMember);
                            $em->persist($beneficiary);
                        }
                        $em->persist($destMember);
                        $em->flush();
                        $fromMember->setMainBeneficiary(null);
                        $em->remove($fromMember);
                        $em->flush();

                        $session->getFlashBag()->add('success', 'Les deux adhérents ont bien été fusionnés');

                        return $this->redirectToShow($destMember);
                    } else {
                        $session->getFlashBag()->add('error', 'impossible de trouver le compte de destination');
                    }
                } else {
                    $session->getFlashBag()->add('error', 'impossible de trouver le compte à lier');
                }
            }

        }

        $members = $em->getRepository('App:Membership')->findAll(); //todo exclude closed
        return $this->render('admin/member/join.html.twig', array('form' => $form->createView(), 'members' => $members));
    }

    /**
     * Lists all user entities.
     *
     * @Route("/office_tools", name="user_office_tools")
     * @Method({"GET","POST"})
     */
    public function officeToolsAction(Request $request)
    {
        $this->denyAccessUnlessGranted('access_tools', $this->getCurrentAppUser());
        $note = new Note();
        $note->setAuthor($this->getCurrentAppUser());
        $note_form = $this->createForm(NoteType::class, $note);
        $note_form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();

        $session = $request->getSession();
        if ($note_form->isSubmitted()) {
            if ($note_form->isValid()) {
                $existing_note = $em->getRepository('App:Note')->findOneBy(array("subject" => null, "author" => $this->getCurrentAppUser(), "text" => $note->getText()));
                if ($existing_note) {
                    $session->getFlashBag()->add('error', 'Ce post-it existe déjà');
                } else {
                    $em->persist($note);
                    $em->flush();
                    $session->getFlashBag()->add('success', 'Post-it ajouté');
                }
            } else {
                $session->getFlashBag()->add('error', 'Impossible d\'ajouter le post-it');
            }
        }


        $notes = $em->getRepository('App:Note')->findBy(array("subject" => null));
        $notes_form = array();
        $notes_delete_form = array();
        $new_notes_form = array();
        foreach ($notes as $n) {
            $notes_form[$n->getId()] = $this->createForm(NoteType::class, $n, array('action' => $this->generateUrl('note_edit', array('id' => $n->getId()))))->createView();
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
     * Creates a form to delete a member entity.
     *
     * @param Membership $member
     * @return \Symfony\Component\Form\FormInterface
     */
    private function createDeleteForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_delete', array('id' => $member->getId())))
            ->setMethod('DELETE')
            ->getForm();
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
     * Export all emails of members (including beneficiary)
     *
     * @Route("/emails_csv", name="admin_emails_csv")
     * @Method({"GET"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function exportEmails(Request $request)
    {
        $beneficiaries = $this->getDoctrine()->getRepository("App:Beneficiary")->findAll();
        $return = '';
        if ($beneficiaries) {
            $d = ','; // this is the default but i like to be explicit
            $e = '"'; // this is the default but i like to be explicit

            /** @var MailerService $mailerService */
            $mailerService = $this->get('mailer_service');

            foreach ($beneficiaries as $beneficiary) {
                if (!$beneficiary->getMembership()->isWithdrawn()) {
                    if (!$mailerService->isTemporaryEmail($beneficiary->getEmail()) && filter_var($beneficiary->getEmail(), FILTER_VALIDATE_EMAIL)) { //was not a temp mail
                        $return .= $beneficiary->getFirstname() . $d . $beneficiary->getLastname() . $d . $beneficiary->getEmail() . "\n";
                    }
                }
            }
        }
        return new Response($return, 200, array(
            'Content-Encoding: UTF-8',
            'Content-Type' => 'application/force-download; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="emails_' . date('dmyhis') . '.csv"'
        ));
    }

    private function redirectToShow(Membership $member)
    {
        $securityContext = $this->container->get('security.authorization_checker');
        if (!$securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('homepage');
        }
        $session = new Session();
        if ($this->get('security.authorization_checker')->isGranted('ROLE_USER_MANAGER'))
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber()));
        else
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber(), 'token' => $member->getTmpToken($session->get('token_key') . $this->getCurrentAppUser()->getUsername())));
    }
}
