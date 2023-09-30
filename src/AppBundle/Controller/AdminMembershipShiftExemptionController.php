<?php

namespace AppBundle\Controller;

use AppBundle\Entity\MembershipShiftExemption;
use AppBundle\Form\AutocompleteMembershipType;
use AppBundle\Repository\ShiftExemptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Doctrine\ORM\Tools\Pagination\Paginator;
use \Datetime;

/**
 * MembershipShiftExemption controller.
 *
 * @Route("admin/membershipshiftexemption")
 */
class AdminMembershipShiftExemptionController extends Controller
{
    /**
     * Filter form.
     */
    private function filterFormFactory(Request $request): array
    {
        // default values
        $res = [
            "membership" => null,
            "shiftExemption" => null,
            'page' => 1,
        ];

        // filter creation ----------------------
        $res["form"] = $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_membershipshiftexemption_index'))
            ->add('membership', AutocompleteMembershipType::class, array(
                'label' => 'Membre',
                'required' => false,
            ))
            ->add('shiftExemption', EntityType::class, array(
                'label' => 'Motif',
                'class' => 'AppBundle:ShiftExemption',
                'choice_label' => 'name',
                'multiple' => false,
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
            $res["membership"] = $res["form"]->get("membership")->getData();
            $res["shiftExemption"] = $res["form"]->get("shiftExemption")->getData();
            $res["page"] = $res["form"]->get("page")->getData();
        }

        return $res;
    }

    /**
     * Lists all membershipShiftExemption entities.
     *
     * @Route("/", name="admin_membershipshiftexemption_index", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_MANAGER')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $filter = $this->filterFormFactory($request);
        $findByFilter = array();
        $sort = 'createdAt';
        $order = 'DESC';

        $qb = $em->getRepository('AppBundle:MembershipShiftExemption')->createQueryBuilder('mse')
            ->orderBy('mse.' . $sort, $order);

        if ($filter['membership']) {
            $qb = $qb->andWhere('mse.membership = :membership')
                ->setParameter('membership', $filter['membership']);
        }
        if ($filter['shiftExemption']) {
            $qb = $qb->andWhere('mse.shiftExemption = :shiftExemption')
                ->setParameter('shiftExemption', $filter['shiftExemption']);
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

        return $this->render('admin/membershipshiftexemption/index.html.twig', array(
            'membershipShiftExemptions' => $paginator,
            'filter_form' => $filter['form']->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount,
        ));
    }

    /**
     * Creates a new membershipShiftExemption entity.
     *
     * @Route("/new", name="admin_membershipshiftexemption_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_MANAGER')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $membershipShiftExemption = new MembershipShiftExemption();
        $form = $this->createForm('AppBundle\Form\MembershipShiftExemptionType', $membershipShiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $membership = $form->get("membership")->getData();
            $membershipShiftExemption->setMembership($membership);

            if ($this->get('membership_service')->memberHasShiftsOnExemptionPeriod($membershipShiftExemption)) {
                $session->getFlashBag()->add("error", "Désolé, le membre a déjà des créneaux planifiés sur la plage d'exemption.");
            } else {
                $membershipShiftExemption->setCreatedBy($current_user);
                $em->persist($membershipShiftExemption);
                $em->flush();

                $session->getFlashBag()->add('success', 'L\'exemption de créneau a bien été crée !');
                return $this->redirectToRoute('admin_membershipshiftexemption_index');
            }
        }

        return $this->render('admin/membershipshiftexemption/new.html.twig', array(
            'membershipShiftExemption' => $membershipShiftExemption,
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing membershipShiftExemption entity.
     *
     * @Route("/{id}/edit", name="admin_membershipshiftexemption_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_MANAGER')")
     */
    public function editAction(Request $request, MembershipShiftExemption $membershipShiftExemption)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm('AppBundle\Form\MembershipShiftExemptionType', $membershipShiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->get('membership_service')->memberHasShiftsOnExemptionPeriod($membershipShiftExemption)) {
                $session->getFlashBag()->add("error", "Désolé, le membre a déjà des créneaux planifiés sur la plage d'exemption.");
            } else {
                $em->flush();
                $session->getFlashBag()->add('success', 'L\'exemption de créneau a bien été éditée !');
                return $this->redirectToRoute('admin_membershipshiftexemption_index');
            }
        }

        return $this->render('admin/membershipshiftexemption/edit.html.twig', array(
            'membershipShiftExemption' => $membershipShiftExemption,
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($membershipShiftExemption)->createView(),
        ));
    }

    /**
     * Deletes a membershipShiftExemption entity.
     *
     * @Route("/{id}", name="admin_membershipshiftexemption_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_USER_MANAGER')")
     */
    public function deleteAction(Request $request, MembershipShiftExemption $membershipShiftExemption)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $form = $this->getDeleteForm($membershipShiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $today = new Datetime('now');
            $today->setTime(0, 0, 0);
            if (($membershipShiftExemption->getStart() < $today) && !$current_user->hasRole('ROLE_SUPER_ADMIN')) {
                $session->getFlashBag()->add('warning', 'Vous n\'avez pas les droits pour supprimer une exemption déjà commencée');
                return $this->redirectToRoute('admin_membershipshiftexemption_edit', array('id' => $membershipShiftExemption->getId()));
            }

            $em->remove($membershipShiftExemption);
            $em->flush();

            $session->getFlashBag()->add('success', 'L\'exemption de créneau a bien été supprimée !');
        }

        return $this->redirectToRoute('admin_membershipshiftexemption_index');
    }

    /**
     * Creates a form to delete a membershipShiftExemption entity.
     *
     * @param MembershipShiftExemption $membershipShiftExemption The membershipShiftExemption entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function getDeleteForm(MembershipShiftExemption $membershipShiftExemption)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_membershipshiftexemption_delete', array('id' => $membershipShiftExemption->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
