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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
            'beneficiary' => null,
            'fixe' => 0
        ];

        // filter creation ----------------------
        $res["form"] = $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_shiftfreelog_index'))
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
            ->add('submit', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'filtrer')
            ))
            ->getForm();

        $res['form']->handleRequest($request);

        if ($res['form']->isSubmitted() && $res['form']->isValid()) {
            $res["beneficiary"] = $res["form"]->get("beneficiary")->getData();
            $res["fixe"] = $res["form"]->get("fixe")->getData();
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

        $qb = $em->getRepository('AppBundle:ShiftFreeLog')->createQueryBuilder('s')
            ->orderBy('s.' . $sort, $order);

        if($filter["beneficiary"]) {
            $qb = $qb->andWhere('s.beneficiary = :beneficiary')
                ->setParameter('beneficiary', $filter['beneficiary']);
        }
        if($filter["fixe"] > 0) {
            $qb = $qb->andWhere('s.fixe = :fixe')
                ->setParameter('fixe', $filter['fixe']-1);
        }

        $limitPerPage = 25;
        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $pagesCount = ($totalItems == 0) ? 1 : ceil($totalItems / $limitPerPage);
        $currentPage = $request->get('page', 1);
        $currentPage = ($currentPage > $pagesCount) ? $pagesCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage-1)) // set the offset
            ->setMaxResults($limitPerPage); // set the limit

        return $this->render('admin/shiftfreelog/index.html.twig', array(
            'shiftFreeLogs' => $paginator,
            'filter_form' => $filter['form']->createView(),
            'current_page' => $currentPage,
            'pages_count' => $pagesCount,
        ));
    }

}
