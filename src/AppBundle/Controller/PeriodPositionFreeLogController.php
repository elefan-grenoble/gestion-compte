<?php

namespace AppBundle\Controller;

use AppBundle\Entity\PeriodPositionFreeLog;
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
 * PeriodPositionFreeLog controller.
 *
 * @Route("admin/period/positionfreelogs")
 */
class PeriodPositionFreeLogController extends Controller
{
    /**
     * Filter form.
     */
    private function filterFormFactory(Request $request): array
    {
        // default values
        $res = [
            'created_at' => null,
            'beneficiary' => null,
            'page' => 1,
        ];

        // filter creation ----------------------
        $res["form"] = $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_periodpositionfreelog_list'))
            ->add('created_at', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'label' => "Date de l'annulation",
                'required' => false,
                'attr' => [
                    'class' => 'datepicker'
                ]
            ])
            ->add('beneficiary', AutocompleteBeneficiaryType::class, array(
                'label' => 'Bénéficiaire',
                'required' => false,
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
            $res["beneficiary"] = $res["form"]->get("beneficiary")->getData();
            $res["page"] = $res["form"]->get("page")->getData();
        }

        return $res;
    }

    /**
     * Lists all PeriodPositionFreeLog entities.
     *
     * @Route("/", name="admin_periodpositionfreelog_list", methods={"GET","POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $filter = $this->filterFormFactory($request);
        $sort = 'createdAt';
        $order = 'DESC';

        $qb = $em->getRepository('AppBundle:PeriodPositionFreeLog')->createQueryBuilder('ppfl')
            ->orderBy('ppfl.' . $sort, $order);

        if ($filter["created_at"]) {
            $qb = $qb->andWhere("DATE_FORMAT(ppfl.createdAt, '%Y-%m-%d') = :created_at")
                ->setParameter('created_at', $filter['created_at']->format('Y-m-d'));
        }
        if ($filter["beneficiary"]) {
            $qb = $qb->andWhere('ppfl.beneficiary = :beneficiary')
                ->setParameter('beneficiary', $filter['beneficiary']);
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

        $periodPositionFreeLogDeleteForms = [];
        foreach ($paginator as $periodPositionFreeLog) {
            $periodPositionFreeLogDeleteForms[$periodPositionFreeLog->getId()] = $this->getDeleteForm($periodPositionFreeLog)->createView();
        }

        return $this->render('admin/periodpositionfreelog/list.html.twig', array(
            'periodPositionFreeLogs' => $paginator,
            'filter_form' => $filter['form']->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount,
            'period_position_free_log_delete_forms_' => $periodPositionFreeLogDeleteForms,
        ));
    }

    /**
     * Delete PeriodPositionFreeLog
     *
     * @Route("/{id}", name="admin_periodpositionfreelog_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function deleteAction(Request $request, PeriodPositionFreeLog $periodPositionFreeLog)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->getDeleteForm($periodPositionFreeLog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($periodPositionFreeLog);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le log d\'annulation de poste type a bien été supprimé !');
        }

        return $this->redirectToRoute('admin_periodpositionfreelog_list');
    }

    /**
     * @param PeriodPositionFreeLog $periodPositionFreeLog
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(PeriodPositionFreeLog $periodPositionFreeLog)
    {
        return $this->get('form.factory')->createNamedBuilder('period_position_free_log_delete_forms_' . $periodPositionFreeLog->getId())
            ->setAction($this->generateUrl('admin_periodpositionfreelog_delete', array('id' => $periodPositionFreeLog->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
