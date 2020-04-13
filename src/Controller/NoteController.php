<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Client;
use App\Entity\Note;
use App\Entity\Registration;
use App\Entity\Shift;
use App\Entity\TimeLog;
use App\Entity\User;
use App\Form\BeneficiaryType;
use App\Form\NoteType;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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
 * Note controller.
 *
 * @Route("note")
 */
class NoteController extends Controller
{
    /**
     * reply to a note
     *
     * @Route("/note/{id}/reply", name="note_reply")
     * @Method({"POST"})
     */
    public function noteReplyAction(Request $request, Note $note, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('access_tools', $this->getUser());

        $new_note = new Note();
        $new_note->setParent($note);
        $new_note->setAuthor($this->getUser());
        $new_note->setCreatedAt(new \DateTime());
        $new_note->setSubject($note->getSubject());

        $note_form = $this->createForm(NoteType::class, $new_note);
        $note_form->handleRequest($request);

        if ($note_form->isSubmitted() && $note_form->isValid()) {
            $session = new Session();
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
     * @Route("/note/{id}/edit", name="note_edit")
     * @Method({"GET","POST"})
     */
    public function noteEditAction(Request $request, Note $note, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('edit', $note);

        $note_form = $this->createForm(NoteType::class, $note);
        $note_form->handleRequest($request);

        if ($note_form->isSubmitted() && $note_form->isValid()) {
            $session = new Session();
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
     * @Route("/note/{id}", name="note_delete")
     * @Method("DELETE")
     */
    public function deleteNoteAction(Request $request, Note $note, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('delete', $note);

        $form = $this->createNoteDeleteForm($note);
        $form->handleRequest($request);
        $session = new Session();

        $member = $note->getSubject();

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($note);
            $em->flush();
            $session->getFlashBag()->add('success', "la note a bien été supprimée");
        }

        if ($member) {
            return $this->redirectToShow($member);
        }
        return $this->redirectToRoute('user_office_tools');
    }

    private function redirectToShow(Membership $member, AuthorizationCheckerInterface $authorizationChecker)
    {
        $user = $member->getMainBeneficiary()->getUser(); // FIXME
        $session = new Session();
        if ($authorizationChecker->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber()));
        else
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber(), 'token' => $user->getTmpToken($session->get('token_key') . $this->getUser()->getUsername())));
    }
}
