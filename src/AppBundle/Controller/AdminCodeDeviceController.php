<?php

namespace AppBundle\Controller;

use AppBundle\Entity\CodeDevice;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

/**
 * Codedevice controller.
 *
 * @Route("admin/codedevice")
 */
class AdminCodeDeviceController extends Controller
{
    /**
     * Lists all codeDevice entities.
     *
     * @Route("/", name="admin_codedevice_index")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $codeDevices = $em->getRepository('AppBundle:CodeDevice')->findAll();

        return $this->render('admin/codedevice/index.html.twig', array(
            'codeDevices' => $codeDevices,
        ));
    }

    /**
     * Creates a new codeDevice entity.
     *
     * @Route("/new", name="admin_codedevice_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $codeDevice = new Codedevice();
        $form = $this->createForm('AppBundle\Form\CodeDeviceType', $codeDevice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($codeDevice);
            $em->flush();

            $session->getFlashBag()->add('success', 'L\'équipement a bien été ajouté');
            return $this->redirectToRoute('admin_codedevice_index');
        }

        return $this->render('admin/codedevice/new.html.twig', array(
            'codeDevice' => $codeDevice,
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing codeDevice entity.
     *
     * @Route("/{id}/edit", name="admin_codedevice_edit")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request, CodeDevice $codeDevice)
    {
        $session = new Session();
        $deleteForm = $this->createDeleteForm($codeDevice);
        $editForm = $this->createForm('AppBundle\Form\CodeDeviceType', $codeDevice);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            $session->getFlashBag()->add('success', 'L\'équipement a bien été modifié');
            return $this->redirectToRoute('admin_codedevice_index');
        }

        return $this->render('admin/codedevice/edit.html.twig', array(
            'codeDevice' => $codeDevice,
            'form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a codeDevice entity.
     *
     * @Route("/{id}", name="admin_codedevice_delete")
     * @Method("DELETE")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function deleteAction(Request $request, CodeDevice $codeDevice)
    {
        $session = new Session();
        $form = $this->createDeleteForm($codeDevice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($codeDevice);
            $em->flush();
            $session->getFlashBag()->add('success', 'L\'équipement a bien été supprimé');
        }

        return $this->redirectToRoute('admin_codedevice_index');
    }

    /**
     * Creates a form to delete a codeDevice entity.
     *
     * @param CodeDevice $codeDevice The codeDevice entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(CodeDevice $codeDevice)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_codedevice_delete', array('id' => $codeDevice->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
