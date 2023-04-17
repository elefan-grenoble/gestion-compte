<?php

namespace AppBundle\Controller;

use AppBundle\Entity\EventKind;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;

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
}
