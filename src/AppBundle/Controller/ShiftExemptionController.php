<?php

namespace AppBundle\Controller;

use AppBundle\Entity\ShiftExemption;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Shiftexemption controller.
 *
 * @Route("admin/shiftexemption")
 */
class ShiftExemptionController extends Controller
{
    /**
     * Lists all shiftExemption entities.
     *
     * @Route("/", name="admin_shiftexemption_index", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $shiftExemptions = $em->getRepository('AppBundle:ShiftExemption')->findAll();

        return $this->render('admin/shiftexemption/index.html.twig', array(
            'shiftExemptions' => $shiftExemptions,
        ));
    }

    /**
     * Creates a new shiftExemption entity.
     *
     * @Route("/new", name="admin_shiftexemption_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $shiftExemption = new Shiftexemption();
        $form = $this->createForm('AppBundle\Form\ShiftExemptionType', $shiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($shiftExemption);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le nouveau motif d\'exemption a été créé !');
            return $this->redirectToRoute('admin_shiftexemption_index');
        }

        return $this->render('admin/shiftexemption/new.html.twig', array(
            'shiftExemption' => $shiftExemption,
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing shiftExemption entity.
     *
     * @Route("/{id}/edit", name="admin_shiftexemption_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request, ShiftExemption $shiftExemption)
    {
        $session = new Session();
        $deleteForm = $this->createDeleteForm($shiftExemption);
        $editForm = $this->createForm('AppBundle\Form\ShiftExemptionType', $shiftExemption);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            $session->getFlashBag()->add('success', 'Le motif d\'exemption a bien été modifié !');
            return $this->redirectToRoute('admin_shiftexemption_index');
        }

        return $this->render('admin/shiftexemption/edit.html.twig', array(
            'shiftExemption' => $shiftExemption,
            'form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a shiftExemption entity.
     *
     * @Route("/{id}", name="admin_shiftexemption_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function deleteAction(Request $request, ShiftExemption $shiftExemption)
    {
        $session = new Session();
        $form = $this->createDeleteForm($shiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($shiftExemption);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le motif d\'exemption bien été supprimé !');
        }

        return $this->redirectToRoute('admin_shiftexemption_index');
    }

    /**
     * Creates a form to delete a shiftExemption entity.
     *
     * @param ShiftExemption $shiftExemption The shiftExemption entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(ShiftExemption $shiftExemption)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_shiftexemption_delete', array('id' => $shiftExemption->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
