<?php

namespace App\Controller;

use App\Entity\ClosingException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * ClosingException controller
 *
 * @Route("closingexceptions")
 */
class ClosingExceptionController extends AbstractController
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

        $closingExceptions = $em->getRepository('App:ClosingException')->findFuturesOrOngoing();

        return $this->render('closingexception/_partial/widget.html.twig', [
            'closingExceptions' => $closingExceptions,
            'title' => $filter_title,
        ]);
    }
}
