<?php

namespace AppBundle\Controller;

use AppBundle\Entity\EventKind;
use AppBundle\Form\EventKindType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;


/**
 * EventKind controller.
 *
 * @Route("admin/event/kinds")
 */
class EventKindController extends Controller
{
    /**
     * Lists all event kinds
     *
     * @Route("/", name="admin_event_kind_list", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $eventKinds = $em->getRepository('AppBundle:EventKind')->findAll();

        return $this->render('admin/event/kind/list.html.twig', array(
            'eventKinds' => $eventKinds,
        ));
    }

    /**
     * Add new event kind
     *
     * @Route("/new", name="admin_event_kind_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $eventKind = new EventKind();
        $form = $this->createForm(EventKindType::class, $eventKind);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($eventKind);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le type d\'événement a bien été créé !');
            return $this->redirectToRoute('admin_event_kind_list');
        }

        return $this->render('admin/event/kind/new.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Edit event kind
     *
     * @Route("/{id}/edit", name="admin_event_kind_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request, EventKind $eventKind)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(EventKindType::class, $eventKind);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($eventKind);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le type d\'événement a bien été édité !');
            return $this->redirectToRoute('admin_event_kind_list');
        }

        return $this->render('admin/event/kind/edit.html.twig', array(
            'form' => $form->createView(),
            'eventKind' => $eventKind,
            'delete_form' => $this->getDeleteForm($eventKind)->createView(),
        ));
    }

    /**
     * Delete event kind
     *
     * @Route("/{id}", name="admin_event_kind_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function deleteAction(Request $request, EventKind $eventKind)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->getDeleteForm($eventKind);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($eventKind);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le type d\'événement a bien été supprimé !');
        }

        return $this->redirectToRoute('admin_event_kind_list');
    }

    /**
     * @param EventKind $eventKind
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(EventKind $eventKind)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_event_kind_delete', array('id' => $eventKind->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
