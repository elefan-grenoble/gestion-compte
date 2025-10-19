<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\AnonymousBeneficiary;
use App\Entity\Beneficiary;
use App\Entity\Client;
use App\Entity\Note;
use App\Entity\Registration;
use App\Entity\Shift;
use App\Entity\TimeLog;
use App\Entity\User;
use App\Event\AnonymousBeneficiaryCreatedEvent;
use App\Event\AnonymousBeneficiaryRecallEvent;
use App\Form\AnonymousBeneficiaryType;
use App\Form\BeneficiaryType;
use App\Form\NoteType;
use App\Form\UserAdminType;
use App\Service\MembershipService;
use FOS\UserBundle\Event\UserEvent;
use FOS\UserBundle\FOSUserEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Twig\Sandbox\SecurityError;

/**
 * User controller.
 *
 * @Route("user")
 */
class UserController extends AbstractController
{
    private $_current_app_user;

    public function getCurrentAppUser()
    {
        if (!$this->_current_app_user) {
            $this->_current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        }
        return $this->_current_app_user;
    }

    /**
     * install admin
     *
     * @Route("/install_admin", name="user_install_admin", methods={"GET","POST"})
     */
    public function installAdminAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $session = new Session();
        $user = $em->getRepository('App:User')->findByRole('ROLE_SUPER_ADMIN');

