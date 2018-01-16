<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Role;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("admin/roles")
 * @Security("has_role('ROLE_ADMIN')")
 */
class RoleController extends Controller
{

    /**
     * Roles list
     *
     * @Route("/", name="admin_roles")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction()
    {
        $roles = $this->getDoctrine()->getManager()->getRepository('AppBundle:Role')->findAll();
        return $this->render('admin/role/list.html.twig',array('roles'=>$roles));
    }

    /**
     * role new
     *
     * @Route("/new", name="role_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();

        $role = new Role();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm('AppBundle\Form\RoleType', $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($role);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le nouveau role a bien été créé !');

            return $this->redirectToRoute('admin_roles');

        }

        return $this->render('admin/role/new.html.twig', array(
            'role' => $role,
            'form' => $form->createView(),
        ));
    }

    /**
     * Comission edit
     *
     * @Route("/{id}/edit", name="role_edit")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request,Role $role)
    {
        $session = new Session();

        $form = $this->createForm('AppBundle\Form\RoleType', $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($role);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le role a bien été édité !');

            return $this->redirectToRoute('admin_roles');

        }

        return $this->render('admin/role/edit.html.twig', array(
            'role' => $role,
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($role)->createView(),
        ));
    }

    /**
     * Comission edit
     *
     * @Route("/{id}", name="role_delete")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removeAction(Request $request,Role $role)
    {
        $session = new Session();
        $form = $this->getDeleteForm($role);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($role);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le role a bien été supprimée !');
        }
        return $this->redirectToRoute('admin_roles');
    }

    /**
     * @param Role $role
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Role $role){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('role_delete', array('id' => $role->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

}
