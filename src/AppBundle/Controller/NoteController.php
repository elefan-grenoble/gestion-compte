<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Client;
use AppBundle\Entity\Note;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
use AppBundle\Entity\User;
use AppBundle\Form\BeneficiaryType;
use AppBundle\Form\NoteType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Twig\Sandbox\SecurityError;

/**
 * Note controller.
 *
 * @Route("note")
 */
class NoteController extends Controller
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
     * reply to a note
     *
     * @Route("/note/{id}/reply", name="note_reply", methods={"POST"})
     * @Security("has_role('ROLE_USER_VIEWER')")
     */
    public function noteReplyAction(Request $request, Note $note)
    {
        $new_note = new Note();
        $new_note->setParent($note);
        $new_note->setAuthor($this->getCurrentAppUser());
        $new_note->setSubject($note->getSubject());

        $note_form = $this->createForm(NoteType::class, $new_note);
        $note_form->handleRequest($request);

        if ($note_form->isSubmitted() && $note_form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();
            $em->persist($new_note);
            $em->flush();
            if ($new_note->getSubject()) {
                $session->getFlashBag()->add('success', 'réponse enregistrée');
                return $this->redirectToShow($note->getSubject());
            }
            $session->getFlashBag()->add('success', 'Post-it réponse enregistré');
        }
        return $this->redirectToRoute('user_office_tools');
    }

    /**
     * edit a note
     *
     * @Route("/note/{id}/edit", name="note_edit", methods={"GET","POST"})
     */
    public function noteEditAction(Request $request, Note $note)
    {
        $this->denyAccessUnlessGranted('edit', $note);

        $note_form = $this->createForm(NoteType::class, $note);
        $note_form->handleRequest($request);

        if ($note_form->isSubmitted() && $note_form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();
            $em->persist($note);
            $em->flush();
            if ($note->getSubject()) {
                $session->getFlashBag()->add('success', 'note éditée');
                return $this->redirectToShow($note->getSubject());
            }
            $session->getFlashBag()->add('success', 'Post-it édité');
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
     * Delete a note.
     *
     * @Route("/note/{id}", name="note_delete", methods={"DELETE"})
     */
    public function deleteNoteAction(Request $request, Note $note)
    {
        $this->denyAccessUnlessGranted('delete', $note);

        $form = $this->createNoteDeleteForm($note);
        $form->handleRequest($request);
        $session = new Session();

        $member = $note->getSubject();

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($note);
            $em->flush();
            $session->getFlashBag()->add('success', "la note a bien été supprimée");
        }

        if ($member) {
            return $this->redirectToShow($member);
        }
        return $this->redirectToRoute('user_office_tools');
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