        if (count($user) > 0) { //main super admin exist
            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $new_admin = new User();
                $form = $this->createForm(UserAdminType::class, $new_admin);
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    $new_admin->setEnabled(true);
                    $new_admin->addRole('ROLE_ADMIN');
                    $em->persist($new_admin);
                    $em->flush();

                    $session->getFlashBag()->add('success', 'new user admin created with success !');
                    return $this->redirectToRoute('admin');
                } else {
                    return $this->render('admin/user/new_admin.html.twig', array(
                        'form' => $form->createView(),
                    ));
                }
            } else {
                $session->getFlashBag()->add('error', 'Main super admin user already exist !');
                return $this->redirectToRoute('homepage');
            }
        } else { //main super user not created yet
            $admin = new User();
            $admin->setEmail($this->getParameter('emails.admin')['address']);
            $admin->setPlainPassword($this->getParameter('super_admin.initial_password'));
            $admin->setUsername($this->getParameter('super_admin.username'));
            $admin->setEnabled(true);
            $admin->addRole('ROLE_SUPER_ADMIN');
            $em->persist($admin);
            $em->flush();

            $session->getFlashBag()->add('success', 'user super admin created with success !');

            return $this->redirectToRoute('homepage');
        }

    }

    /**
     * change_password
     *
     * @Route("/change_password", name="user_change_password", methods={"GET","POST"})
     * @param Request $request
     * @return Response
     */
    public function changePasswordAction(Request $request, EventDispatcherInterface $event_dispatcher)
    {
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        $formBuilder = $this->createFormBuilder();
        $formBuilder->add('password',PasswordType::class,array('label'=>'Un mot de passe','trim'=>true));
        $formBuilder->add('password_repeat',PasswordType::class,array('label'=>'Le même une deuxième fois','trim'=>true));
        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($form->getData()['password'] === $form->getData()['password_repeat']){
                $this->getUser()->setPlainPassword($form->getData()['password']);

                $em = $this->getDoctrine()->getManager();
                $em->persist($this->getUser());
                $em->flush();

                $event = new UserEvent($this->getUser(), $request);
                $event_dispatcher->dispatch(FOSUserEvents::USER_PASSWORD_CHANGED, $event);

                $session = new Session();
                $session->getFlashBag()->add('success', 'Mot de passe enregistré, merci !');

                return $this->redirectToRoute('homepage');
            }else{
                $session = new Session();
                $session->getFlashBag()->add('error','Attention : tes deux mots de passe ne sont pas identique !');
            }

        }

        return $this->render('user/change_password.html.twig',array('form'=>$form->createView()));
    }

    /**
     * Creates a new user entity
     *
     * @Route("/quick_new", name="user_quick_new", methods={"GET","POST"})
     * @Security("is_granted('ROLE_USER_VIEWER')")
     */
    public function quickNewAction(Request $request, MailerInterface $mailer, EventDispatcherInterface $event_dispatcher)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $ab = new AnonymousBeneficiary();

        $form = $this->createForm(AnonymousBeneficiaryType::class, $ab);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ab->setRegistrar($this->getCurrentAppUser());
            $em->persist($ab);
            $em->flush();

            $event_dispatcher->dispatch(AnonymousBeneficiaryCreatedEvent::NAME, new AnonymousBeneficiaryCreatedEvent($ab));

            $session->getFlashBag()->add('success', 'La nouvelle adhésion a bien été prise en compte !');
            return $this->redirectToRoute('user_quick_new');
        }

        return $this->render('user/quick_new.html.twig', array(
            'anonymous_beneficiary' => $ab,
            'form' => $form->createView(),
        ));
    }

    /**
     * remove role of user
     *
     * @Route("/{id}/removeRole/{role}", name="user_remove_role", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * @param User $user
     * @param $role
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function removeRoleAction(User $user, $role)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->getCurrentAppUser();

        // cannot remove a nonexistant role
        if (!$user->hasRole($role)) {
            $session->getFlashBag()->add('warning', $user . ' ne possède pas le rôle ' . $role);
            return $this->redirectToShow($user);
        }
        // only ROLE_SUPER_ADMIN can remove ROLE_ADMIN to users
        if ($role == 'ROLE_ADMIN' && !$current_user->hasRole('ROLE_SUPER_ADMIN')) {
            $session->getFlashBag()->add('warning', 'Vous n\'avez pas les droits pour retirer le rôle ' . $role);
            return $this->redirectToShow($user);
        }

        $user->removeRole($role);
        $em->persist($user);
        $em->flush();

        $session->getFlashBag()->add('success', 'Le rôle ' . $role . ' a bien été retiré à ' . $user);
        return $this->redirectToShow($user);
    }

    /**
     * add role of user
     *
     * @Route("/{id}/addRole/{role}", name="user_add_role", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * @param User $user
     * @param $role
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function addRoleAction(User $user, $role)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->getCurrentAppUser();

        // cannot add an existing role
        if ($user->hasRole($role)) {
            $session->getFlashBag()->add('warning', $user . ' possède déjà le rôle ' . $role);
            return $this->redirectToShow($user);
        }
        // only ROLE_SUPER_ADMIN can add ROLE_ADMIN to users
        if ($role == 'ROLE_ADMIN' && !$current_user->hasRole('ROLE_SUPER_ADMIN')) {
            $session->getFlashBag()->add('warning', 'Vous n\'avez pas les droits pour ajouter le rôle ' . $role);
            return $this->redirectToShow($user);
        }

        $user->addRole($role);
        $em->persist($user);
        $em->flush();

        $session->getFlashBag()->add('success', 'Le rôle ' . $role . ' a bien été ajouté à ' . $user);
        return $this->redirectToShow($user);
    }

    /**
     * self_register
     *
     * @Route("/self_register", name="user_self_register", methods={"GET"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function selfRegistrationAction(MembershipService $membership_service)
    {
        $session = new Session();
        $membership = $this->getCurrentAppUser()->getBeneficiary()->getMembership();
        if (!$membership_service->canRegister($membership)) {
            $session->getFlashBag()->add('warning', 'Pas besoin de ré-adhérer pour le moment :)');
            return $this->redirectToRoute('homepage');
        }
        return $this->render('user/self_register.html.twig');
    }

    /**
     * remove client from user
     *
     * @Route("/{username}/remove_client/{client_id}", name="user_client_remove", methods={"GET","POST"})
     */
    public function removeClientUserAction(User $user, $client_id)
    {
        $session = new Session();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN') && ($this->getCurrentAppUser() != $user)) {
            throw $this->createAccessDeniedException();
        }
        if ($client_id) {
            $client = $this->getDoctrine()->getManager()->getRepository('App:Client')->find($client_id);
            if ($client->getId()) {
                if ($user->getClients()->contains($client)) {
                    $user->removeClient($client);
                    $this->getDoctrine()->getManager()->flush($user);
                    $session->getFlashBag()->add('success', 'Le service a bien été supprimé de votre compte');
                } else {
                    $session->getFlashBag()->add('error', 'ce client n\'est pas associé à votre compte');
                }
            } else {
                $session->getFlashBag()->add('error', 'ce client n\'existe pas');
            }
        } else {
            $session->getFlashBag()->add('error', 'ce client n\'existe pas');
        }

        return $this->redirectToRoute('fos_user_profile_show');
    }

    /**
     * Deletes a user entity
     *
     * @Route("/{id}/delete", name="user_delete", methods={"DELETE"})
     * @Security("is_granted('ROLE_SUPER_ADMIN')")
     * @param Request $request
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Request $request, User $user)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createDeleteForm($user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($user);
            $em->flush();

            $session->getFlashBag()->add('success', "L'utilisateur a bien été supprimé");
        }

        return $this->redirectToRoute('user_index');
    }

    /**
     * List all unconfirmed users
     *
     * @Route("/pre_users", name="pre_user_index", methods={"GET"})
     * @Security("is_granted('ROLE_USER_VIEWER')")
     */
    public function preUsersAction()
    {
        /** @var AnonymousBeneficiary[] $anonymousBeneficiaries */
        $anonymousBeneficiaries = $this->getDoctrine()->getRepository(AnonymousBeneficiary::class)->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        return $this->render('admin/pre_user/list.html.twig', array(
            'anonymousBeneficiaries' => $anonymousBeneficiaries,
        ));
    }

    /**
     * Recall unconfirmed user
     *
     * @Route("/pre_users/{id}/recall", name="pre_user_recall", methods={"GET"})
     * @Security("is_granted('ROLE_USER_VIEWER')")
     */
    public function quickNewRecallAction(Request $request, AnonymousBeneficiary $anonymousBeneficiary, EventDispatcherInterface $event_dispatcher)
    {
        $event_dispatcher->dispatch(AnonymousBeneficiaryRecallEvent::NAME, new AnonymousBeneficiaryRecallEvent($anonymousBeneficiary));

        $anonymousBeneficiary->setRecallDate(new \DateTime());
        $em = $this->getDoctrine()->getManager();
        $em->persist($anonymousBeneficiary);
        $em->flush();

        $session = new Session();
        $session->getFlashBag()->add('success', 'La relance a été envoyée !');

        $referer = $request->headers->get('referer');

        return new RedirectResponse($referer);
    }

    /**
     * Delete unconfirmed user
     * 
     * @Route("/pre_users/{id}/delete", name="pre_user_delete", methods={"GET"})
     * @Security("is_granted('ROLE_USER_MANAGER')")
     */
    public function preUsersDeleteAction(AnonymousBeneficiary $anonymousBeneficiary, SessionInterface $session)
    {
        $this->getDoctrine()->getManager()->remove($anonymousBeneficiary);
        $this->getDoctrine()->getManager()->flush();

        $session->getFlashBag()->add('success', "La pré-adhésion a bien été supprimée");

        return $this->redirectToRoute('pre_user_index');
    }

    /**
     * Creates a form to delete a user entity
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
            ->getForm();
    }

    private function getErrorMessages(Form $form)
    {
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

    private function redirectToShow(User $user)
    {
        if ($user->getBeneficiary()) {
            $session = new Session();
            $memberNumber = $user->getBeneficiary()->getMembership()->getMemberNumber();
            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
                return $this->redirectToRoute('member_show', array('member_number' => $memberNumber));
            else
                return $this->redirectToRoute('member_show', array('member_number' => $memberNumber, 'token' => $user->getTmpToken($session->get('token_key') . $this->getCurrentAppUser()->getUsername())));
        } else {
            return $this->redirectToRoute("homepage");
        }
    }
}
