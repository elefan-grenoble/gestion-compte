<?php

namespace AppBundle\Controller;

use AppBundle\Entity\OpeningHour;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * OpeningHour controller
 *
 * @Route("openinghours")
 */
class OpeningHourController extends Controller
{
    /**
     * Opening hours widget display
     * 
     * @Route("/widget", name="openinghour_widget", methods={"GET"})
     */
    public function widgetAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $filter_title = $request->query->has('title') ? ($request->get('title') == 1) : true;
        $filter_align = $request->query->has('align') ? $request->get('align') : 'center';

        $openingHours = $em->getRepository('AppBundle:OpeningHour')->findAll();

        return $this->render('admin/openinghour/_partial/widget.html.twig', [
            'openingHours' => $openingHours,
            'title' => $filter_title,
            'align' => $filter_align,
        ]);
    }
}
