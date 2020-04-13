<?php

namespace App\Controller;

use App\Entity\DynamicContent;
use App\Entity\ProcessUpdate;
use App\Entity\Shift;
use App\Form\ProcessUpdateType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Validator\Constraints\Date;

/**
 * Process update controller.
 *
 * @Route("process/updates")
 */
class ProcessUpdateController extends Controller
{

    /**
     * Lists all process updates.
     *
     * @Route("/", name="process_update_list")
     * @Method("GET")
     * @Security("has_role('ROLE_USER')")
     */
    public function listAction(Request $request, EntityManagerInterface $em)
    {
        $processUpdates = $em->getRepository('App:ProcessUpdate')->findBy(array(),array('date'=>'DESC'));

        $delete_forms = array();
        foreach ($processUpdates as $update){
            $delete_forms[$update->getId()] = $this->createDeleteForm($update)->createView();
        }

        $lastShiftDate = null;
        $nbOfNew = null;
        if ($beneficiary = $this->getUser()->getBeneficiary()){
            $lastShift = $em->getRepository(Shift::class)->findLastShifted($beneficiary);
            $lastShiftDate = $this->getUser()->getLastLogin();
            if ($lastShift){
                $lastShiftDate = $lastShift->getStart();
            }
            $nbOfNew = $em->getRepository(ProcessUpdate::class)->countFrom($lastShiftDate);
        }


        return $this->render('process/list.html.twig', array(
            'processUpdates' => $processUpdates,
            'deleteForms' => $delete_forms,
            'lastShiftDate' => $lastShiftDate,
            'nbOfNew' => $nbOfNew,
        ));
    }

    /**
     * @Route("/count_unread", name="process_update_count_unread")
     * @Method("POST")
     * @param Request $request
     * @return Response | JsonResponse
     * @throws
     * @Security("has_role('ROLE_USER')")
     */
    public function countUnreadAction(Request $request, EntityManagerInterface $em)
    {
        if ($request->isXMLHttpRequest()) {
            $date = trim($request->get('date'));
            $date = \DateTime::createFromFormat(\DateTimeInterface::W3C,$date);
            $nbOfNew = $em->getRepository(ProcessUpdate::class)->countFrom($date);

            return new JsonResponse(array('count' => $nbOfNew,'date' => $date->format(\DateTimeInterface::W3C)));
        }
        return new Response('This is not ajax!', 400);
    }

    /**
     * Create a process update
     *
     * @Route("/new", name="process_update_new")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function newAction(Request $request, EntityManagerInterface $em)
    {
        $emailTemplate = new ProcessUpdate();
        $form = $this->createForm(ProcessUpdateType::class, $emailTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();

            $emailTemplate->setDate(new \DateTime());
            $emailTemplate->setAuthor($this->getUser());

            $em->persist($emailTemplate);
            $em->flush();
            $session->getFlashBag()->add('success', "Mise à jour de procédure créée");
            return $this->redirectToRoute('process_update_list');

        }

        return $this->render('process/new.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * Edit a process update
     *
     * @Route("/{id}/edit", name="process_update_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function editAction(Request $request, ProcessUpdate $processUpdate, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('edit', $processUpdate);

        $form = $this->createForm(ProcessUpdateType::class, $processUpdate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $em->persist($processUpdate);
            $em->flush();
            $session->getFlashBag()->add('success', 'Mise à jour de procédure éditée');
            return $this->redirectToRoute('process_update_list');

        }

        return $this->render('process/edit.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * Creates a form to delete an entity.
     *
     * @param ProcessUpdate $processUpdate the entity
     *
     * @return \Symfony\Component\Form\FormInterface The form
     */
    private function createDeleteForm(ProcessUpdate $processUpdate)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('process_update_delete', array('id' => $processUpdate->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Delete a process update.
     *
     * @Route("/{id}", name="process_update_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, ProcessUpdate $processUpdate, EntityManagerInterface $em)
    {

        $this->denyAccessUnlessGranted('delete', $processUpdate);

        $form = $this->createDeleteForm($processUpdate);
        $form->handleRequest($request);
        $session = new Session();

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($processUpdate);
            $em->flush();
            $session->getFlashBag()->add('success', "l'entrée a bien été supprimée");
        }

        return $this->redirectToRoute('process_update_list');
    }

}
