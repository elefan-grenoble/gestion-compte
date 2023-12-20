<?php

namespace App\Controller;

use App\Entity\ShiftBucket;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Widget controller.
 * 
 * @Route("widget")
 */
class WidgetController extends Controller
{

    /**
     * Widget display
     * 
     * Note: moved to ShiftController. Duplicate left here to avoid breaking existing widgets.
     * 
     * @Route("/", name="widget", methods={"GET"})
     */
    public function widgetAction(Request $request)
    {
        $job_id = $request->get('job_id');
        $buckets = array();
        $display_end = $request->query->has('display_end') ? ($request->get('display_end') == 1) : false;
        $display_on_empty = $request->query->has('display_on_empty') ? ($request->get('display_on_empty') == 1) : false;
        $title = $request->query->has('title') ? ($request->get('title') == 1) : true;
        $job = null;
        if ($job_id) {
            $em = $this->getDoctrine()->getManager();
            $job = $em->getRepository('App:Job')->find($job_id);
            if ($job) {
                $shifts = $em->getRepository('App:Shift')->findFutures(null, $job);
                foreach ($shifts as $shift) {
                    $day = $shift->getStart()->format("d m Y");
                    $interval = $shift->getIntervalCode();
                    if (!isset($buckets[$interval . $day])) {
                        $buckets[$interval . $day] = new ShiftBucket();
                    }
                    $buckets[$interval . $day]->addShift($shift);
                }
            }
        }

        return $this->render('admin/shift/widget/widget.html.twig', [
            'job' => $job,
            'buckets' => $buckets,
            'display_end' => $display_end,
            'display_on_empty' => $display_on_empty,
            'title' => $title
        ]);
    }
}
