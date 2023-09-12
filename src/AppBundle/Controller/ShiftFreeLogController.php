<?php

namespace AppBundle\Controller;

use AppBundle\Entity\ShiftFreeLog;
use AppBundle\Form\AutocompleteBeneficiaryType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * ShiftFreeLog controller.
 *
 * @Route("admin/shifts/freelogs")
 */
class ShiftFreeLogController extends Controller
{
    /**
     * Filter form.
     */
    private function filterFormFactory(Request $request): array
    {
        // default values
        $res = [
            'created_at' => null,
            'shift_start_date' => null,
            'beneficiary' => null,
            'fixe' => 0,
            'page' => 1,
        ];

        // filter creation ----------------------
        $res["form"] = $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_shiftfreelog_index'))
            ->add('created_at', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'label' => "Date de l'annulation",
                'required' => false,
                'attr' => [
                    'class' => 'datepicker'
                ]
            ])
            ->add('shift_start_date', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'label' => 'Date du créneau',
                'required' => false,
                'attr' => [
                    'class' => 'datepicker'
                ]
            ])
            ->add('beneficiary', AutocompleteBeneficiaryType::class, array(
                'label' => 'Bénéficiaire',
                'required' => false,
            ))
            ->add('fixe', ChoiceType::class, array(
                'label' => 'Type de créneau',
                'required' => false,
                'choices' => [
                    'fixe' => 2,
                    'volant' => 1,
                ]
            ))
            ->add('page', HiddenType::class, [
                'data' => '1'
            ])
            ->add('submit', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'filtrer')
            ))
            ->getForm();

        $res['form']->handleRequest($request);

        if ($res['form']->isSubmitted() && $res['form']->isValid()) {
            $res["created_at"] = $res["form"]->get("created_at")->getData();
            $res["shift_start_date"] = $res["form"]->get("shift_start_date")->getData();
            $res["beneficiary"] = $res["form"]->get("beneficiary")->getData();
            $res["fixe"] = $res["form"]->get("fixe")->getData();
            $res["page"] = $res["form"]->get("page")->getData();
        }

        return $res;
    }

    /**
     * Lists all ShiftFreeLog entities.
     *
     * @Route("/", name="admin_shiftfreelog_index", methods={"GET","POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $filter = $this->filterFormFactory($request);
        $sort = 'createdAt';
        $order = 'DESC';

        $qb = $em->getRepository('AppBundle:ShiftFreeLog')->createQueryBuilder('sfl')
            ->orderBy('sfl.' . $sort, $order);

        if ($filter["created_at"]) {
            $qb = $qb->andWhere("DATE_FORMAT(sfl.createdAt, '%Y-%m-%d') = :created_at_formatted")
                ->setParameter('created_at_formatted', $filter['created_at']->format('Y-m-d'));
        }
        if ($filter["shift_start_date"]) {
            $qb = $qb->leftJoin('sfl.shift', 's')
                ->andWhere("DATE_FORMAT(s.start, '%Y-%m-%d') = :shift_start_date_formatted")
                ->setParameter('shift_start_date_formatted', $filter['shift_start_date']->format('Y-m-d'));
        }
        if ($filter["beneficiary"]) {
            $qb = $qb->andWhere('sfl.beneficiary = :beneficiary')
                ->setParameter('beneficiary', $filter['beneficiary']);
        }
        if ($filter["fixe"] > 0) {
            $qb = $qb->andWhere('sfl.fixe = :fixe')
                ->setParameter('fixe', $filter['fixe']-1);
        }

        $limitPerPage = 25;
        $paginator = new Paginator($qb);
        $resultCount = count($paginator);
        $pageCount = ($resultCount == 0) ? 1 : ceil($resultCount / $limitPerPage);
        $currentPage = $filter['page'];
        $currentPage = ($currentPage > $pageCount) ? $pageCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage-1)) // set the offset
            ->setMaxResults($limitPerPage); // set the limit

        return $this->render('admin/shiftfreelog/index.html.twig', array(
            'shiftFreeLogs' => $paginator,
            'filter_form' => $filter['form']->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount,
        ));
    }
}
