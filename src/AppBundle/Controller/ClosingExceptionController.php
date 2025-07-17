<?php

namespace AppBundle\Controller;

use AppBundle\Entity\ClosingException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * ClosingException controller
 *
 * @Route("closingexceptions")
 */
class ClosingExceptionController extends Controller
{
    /**
     * Closing exception widget display
     * 
     * @Route("/widget", name="closingexception_widget", methods={"GET"})
     */
    public function widgetAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $filter_title = $request->query->has('title') ? ($request->get('title') == 1) : true;

        $closingExceptions = $em->getRepository('AppBundle:ClosingException')->findFuturesOrOngoing();

        return $this->render('closingexception/_partial/widget.html.twig', [
            'closingExceptions' => $closingExceptions,
            'title' => $filter_title,
        ]);
    }
}
