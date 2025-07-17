<?php

namespace AppBundle\Controller;

use AppBundle\Entity\OpeningHourKind;
use AppBundle\Form\OpeningHourKindType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;


/**
 * AdminOpeningHourKind controller
 *
 * @Route("admin/openinghours/kinds")
 */
class AdminOpeningHourKindController extends Controller
{
    /**
     * Lists all opening hour kinds
     *
     * @Route("/", name="admin_openinghour_kind_list", methods={"GET"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $openingHourKinds = $em->getRepository('AppBundle:OpeningHourKind')->findAll();

        return $this->render('admin/openinghour/kind/list.html.twig', array(
            'openingHourKinds' => $openingHourKinds,
        ));
    }

    /**
     * Add new opening hour kind
     *
     * @Route("/new", name="admin_openinghour_kind_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $openingHourKind = new OpeningHourKind();
        $form = $this->createForm(OpeningHourKindType::class, $openingHourKind);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $openingHourKind->setCreatedBy($current_user);
            $em->persist($openingHourKind);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le type d\'horaire d\'ouverture a bien été créé !');
            return $this->redirectToRoute('admin_openinghour_kind_list');
        }

        return $this->render('admin/openinghour/kind/new.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Edit opening hour kind
     *
     * @Route("/{id}/edit", name="admin_openinghour_kind_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function editAction(Request $request, OpeningHourKind $openingHourKind)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $form = $this->createForm(OpeningHourKindType::class, $openingHourKind);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $openingHourKind->setUpdatedBy($current_user);
            $em->persist($openingHourKind);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le type d\'horaire d\'ouverture a bien été édité !');
            return $this->redirectToRoute('admin_openinghour_kind_list');
        }

        return $this->render('admin/openinghour/kind/edit.html.twig', array(
            'form' => $form->createView(),
            'openingHourKind' => $openingHourKind,
            'delete_form' => $this->getDeleteForm($openingHourKind)->createView(),
        ));
    }

    /**
     * Delete opening hour kind
     *
     * @Route("/{id}", name="admin_openinghour_kind_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deleteAction(Request $request, OpeningHourKind $openingHourKind)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->getDeleteForm($openingHourKind);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($openingHourKind);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le type d\'horaire d\'ouverture a bien été supprimé !');
        }

        return $this->redirectToRoute('admin_openinghour_kind_list');
    }

    /**
     * @param OpeningHourKind $openingHourKind
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(OpeningHourKind $openingHourKind)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_openinghour_kind_delete', array('id' => $openingHourKind->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
