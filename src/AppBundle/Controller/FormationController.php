<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Formation;
use AppBundle\Form\FormationType;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Formation controller.
 *
 * @Route("admin/formations")
 * @Security("has_role('ROLE_ADMIN')")
 */
class FormationController extends Controller
{

    /**
     * Formations list
     *
     * @Route("/", name="formation_list", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $formations = $em->getRepository('AppBundle:Formation')->findAll();

        return $this->render('admin/formation/list.html.twig',array('formations'=>$formations));
    }

    /**
     * Formation new
     *
     * @Route("/new", name="formation_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $formation = new Formation();

        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formation->setCreatedBy($current_user);

            $em->persist($formation);
            $em->flush();

            $session->getFlashBag()->add('success', 'La nouvelle formation a bien été créée !');
            return $this->redirectToRoute('formation_list');
        }

        return $this->render('admin/formation/new.html.twig', array(
            'formation' => $formation,
            'form' => $form->createView(),
        ));
    }

    /**
     * Formation edit
     *
     * @Route("/{id}/edit", name="formation_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request, Formation $formation)
    {
        $session = new Session();

        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($formation);
            $em->flush();

            $session->getFlashBag()->add('success', 'La formation a bien été éditée !');
            return $this->redirectToRoute('formation_list');
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
     * @Route("/{id}", name="formation_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function deleteAction(Request $request, Formation $formation)
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

        return $this->redirectToRoute('formation_list');
    }

    /**
     * @param Formation $formation
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Formation $formation)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('formation_delete', array('id' => $formation->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
