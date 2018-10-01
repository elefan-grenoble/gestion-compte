<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Client;
use AppBundle\Entity\Membership;
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
 * Beneficiary controller.
 *
 * @Route("beneficiary")
 */
class BeneficiaryController extends Controller
{
    private $_current_app_user;

    public function getCurrentAppUser(){
        if (!$this->_current_app_user){
            $this->_current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        }
        return $this->_current_app_user;
    }

    /**
     * Displays a form to edit an existing user entity.
     *
     * @Route("/{id}/edit", name="beneficiary_edit")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @param Beneficiary $beneficiary
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editBeneficiaryAction(Request $request, Beneficiary $beneficiary)
    {
        $session = new Session();
        $member = $beneficiary->getMembership();
        $this->denyAccessUnlessGranted('edit', $member);

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

            return $this->redirectToShow($member);
        }

        return $this->render('beneficiary/edit_beneficiary.html.twig', array(
            'beneficiary' => $beneficiary,
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Deletes a beneficiary entity.
     *
     * @Route("/beneficiary/{id}", name="beneficiary_delete")
     * @Method("DELETE")
     * @param Request $request
     * @param Beneficiary $beneficiary
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteBeneficiaryAction(Request $request, Beneficiary $beneficiary)
    {
        $user = $beneficiary->getUser();

        $this->denyAccessUnlessGranted('edit', $user);

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('beneficiary_delete', array('id' => $beneficiary->getId())))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($beneficiary);
            $em->flush();
        }

        return $this->redirectToShow($user);
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


    private function redirectToEdit(Membership $member)
    {
        $user = $member->getMainBeneficiary()->getUser(); // FIXME
        $session = new Session();
        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('member_edit', array('member_number' => $member->getMemberNumber()));
        else
            return $this->redirectToRoute('member_edit', array('member_number' => $member->getMemberNumber(), 'token' => $user->getTmpToken($session->get('token_key') . $this->getCurrentAppUser()->getUsername())));
    }

    private function redirectToShow(Membership $member)
    {
        $user = $member->getMainBeneficiary()->getUser(); // FIXME
        $session = new Session();
        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber()));
        else
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber(), 'token' => $user->getTmpToken($session->get('token_key') . $this->getCurrentAppUser()->getUsername())));
    }
}
