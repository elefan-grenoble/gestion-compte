<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\AnonymousBeneficiary;
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
        $user = $em->getRepository('AppBundle:User')->findByRole('ROLE_SUPER_ADMIN');

        if (count($user) > 0) { //main super admin exist
            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $form = $this->createFormBuilder()
                    ->add('username', TextType::class, array('label' => "Nom d'utilisateur"))
                    ->add('password', PasswordType::class, array('label' => "Mot de passe"))
                    ->add('email', EmailType::class, array('label' => "Adresse email"))
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

                    $new_admin = new User();
                    $new_admin->setEmail($form->get('email')->getData());
                    $new_admin->setPlainPassword($form->get('password')->getData());
                    $new_admin->setUsername($form->get('username')->getData());
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
            $admin->setPlainPassword("password");
            $admin->setUsername("admin");
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
     * @Route("/quick_new", name="user_quick_new")
     * @Method({"GET", "POST"})
     */
    public function quickNewAction(Request $request, \Swift_Mailer $mailer)
    {
        $ab = new AnonymousBeneficiary();

        $form = $this->createForm('AppBundle\Form\AnonymousBeneficiaryType', $ab);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $ab->setRegistrar($this->getCurrentAppUser());

            $em = $this->getDoctrine()->getManager();
            $em->persist($ab);
            $em->flush();

            $welcome = (new \Swift_Message('Bienvenu à l\'éléfàn, tu te présentes ?'))
                ->setFrom($this->container->getParameter('shift_mailer_user'))
                ->setTo($ab->getEmail())
                ->setBody(
                    $this->renderView(
                        'emails/needInfo.html.twig'
                    ),
                    'text/html'
                );
            $mailer->send($welcome);

            $session = new Session();
            $session->getFlashBag()->add('success', 'La nouvelle adhésion a bien été prise en compte !');

            return $this->redirectToRoute('user_office_tools');
        }

        return $this->render('user/quick_new.html.twig', array(
            'anonymous_beneficiary' => $ab,
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
        $membership = $this->getCurrentAppUser()->getBeneficiary()->getMembership();
        if (!$membership->canRegister()) {
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
