<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\AnonymousBeneficiary;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Client;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Note;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
use AppBundle\Entity\User;
use AppBundle\Event\AnonymousBeneficiaryCreatedEvent;
use AppBundle\Event\BeneficiaryAddEvent;
use AppBundle\Event\MemberCreatedEvent;
use AppBundle\EventListener\SetFirstPasswordListener;
use AppBundle\Form\AutocompleteBeneficiaryType;
use AppBundle\Form\BeneficiaryType;
use AppBundle\Form\MembershipType;
use AppBundle\Form\NoteType;
use AppBundle\Form\RegistrationType;
use AppBundle\Form\TimeLogType;
use AppBundle\Security\MembershipVoter;
use AppBundle\Service\MailerService;
use AppBundle\Validator\Constraints\BeneficiaryCanHost;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\UserEvent;
use FOS\UserBundle\FOSUserBundle;
use FOS\UserBundle\FOSUserEvents;
use Spipu\Html2Pdf\Tag\Html\U;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
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
     * Why the '/show' in the route? Because routing conflict if not
     *
     * @Route("/{member_number}/show", name="member_show", methods={"GET"})
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Membership $member)
    {
        if ($member->getMemberNumber() <= 0) {
            return $this->redirectToRoute("homepage");
        }
        $this->denyAccessUnlessGranted('view', $member);

        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $freezeForm = $this->createFreezeForm($member);
        $unfreezeForm = $this->createUnfreezeForm($member);
        $freezeChangeForm = $this->createFreezeChangeForm($member);
        $closeForm = $this->createCloseForm($member);
        $openForm = $this->createOpenForm($member);
        $deleteForm = $this->createDeleteForm($member);

        $note = new Note();
        $noteNewForm = $this->createForm(NoteType::class, $note, array(
            'action' => $this->generateUrl('ambassador_new_note', array("member_number" => $member->getMemberNumber())),
            'method' => 'POST',
        ));
        $noteEditForms = array();
        $noteDeleteForms = array();
        $new_notes_form = array();
        foreach ($member->getNotes() as $n) {
            $noteEditForms[$n->getId()] = $this->createForm(NoteType::class, $n, array('action' => $this->generateUrl('note_edit', array('id' => $n->getId()))))->createView();
            $noteDeleteForms[$n->getId()] = $this->createNoteDeleteForm($n)->createView();

            $response_note = clone $note;
            $response_note->setParent($n);
            $response_note_form = $this->createForm(NoteType::class, $response_note,
                array('action' => $this->generateUrl('note_reply', array('id' => $n->getId()))));

            $new_notes_form[$n->getId()] = $response_note_form->createView();
        }

        $newReg = new Registration();
        $remainder = $this->get('membership_service')->getRemainder($member);
        if (!$remainder->invert) { //still some days
            $expire = $this->get('membership_service')->getExpire($member);
            $expire->modify('+1 day');
            $newReg->setDate($expire);
        } else { //register now !
            $newReg->setDate(new DateTime('now'));
        }
        $newReg->setRegistrar($this->get('security.token_storage')->getToken()->getUser());
        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            $action = $this->generateUrl('member_new_registration', array('member_number' => $member->getMemberNumber()));
        } else {
            $action = $this->generateUrl('member_new_registration', array('member_number' => $member->getMemberNumber(), 'token' => $member->getTmpToken($session->get('token_key') . $this->getCurrentAppUser()->getUsername())));
        }

        $registrationForm = $this->createForm(RegistrationType::class, $newReg, array('action' => $action));
        $registrationForm->add('is_new', HiddenType::class, array('attr' => array('value' => '1')));

        $detachBeneficiaryForms = array();
        $deleteBeneficiaryForms = array();
        foreach ($member->getBeneficiaries() as $beneficiary) {
            if (!$beneficiary->isMain()) {
                $detachBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                    ->setAction($this->generateUrl('beneficiary_detach', array('id' => $beneficiary->getId())))
                    ->setMethod('POST')->getForm()->createView();
            } else {
                $detachBeneficiaryForms[$beneficiary->getId()] = array();
            }
            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $deleteBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                    ->setAction($this->generateUrl('beneficiary_delete', array('id' => $beneficiary->getId())))
                    ->setMethod('DELETE')->getForm()->createView();
            } else {
                $user = $member->getMainBeneficiary()->getUser(); // FIXME
                $deleteBeneficiaryForms[$beneficiary->getId()] = $this->createFormBuilder()
                    ->setAction($this->generateUrl('beneficiary_delete', array(
                        'id' => $beneficiary->getId(),
                        'token' => $user->getTmpToken($session->get('token_key') . $this->getCurrentAppUser()->getUsername())
                    )))
                    ->setMethod('DELETE')->getForm()->createView();
            }
        }
        $beneficiaryForm = $this->createNewBeneficiaryForm($member);

        $timeLogNewForm = $this->createNewTimeLogForm($member);
        $timeLogDeleteForms = [];
        if ($this->get('security.authorization_checker')->isGranted('ROLE_SUPER_ADMIN')) {
            foreach ($member->getTimeLogs() as $mtl) {
                $timeLogDeleteForms[$mtl->getId()] = $this->createTimeLogDeleteForm($member, $mtl)->createView();
            }
        }

        $period_positions = $em->getRepository('AppBundle:PeriodPosition')->findByBeneficiaries($member->getBeneficiaries());
        $previous_cycle_start = $this->get('membership_service')->getStartOfCycle($member, -1 * $this->getParameter('max_nb_of_past_cycles_to_display'));
        $next_cycle_end = $this->get('membership_service')->getEndOfCycle($member, 1);
        $shifts_by_cycle = $em->getRepository('AppBundle:Shift')->findShiftsByCycles($member, $previous_cycle_start, $next_cycle_end);
        $shifts_by_cycle = array_reverse($shifts_by_cycle, true);  // from latest to oldest
        $shiftFreeForms = [];
        $shiftValidateInvalidateForms = [];
        foreach ($shifts_by_cycle as $shifts) {
            foreach ($shifts as $shift) {
                $shiftFreeForms[$shift->getId()] = $this->createShiftFreeAdminForm($shift)->createView();
                $shiftValidateInvalidateForms[$shift->getId()] = $this->createShiftValidateInvalidateAdminForm($shift)->createView();
            }
        }

        $in_progress_and_upcoming_shifts = $em->getRepository('AppBundle:Shift')->findInProgressAndUpcomingShiftsForMembership($member);

        return $this->render('member/show.html.twig', array(
            'member' => $member,
            'note' => $note,
            'note_form' => $noteNewForm->createView(),
            'notes_form' => $noteEditForms,
            'note_delete_forms' => $noteDeleteForms,
            'new_notes_form' => $new_notes_form,
            'new_registration_form' => $registrationForm->createView(),
            'new_beneficiary_form' => $beneficiaryForm->createView(),
            'detach_beneficiary_forms' => $detachBeneficiaryForms,
            'delete_beneficiary_forms' => $deleteBeneficiaryForms,
            'freeze_form' => $freezeForm->createView(),
            'unfreeze_form' => $unfreezeForm->createView(),
            'freeze_change_form' => $freezeChangeForm->createView(),
            'close_form' => $closeForm->createView(),
            'open_form' => $openForm->createView(),
            'delete_form' => $deleteForm->createView(),
            'time_log_new_form' => $timeLogNewForm->createView(),
            'time_log_delete_forms' => $timeLogDeleteForms,
            'period_positions' => $period_positions,
            'in_progress_and_upcoming_shifts' => $in_progress_and_upcoming_shifts,
            'shifts_by_cycle' => $shifts_by_cycle,
            'shift_free_forms' => $shiftFreeForms,
            'shift_validate_invalidate_forms' => $shiftValidateInvalidateForms,
        ));
    }

    private function createNewTimeLogForm(Membership $member)
    {
        $newTimeLogAction = $this->generateUrl('timelog_new', array('id' => $member->getId()));
        return $this->createForm(TimeLogType::class, new TimeLog(), array('action' => $newTimeLogAction));
    }


    /**
     * Add a new registration.
     *
     * @Route("/{member_number}/newRegistration", name="member_new_registration", methods={"GET","POST"})
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
            $expire = $this->get('membership_service')->getExpire($member);
            $expire->modify('+1 day');
            $newReg->setDate($expire);
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
                $session->getFlashBag()->add('error', 'Tu ne peux pas enregistrer ta propre ré-adhésion, demande à un autre adhérent :)');
                return $this->redirectToShow($member);
            }
            $newReg->setRegistrar($this->getCurrentAppUser());

            $date = $registrationForm->get('date')->getData();
            if ($this->get('membership_service')->getExpire($member) >= $date) {
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

        $id = $request->request->get("registration_id");
        if ($id) {
            $em = $this->getDoctrine()->getManager();
            $registration = $em->getRepository('AppBundle:Registration')->find($id);
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
     * @Route("/{member_number}/newBeneficiary", name="member_new_beneficiary", methods={"GET","POST"})
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
                $session->getFlashBag()->add('error', $violation->getMessage());
            }
            $session->getFlashBag()->add('warning','Veuillez réaliser une nouvelle adhésion');

            return $this->redirectToShow($member);
        }
        //yes he can

        $beneficiaryForm = $this->createNewBeneficiaryForm($member);
        $beneficiaryForm->handleRequest($request);
        if ($beneficiaryForm->isSubmitted() && $beneficiaryForm->isValid()) {
            $beneficiary = $beneficiaryForm->getData();
            $dispatcher = $this->get('event_dispatcher');

            $event = new FormEvent($beneficiaryForm->get('user'), $request);
            $dispatcher->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

            if (count($member->getBeneficiaries()) <= $this->getParameter('maximum_nb_of_beneficiaries_in_membership')) {
                $beneficiary->setMembership($member);
                $member->addBeneficiary($beneficiary);
                $em = $this->getDoctrine()->getManager();
                $em->persist($beneficiary);
                $em->flush();

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

    /**
     * Displays a form to edit an existing member entity.
     *
     * @Route("/edit", name="member_edit_firewall", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_VIEWER')")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function editFirewallAction(Request $request)
    {
        $session = new Session();

        if ($this->isGranted('ROLE_USER_VIEWER')) {
            $form = $this->createFormBuilder()
                ->add('member_number', IntegerType::class, array('label' => 'Numéro d\'adhérent'))
                ->add('username', HiddenType::class, array('attr' => array('value' => '')))
                ->add('email', HiddenType::class, array('label' => 'email'))  # hidden
                ->add('edit', SubmitType::class, array('label' => 'Editer', 'attr' => array('class' => 'btn')))
                ->getForm();
        } else {  # higher privileges
            $form = $this->createFormBuilder()
                ->add('member_number', IntegerType::class, array('label' => 'Numéro d\'adhérent'))
                ->add('username', HiddenType::class, array('attr' => array('value' => '')))
                ->add('email', EmailType::class, array('label' => 'email'))  # visible
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
                $member = $em->getRepository('AppBundle:User')->findOneBy(array('username' => $username));
            else if ($member_number) {
                $member = $em->getRepository('AppBundle:Membership')->findOneBy(array('member_number' => $member_number));
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
     * @Route("/{id}/set_email", name="set_email", methods={"POST"})
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
            $request->getSession()->getFlashBag()->add('warning', "Oups, le format de l'email entré semble problématique");
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

        if ($form->handleRequest($request)->isValid()) {
            $member_number = $form->get('member_number')->getData();
            $em = $this->getDoctrine()->getManager();
            $ms = $em->getRepository('AppBundle:Membership')->findOneBy(array('member_number' => $member_number));

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
     * @Route("/{id}/close", name="member_close", methods={"POST"})
     * @param Request $request
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function closeAction(Request $request, Membership $member)
    {
        $this->denyAccessUnlessGranted('close', $member);

        $current_user = $this->get('security.token_storage')->getToken()->getUser();
        $session = new Session();

        $form = $this->createCloseForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $member->setWithdrawn(true);
            $member->setWithdrawnDate(new \DateTime('now'));
            $member->setWithdrawnBy($current_user);
            $em->persist($member);
            $em->flush();

            $session->getFlashBag()->add('success', 'Compte fermé !');
        }

        return $this->redirectToShow($member);
    }

    /**
     * Open member
     *
     * @Route("/{id}/open", name="member_open", methods={"POST"})
     * @param Request $request
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function openAction(Request $request, Membership $member)
    {
        $this->denyAccessUnlessGranted('open', $member);

        $form = $this->createOpenForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();

            $member->setWithdrawn(false);
            $em->persist($member);
            $em->flush();

            $session->getFlashBag()->add('success', 'Compte ré-ouvert !');
        }

        return $this->redirectToShow($member);
    }

    /**
     * freeze member
     *
     * @Route("/{id}/freeze", name="member_freeze", methods={"POST"})
     * @param Request $request
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function freezeAction(Request $request, Membership $member)
    {
        $this->denyAccessUnlessGranted('freeze', $member);

        $form = $this->createFreezeForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();

            $member->setFrozen(true);
            $member->setFrozenChange(false);
            $em->persist($member);
            $em->flush();

            $session->getFlashBag()->add('success', 'Compte gelé !');
        }

        return $this->redirectToShow($member);
    }

    /**
     * Unfreeze member
     *
     * @Route("/{id}/unfreeze", name="member_unfreeze", methods={"POST"})
     * @param Request $request
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function unfreezeAction(Request $request, Membership $member)
    {
        $this->denyAccessUnlessGranted('freeze', $member);

        $form = $this->createUnfreezeForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();

            $member->setFrozen(false);
            $member->setFrozenChange(false);
            $em->persist($member);
            $em->flush();

            $session->getFlashBag()->add('success', 'Compte dégelé !');
        }

        return $this->redirectToShow($member);
    }

    /**
     * Ask freeze status change for user
     *
     * @Route("/{id}/freeze_change", name="member_freeze_change", methods={"POST"})
     * @param Request $request
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function freezeChangeAction(Request $request, Membership $member)
    {
        $this->denyAccessUnlessGranted('freeze_change', $member);

        $form = $this->createFreezeChangeForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();

            $member->setFrozenChange(!$member->getFrozenChange());
            $em->persist($member);
            $em->flush();

            if ($member->isFrozen()) {
                if ($member->getFrozenChange()) {
                    $session->getFlashBag()->add('success', 'Le compte sera dégelé à la fin du cycle !');
                } else {
                    $session->getFlashBag()->add('success', 'La demande de dégel a été annulée !');
                }
            } else {
                if ($member->getFrozenChange()) {
                    $session->getFlashBag()->add('success', 'Le compte sera gelé à la fin du cycle !');
                } else {
                    $session->getFlashBag()->add('success', 'La demande de gel a été annulée !');
                }
            }
        }

        if ($this->getCurrentAppUser()->getBeneficiary() && $member === $this->getCurrentAppUser()->getBeneficiary()->getMembership()) {
            return $this->redirectToRoute("fos_user_profile_show");
        } else {
            return $this->redirectToShow($member);
        }
    }

    /**
     * Delete member
     *
     * @Route("/{id}", name="member_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @param Request $request
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Request $request, Membership $member)
    {
        $session = new Session();

        $form = $this->createDeleteForm($member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($member);
            $em->flush();

            $session->getFlashBag()->add('success', "Le membre a bien été supprimé !");
        }

        return $this->redirectToRoute('user_index');
    }

    /**
     * Creates a new membership entity
     *
     * @Route("/new", name="member_new", methods={"GET","POST"})
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $a_beneficiary = null;
        $code = $request->query->get('code');

        if ($code) {
            $email = $this->get('AppBundle\Helper\SwipeCard')->vigenereDecode($code);
            if ($email) {
                $a_beneficiary = $em->getRepository('AppBundle:AnonymousBeneficiary')->findOneBy(array('email'=>$email));
            }
            if (!$a_beneficiary) {
                $session->getFlashBag()->add('error', 'Cette url n\'est plus valide');
                return $this->redirectToRoute("homepage");
            } else {
                if ($a_beneficiary->getJoinTo()) { //adding beneficiary to an existing membership : wrong place
                    return $this->redirectToRoute('member_add_beneficiary', array('code' => $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($email)));
                }
            }
        }

        if (!$a_beneficiary) {
            $this->denyAccessUnlessGranted('create', $this->getCurrentAppUser());
        }

        $member = new Membership();
        if ($a_beneficiary) {
            $user = new User();
            $user->setEmail($a_beneficiary->getEmail());
            $beneficiary = new Beneficiary();
            $beneficiary->setUser($user);
            $beneficiary->setFlying(false);
            $member->setMainBeneficiary($beneficiary);
        }

        // init member_number
        $m = $em->getRepository('AppBundle:Membership')->findOneBy(array(), array('member_number' => 'DESC'));
        $mm = 1;
        if ($m)
            $mm = $m->getMemberNumber() + 1;
        $member->setMemberNumber($mm);

        $registration = new Registration();
        if ($a_beneficiary) {
            $registration->setDate($a_beneficiary->getCreatedAt());
            $registration->setRegistrar($a_beneficiary->getRegistrar());
            $registration->setAmount($a_beneficiary->getAmount());
            $registration->setMode($a_beneficiary->getMode());
            if ($a_beneficiary->getMode()===Registration::TYPE_HELLOASSO) {
                $registration->setAmount('--');
            }
        } else {
            $registration->setDate(new DateTime('now'));
            $registration->setRegistrar($current_user);
        }
        $registration->setMembership($member);

        $member->addRegistration($registration);

        $form = $this->createForm(MembershipType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dispatcher = $this->get('event_dispatcher');

            if (!$a_beneficiary) {
                if (!$member->getLastRegistration()->getRegistrar()) {
                    $member->getLastRegistration()->setRegistrar($current_user);
                }
            } else if ($a_beneficiary->getMode() === Registration::TYPE_HELLOASSO) {
                $member->removeRegistration($registration); //no registration yet
            }

            $member->setWithdrawn(false);
            $member->setFrozen(false);
            $member->setFrozenChange(false);

            $event = new FormEvent($form->get('mainBeneficiary')->get('user'), $request);
            $dispatcher->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

            $em->persist($member);
            if ($a_beneficiary) {
                $beneficiaries_emails = $a_beneficiary->getBeneficiariesEmailsAsArray();
                foreach ($beneficiaries_emails as $email) {
                    $new_anonymous_beneficiary = new AnonymousBeneficiary();
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

        } elseif ($form->isSubmitted()) {
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
     * @Route("/add_beneficiary", name="member_add_beneficiary", methods={"GET","POST"})
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
            $email = $this->get('AppBundle\Helper\SwipeCard')->vigenereDecode($code);
            if ($email) {
                $a_beneficiary = $em->getRepository('AppBundle:AnonymousBeneficiary')->findOneBy(array('email' => $email));
            }
            if (!$a_beneficiary) {
                $session->getFlashBag()->add('error', 'Cette url n\'est plus valide');
                return $this->redirectToRoute('homepage');
            }
        }

        if (!$a_beneficiary) {
            throw $this->createAccessDeniedException('Tu cherches ?');
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
        $beneficiary->setFlying(false);
        $beneficiary->setEmail($a_beneficiary->getEmail());

        $form->get('beneficiary')->setData($beneficiary);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $beneficiary = $form->get('beneficiary')->getData();
            $beneficiary->setMembership($member);
            $dispatcher = $this->get('event_dispatcher');

            $event = new FormEvent($form->get('beneficiary')->get('user'), $request);
            $dispatcher->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

            $em->persist($beneficiary);
            $em->remove($a_beneficiary);
            $em->flush();

            $dispatcher->dispatch(BeneficiaryAddEvent::NAME, new BeneficiaryAddEvent($beneficiary));

            $session->getFlashBag()->add('success', 'Merci ' . $beneficiary->getFirstname() . ' ! Ton adhésion est maintenant finalisée');
            return $this->redirectToRoute('fos_user_registration_check_email');

        } elseif ($form->isSubmitted()) {
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
     * @Route("/join", name="member_join", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function joinAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('from_text', AutocompleteBeneficiaryType::class, array('label' => 'Adhérent a joindre'))
            ->add('dest_text', AutocompleteBeneficiaryType::class, array('label' => 'au compte de l\'adhérent'))
            ->add('join', SubmitType::class, array('label' => 'Joindre les deux comptes', 'attr' => array('class' => 'btn')))
            ->getForm();
        $form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();
        $session = new Session();

        if ($form->isSubmitted() && $form->isValid()) {
            $fromMember = $form->get('from_text')->getData()->getMembership();
            $destMember = $form->get('dest_text')->getData()->getMembership();
            if ($fromMember == $destMember) {
                $session->getFlashBag()->add('error', 'Impossible de joindre deux comptes identiques.');
            } else if ($fromMember->getBeneficiaries()->count() >= $this->getParameter('maximum_nb_of_beneficiaries_in_membership')) {
                $session->getFlashBag()->add('error', 'Le compte à lier a déjà le nombre maximum de bénéficiaires.');
            }else if ($destMember->getBeneficiaries()->count() >= $this->getParameter('maximum_nb_of_beneficiaries_in_membership')) {
                $session->getFlashBag()->add('error', 'Le compte de destination a déjà le nombre maximum de bénéficiaires.');
            } else if ($fromMember->getBeneficiaries()->count() + $destMember->getBeneficiaries()->count() > $this->getParameter('maximum_nb_of_beneficiaries_in_membership')) {
                $session->getFlashBag()->add('error', 'La somme des bénéficiaires du compte à lier (' . $destMember->getBeneficiaries()->count() . ') et du compte de destination (' . $fromMember->getBeneficiaries()->count() . ') dépasse le nombre maximum de bénéficiaires.');
            } else {
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

                $session->getFlashBag()->add('success', 'Les deux comptes adhérents ont bien été fusionnés !');

                return $this->redirectToShow($destMember);
            }
        }
        return $this->render('admin/member/join.html.twig', array('form' => $form->createView()));
    }

    /**
     * Office tools: membership creation & management
     *
     * @Route("/office_tools", name="user_office_tools", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_VIEWER')")
     */
    public function officeToolsAction(Request $request)
    {
        $note = new Note();
        $note->setAuthor($this->getCurrentAppUser());
        $note_form = $this->createForm(NoteType::class, $note);
        $note_form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();
        $session = $request->getSession();

        if ($note_form->isSubmitted()) {
            if ($note_form->isValid()) {
                $existing_note = $em->getRepository('AppBundle:Note')->findOneBy(array("subject" => null, "author" => $this->getCurrentAppUser(), "text" => $note->getText()));
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

        $notes = $em->getRepository('AppBundle:Note')->findBy(array("subject" => null));
        $noteEditForms = array();
        $noteDeleteForms = array();
        $new_notes_form = array();
        foreach ($notes as $n) {
            $noteEditForms[$n->getId()] = $this->createForm(NoteType::class, $n, array('action' => $this->generateUrl('note_edit', array('id' => $n->getId()))))->createView();
            $noteDeleteForms[$n->getId()] = $this->createNoteDeleteForm($n)->createView();

            $response_note = clone $note;
            $response_note->setParent($n);
            $response_note_form = $this->createForm(NoteType::class, $response_note,
                array('action' => $this->generateUrl('note_reply', array('id' => $n->getId()))));

            $new_notes_form[$n->getId()] = $response_note_form->createView();
        }

        return $this->render('default/tools/office_tools.html.twig', array(
            'note_form' => $note_form->createView(),
            'notes_form' => $noteEditForms,
            'note_delete_forms' => $noteDeleteForms,
            'new_notes_form' => $new_notes_form,
            'notes' => $notes
        ));
    }

    /**
     * Export all emails of members (including beneficiary)
     *
     * @Route("/emails_csv", name="admin_emails_csv", methods={"GET"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function exportEmails(Request $request)
    {
        $beneficiaries = $this->getDoctrine()->getRepository("AppBundle:Beneficiary")->findAll();
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

    /**
     * @return Response
     */
    public function homepageFreezeAction(): Response
    {
        $member = $this->getUser()->getBeneficiary()->getMembership();

        $freezeChangeForm = $this->createFreezeChangeForm($member);

        return $this->render('member/_partial/frozen.html.twig', array(
            'member' => $member,
            'freeze_change_form' => $freezeChangeForm->createView(),
        ));
    }

    private function createNewBeneficiaryForm(Membership $member)
    {
        $newBeneficiaryAction = $this->generateUrl('member_new_beneficiary', array('member_number' => $member->getMemberNumber()));
        return $this->createForm(BeneficiaryType::class, new Beneficiary(), array('action' => $newBeneficiaryAction));
    }

    /**
     * Creates a form to freeze a member entity.
     *
     * @param Membership $member
     * @return \Symfony\Component\Form\FormInterface
     */
    private function createFreezeForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_freeze', array('id' => $member->getId())))
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Creates a form to unfreeze a member entity.
     *
     * @param Membership $member
     * @return \Symfony\Component\Form\FormInterface
     */
    private function createUnfreezeForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_unfreeze', array('id' => $member->getId())))
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Creates a form to edit member frozen_change
     *
     * @param Membership $member
     * @return \Symfony\Component\Form\FormInterface
     */
    private function createFreezeChangeForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_freeze_change', array('id' => $member->getId())))
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Creates a form to close a member entity.
     *
     * @param Membership $member
     * @return \Symfony\Component\Form\FormInterface
     */
    private function createCloseForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_close', array('id' => $member->getId())))
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Creates a form to open a member entity.
     *
     * @param Membership $member
     * @return \Symfony\Component\Form\FormInterface
     */
    private function createOpenForm(Membership $member)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_open', array('id' => $member->getId())))
            ->setMethod('POST')
            ->getForm();
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
     * Creates a form to delete a time log.
     *
     * @param Membership $member
     * @param TimeLog $timeLog the time_log entity
     *
     * @return \Symfony\Component\Form\FormInterface The form
     */
    private function createTimeLogDeleteForm(Membership $member, TimeLog $timeLog)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('member_timelog_delete', array('id' => $member->getId(), 'timelog_id' => $timeLog->getId())))
            ->setMethod('DELETE')
            ->getForm();
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

    /**
     * Creates a form to free a shift entity (admin side).
     * // TODO: how to avoid having same createShiftFreeAdminForm in ShiftController ?
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftFreeAdminForm(Shift $shift)
    {
        return $this->get('form.factory')->createNamedBuilder('shift_free_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_free_admin', array('id' => $shift->getId())))
            ->add('reason', TextareaType::class, array('required' => false, 'label' => 'Justification éventuelle', 'attr' => array('class' => 'materialize-textarea')))
            ->setMethod('POST')
            ->getForm();
    }

    /**
     * Creates a form to validate / invalidate a shift entity.
     * // TODO: how to avoid having same createShiftValidateInvalidateAdminForm in ShiftController ?
     *
     * @param Shift $shift The shift entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createShiftValidateInvalidateAdminForm(Shift $shift)
    {
        return $this->get('form.factory')->createNamedBuilder('shift_validate_invalidate_forms_' . $shift->getId())
            ->setAction($this->generateUrl('shift_validate_admin', array('id' => $shift->getId())))
            ->add('validate', HiddenType::class, [
                'data' => ($shift->getWasCarriedOut() ? 0 : 1),
            ])
            ->setMethod('POST')
            ->getForm();
    }
}
