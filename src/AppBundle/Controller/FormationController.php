<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Formation;
use AppBundle\Form\FormationType;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("admin/formations")
 * @Security("has_role('ROLE_ADMIN')")
 */
class FormationController extends Controller
{

    /**
     * Formations list
     *
     * @Route("/", name="admin_formations")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction()
    {
        $formations = $this->getDoctrine()->getManager()->getRepository('AppBundle:Formation')->findAll();
        return $this->render('admin/formation/list.html.twig',array('formations'=>$formations));
    }

    /**
     * Formation new
     *
     * @Route("/new", name="formation_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();

        $formation = new Formation();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($formation);
            $em->flush();

            $session->getFlashBag()->add('success', 'La nouvelle formation a bien été créée !');

            return $this->redirectToRoute('admin_formations');

        }

        return $this->render('admin/formation/new.html.twig', array(
            'formation' => $formation,
            'form' => $form->createView(),
        ));
    }

    /**
     * Formation edit
     *
     * @Route("/{id}/edit", name="formation_edit")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request,Formation $formation)
    {
        $session = new Session();

        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($formation);
            $em->flush();

            $session->getFlashBag()->add('success', 'La formation a bien été éditée !');

            return $this->redirectToRoute('admin_formations');

        }

        return $this->render('admin/formation/edit.html.twig', array(
            'formation' => $formation,
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($formation)->createView(),
        ));
    }

    /**
     * Formation delete
     *
     * @Route("/{id}", name="formation_delete")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removeAction(Request $request,Formation $formation)
    {
        $session = new Session();
        $form = $this->getDeleteForm($formation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($formation);
            $em->flush();
            $session->getFlashBag()->add('success', 'La formation a bien été supprimée !');
        }
        return $this->redirectToRoute('admin_formations');
    }

    /**
     * @param Formation $formation
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Formation $formation){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('formation_delete', array('id' => $formation->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

}
