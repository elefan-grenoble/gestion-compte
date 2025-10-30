<?php

namespace App\Controller;

use App\Entity\OpeningHour;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * OpeningHour controller
 *
 * @Route("openinghours")
 */
class OpeningHourController extends AbstractController
{
    /**
     * Opening hours widget display
     * 
     * @Route("/widget", name="openinghour_widget", methods={"GET"})
     */
    public function widgetAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $openingHourKind = null;

        $filter_title = $request->query->has('title') ? ($request->get('title') == 1) : true;
        $filter_kind_title = $request->query->has('kind_title') ? ($request->get('kind_title') == 1) : true;
        $filter_align = $request->query->has('align') ? $request->get('align') : 'center';

        $filter_opening_hour_kind_id = $request->get('opening_hour_kind_id');
        if ($filter_opening_hour_kind_id) {
            $openingHourKind = $em->getRepository('App:OpeningHourKind')->find($filter_opening_hour_kind_id);
        }

        $openingHours = $em->getRepository('App:OpeningHour')->findAll($openingHourKind);

        return $this->render('openinghour/_partial/widget.html.twig', [
            'openingHours' => $openingHours,
            'openingHourKind' => $openingHourKind,
            'title' => $filter_title,
            'kind_title' => $filter_kind_title,
            'align' => $filter_align,
        ]);
    }
}
