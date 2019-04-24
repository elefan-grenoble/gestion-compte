<?php

namespace AppBundle\Controller;

use AppBundle\Entity\DynamicContent;
use AppBundle\Entity\Note;
use AppBundle\Entity\ProcessUpdate;
use AppBundle\Form\ProcessUpdateType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Process update controller.
 *
 * @Route("process")
 */
class ProcessUpdateController extends Controller
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
     * Lists all process updates.
     *
     * @Route("/updates/", name="process_update_list")
     * @Method("GET")
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function listAction(Request $request)
    {
        //todo paginate

        $em = $this->getDoctrine()->getManager();
        $processUpdates = $em->getRepository('AppBundle:ProcessUpdate')->findAll();

        $delete_forms = array();
        foreach ($processUpdates as $update){
            $delete_forms[$update->getId()] = $this->createDeleteForm($update)->createView();
        }

        return $this->render('process/list.html.twig', array(
            'processUpdates' => $processUpdates,
            'deleteForms' => $delete_forms,
        ));
    }

    /**
     * Create a process update
     *
     * @Route("/updates/new", name="process_update_new")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function newAction(Request $request)
    {
        $emailTemplate = new ProcessUpdate();
        $form = $this->createForm(ProcessUpdateType::class, $emailTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();

            $emailTemplate->setDate(new \DateTime());
            $emailTemplate->setAuthor($this->getCurrentAppUser());

            $em = $this->getDoctrine()->getManager();
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
     * @Route("/updates/{id}/edit", name="process_update_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function editAction(Request $request, ProcessUpdate $processUpdate)
    {
        $this->denyAccessUnlessGranted('edit', $processUpdate);

        $form = $this->createForm(ProcessUpdateType::class, $processUpdate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();
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
     * @Route("/updates/{id}", name="process_update_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, ProcessUpdate $processUpdate)
    {

        $form = $this->createDeleteForm($processUpdate);
        $form->handleRequest($request);
        $session = new Session();

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($processUpdate);
            $em->flush();
            $session->getFlashBag()->add('success', "l'entrée a bien été supprimée");
        }

        return $this->redirectToRoute('process_update_list');
    }

}
