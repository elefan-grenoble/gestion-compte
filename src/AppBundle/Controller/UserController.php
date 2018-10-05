<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Client;
use AppBundle\Entity\Note;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
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
use Twig\Sandbox\SecurityError;

/**
 * User controller.
 *
 * @Route("user")
 */
class UserController extends Controller
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
     * @Route("/install_admin", name="user_install_admin")
     * @Method({"GET","POST"})
     */
    public function installAdminAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $session = new Session();
        $user = $em->getRepository('AppBundle:User')->findOneBy(array("member_number" => 0));

        if ($user) { //main super admin exist
            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $form = $this->createFormBuilder()
                    ->add('username', TextType::class, array('label' => "Nom d'utilisateur"))
                    ->add('password', PasswordType::class, array('label' => "Mot de passe"))
                    ->add('email', EmailType::class, array('label' => "Adresse email", "required" => false))
                    ->getForm();
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {

                    $existing_user = $em->getRepository('AppBundle:User')->findOneBy(array("username" => $form->get('username')->getData()));
                    if ($existing_user) {
                        $session->getFlashBag()->add('error', 'A user with this username already exist !');
                        return $this->render('admin/user/new_admin.html.twig', array(
                            'form' => $form->createView(),
                        ));
                    }
                    $existing_user = $em->getRepository('AppBundle:User')->findOneBy(array("email" => $form->get('email')->getData()));
                    if ($existing_user) {
                        $session->getFlashBag()->add('error', 'A user with this email already exist !');
                        $session->getFlashBag()->add('warning', 'Put an empty email, we will provide one for your');
                        return $this->render('admin/user/new_admin.html.twig', array(
                            'form' => $form->createView(),
                        ));
                    }
                    $last_admin_recorded = $em->getRepository('AppBundle:User')->findBy(array(), array('member_number' => 'ASC'), 1);
                    $lowest_member_number_yet = $last_admin_recorded[0]->getMemberNumber();

                    $email = $form->get('email')->getData();
                    if (!$form->get('email')->getData()) {
                        $email = "membres+admin" . ($lowest_member_number_yet - 1) . "@lelefan.org";//todo put this in conf
                    }

                    $new_admin = new User();
                    $new_admin->setEmail($email);
                    $new_admin->setPlainPassword($form->get('password')->getData());
                    $new_admin->setUsername($form->get('username')->getData());
                    $new_admin->setMemberNumber($lowest_member_number_yet - 1);
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
            $admin->setEmail("admin@lelefan.org"); //todo put this in conf
            $admin->setPlainPassword("password");
            $admin->setUsername("babar"); //todo put this in conf
            $admin->setMemberNumber(0);
            $admin->setEnabled(true);
            $admin->addRole('ROLE_SUPER_ADMIN');
            $em->persist($admin);
            $em->flush();

            $session->getFlashBag()->add('success', 'user super admin created with success !');

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
        $this->denyAccessUnlessGranted('create', $this->getCurrentAppUser());
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
        $registration->setRegistrar($this->getCurrentAppUser());
        $user->addRegistration($registration);

        $form = $this->createForm('AppBundle\Form\UserType', $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $user->getMainBeneficiary()->getEmail();
            if (!filter_var($email, FILTER_SANITIZE_EMAIL) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $session->getFlashBag()->add('error', 'cet adresse email n\'est pas valide');
            } else {
                $other_user = $em->getRepository('AppBundle:User')->findOneBy(array("email" => $email));
                if ($other_user) {
                    $session->getFlashBag()->add('error', 'Oups, un membres utilise déjà cet email ! (' . '#' . $other_user->getMemberNumber() . " " . $other_user->getFirstName() . " " . $other_user->getLastName()[0] . ')');
                } else {
                    $beneficiary = $em->getRepository('AppBundle:Beneficiary')->findOneBy(array("email" => $email));
                    if ($beneficiary) {
                        $session->getFlashBag()->add('error', 'Oups, un beneficiaire est déjà enregistré avec cet email !(' . '#' . $beneficiary->getUser()->getMemberNumber() . " " . $beneficiary->getUser()->getFirstName() . " " . $beneficiary->getUser()->getLastName()[0] . ')');
                    } else {
                        $username = User::makeUsername($user->getFirstname(), $user->getLastname());
                        $qb = $em->createQueryBuilder();
                        $users = $qb->select('u')->from('AppBundle\Entity\User', 'u')
                            ->where($qb->expr()->like('u.username', $qb->expr()->literal($username . '%')))
                            ->getQuery()
                            ->getResult();
                        $already_registred = (isset($usernames[$username])) ? $usernames[$username] : 0;
                        if (count($users) || $already_registred) {
                            $username = User::makeUsername($user->getFirstname(), $user->getLastname(), count($users) + 1 + $already_registred);
                        }
                        $user->setUsername($username);
                        $password = User::randomPassword();
                        $user->setPassword($password);
                        $user->getMainBeneficiary()->setUser($user);
                        $user->setEmail($user->getMainBeneficiary()->getEmail());

                        if (!$user->getLastRegistration()->getRegistrar())
                            $user->getLastRegistration()->setRegistrar($this->getCurrentAppUser());

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

                        return $this->redirectToShow($user);
                    }
                }
            }
        } elseif ($form->isSubmitted()) {
            foreach ($this->getErrorMessages($form) as $key => $errors) {
                foreach ($errors as $error)
                    $session->getFlashBag()->add('error', $key . " : " . $error);
            }
        }

        return $this->render('user/new.html.twig', array(
            'user' => $user,
            'form' => $form->createView(),
        ));
    }

    /**
     * remove role of user
     *
     * @Route("/{id}/removeRole/{role}", name="user_remove_role")
     * @Method({"GET"})
     */
    public function removeRoleAction(User $user, $role)
    {
        $this->denyAccessUnlessGranted('role_remove', $user);
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        if (!$user->hasRole($role)) {
            $session->getFlashBag()->add('success', 'Cet utilisateur ne possède pas le role ' . $role);
            return $this->redirectToShow($user);
        }
        $user->removeRole($role);
        $em->persist($user);
        $em->flush();
        $session->getFlashBag()->add('success', 'Le Role ' . $role . ' a bien été retiré');
        return $this->redirectToShow($user);
    }

    /**
     * add role of user
     *
     * @Route("/{id}/addRole/{role}", name="user_add_role")
     * @Method({"GET"})
     * @param User $user
     * @param $role
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function addRoleAction(User $user, $role)
    {
        $this->denyAccessUnlessGranted('role_add', $user);
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        if ($user->hasRole($role)) {
            $session->getFlashBag()->add('success', 'Cet utilisateur possède déjà le role ' . $role);
            return $this->redirectToShow($user);
        }
        $user->addRole($role);
        $em->persist($user);
        $em->flush();
        $session->getFlashBag()->add('success', 'Le Role ' . $role . ' a bien été ajouté');
        return $this->redirectToShow($user);
    }

    /**
     * self_register
     *
     * @Route("/self_register", name="user_self_register")
     * @Method({"GET"})
     */
    public function selfRegistrationAction()
    {
        $session = new Session();
        if (!$this->getCurrentAppUser()->canRegister()) {
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
            $client = $this->getDoctrine()->getManager()->getRepository('AppBundle:Client')->find($client_id);
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

        return $this->redirectToRoute('fos_user_profile_edit');
    }

    /**
     * Deletes a user entity.
     *
     * @Route("/delete/{id}", name="user_delete")
     * @Method("DELETE")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @param Request $request
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Request $request, User $user)
    {
        $form = $this->createDeleteForm($user);
        $form->handleRequest($request);

        $session = new Session();
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($user);
            $em->flush();

            $session->getFlashBag()->add('success', "L'utilisateur a bien été supprimé");
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
