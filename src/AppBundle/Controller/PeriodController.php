<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Job;
use AppBundle\Entity\Period;
use AppBundle\Entity\PeriodPosition;
use AppBundle\Form\AutocompleteBeneficiaryType;
use AppBundle\Repository\JobRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     * Build the filter form for the admin main page (route /booking/admin)
     * and rerun an array with  the form object and the date range and the action
     *
     * the return object :
     * array(
     *      "form" => FormBuilderInterface
     *      "from" => DateTime,
     *      "to" => DateTime,
     *      "job"=> Job|null,
     *      "filling" => str|null,
     *      "beneficiary" => Entity/Beneficiary
     *      )
     *
     * @param Request $request the request sent by the client, used to process the form
     * @param bool $withBeneficiaryField if true will add a beneficiary and a 'problematic' filter field
     * @return array
     */
    private function filterFormFactory(Request $request, bool $withBeneficiaryField): array
    {
        // default values
        $res = [
            'beneficiary' => null,
            'job' => null,
            'filling' => null,
            'week' => null,
        ];

        // filter creation ----------------------
        $formBuilder = $this->createFormBuilder()
            ->add('job', EntityType::class, array(
                'label' => 'Type de créneau',
                'class' => 'AppBundle:Job',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
                'query_builder' => function(JobRepository $repository) {
                    $qb = $repository->createQueryBuilder('j');
                    return $qb
                        ->where($qb->expr()->eq('j.enabled', '?1'))
                        ->setParameter('1', '1')
                        ->orderBy('j.name', 'ASC');
                }
            ))
            ->add('week', ChoiceType::class, array(
                'label' => 'Semaine',
                'required' => false,
                'choices' => array(
                    'A' => 'A',
                    'B' => 'B',
                    'C' => 'C',
                    'D' => 'D',
                ),
            ))
            ->add('filter', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'filtrer')
            ));

        if ($withBeneficiaryField) {
            $formBuilder
                ->setAction($this->generateUrl('admin_period_index'))
                ->add('beneficiary', AutocompleteBeneficiaryType::class, array(
                    'label' => 'Bénéficiaire',
                    'required' => false,
                ))
                ->add('filling', ChoiceType::class, array(
                    'label' => 'Remplissage',
                    'required' => false,
                    'choices' => array(
                        'Complet' => 'full',
                        'Partiel' => 'partial',
                        'Vide' => 'empty',
                        'Problématique' => 'problematic'
                    ),
                ));
        }else{
            $formBuilder
                ->setAction($this->generateUrl('period_index'))
                ->add('filling', ChoiceType::class, array(
                    'label' => 'Remplissage',
                    'required' => false,
                    'choices' => array(
                        'Complet' => 'full',
                        'Partiel' => 'partial',
                        'Vide' => 'empty',
                    ),
                ));
        }

        $res["form"] = $formBuilder->getForm();
        $res["form"]->handleRequest($request);

        if ($res["form"]->isSubmitted() && $res["form"]->isValid()) {
            if ($withBeneficiaryField) {
                $res["beneficiary"] = $res["form"]->get("beneficiary")->getData();
            }
            $res["job"] = $res["form"]->get("job")->getData();
            $res["filling"] = $res["form"]->get("filling")->getData();
            $res["week"] = $res["form"]->get("week")->getData();

        }

        return $res;
    }

    /**
     * Display all the period (available and reserved) anonymized
     *
     * @Route("/", name="period_index", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $filter = $this->filterFormFactory($request, false);
        $sort = 'start';
        $order = 'ASC';
        $periodsByDay = array();

        foreach (Period::DAYS_OF_WEEK as $i => $value) {
            $findByFilter = array('dayOfWeek' => $i);
            $periodsByDay[$i] = $em->getRepository('AppBundle:Period')->findAll($i, $filter['job'], false);
        }

        return $this->render('period/index.html.twig', array(
            'days_of_week' => Period::DAYS_OF_WEEK,
            'periods_by_day' => $periodsByDay,
            'filter_form' => $filter['form']->createView(),
            'week_filter' => $filter['week'],
            'filling_filter' => $filter["filling"]
        ));
    }
}
