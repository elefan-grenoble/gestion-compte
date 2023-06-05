<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Job;
use AppBundle\Entity\Period;
use AppBundle\Service\PeriodFormHelper;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Period Controller ("semaine type" anonyme)
 *
 * @Route("period")
 */
class PeriodController extends Controller
{
    /**
     * Display all the period (available and reserved) anonymized
     *
     * @Route("/", name="period_index", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function indexAction(Request $request, PeriodFormHelper $formHelper)
    {
        $em = $this->getDoctrine()->getManager();

        $defaults = [
            'job' => null,
            'filling' => null,
            'week' => null,
        ];
        $form = $formHelper->createFilterForm($this->createFormBuilder(), $defaults);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $job_filter = $form->get("job")->getData();
            $week_filter = $form->get("week")->getData();
            $filling_filter = $form->get("filling")->getData();
        } else {
            $job_filter = null;
            $week_filter = null;
            $filling_filter = null;
        }

        $periodsByDay = array();
        foreach (Period::DAYS_OF_WEEK as $i => $value) {
            $periodsByDay[$i] = $em->getRepository('AppBundle:Period')->findAll($i, $job_filter, false);
        }

        return $this->render('period/index.html.twig', array(
            'days_of_week' => Period::DAYS_OF_WEEK,
            'periods_by_day' => $periodsByDay,
            'filter_form' => $form->createView(),
            'week_filter' => $week_filter,
            'filling_filter' => $filling_filter,
        ));
    }
}
