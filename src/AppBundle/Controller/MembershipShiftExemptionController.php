<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\MembershipShiftExemption;
use AppBundle\Repository\ShiftExemptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use \Datetime;

/**
 * MembershipShiftExemption controller.
 *
 * @Route("admin/membershipshiftexemption")
 */
class MembershipShiftExemptionController extends Controller
{
    /**
     * Filter form.
     */
    private function filterFormFactory(Request $request): array
    {
        // default values
        $res = [
            "shiftExemption" => null,
        ];

        // filter creation ----------------------
        $res["form"] = $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_membershipshiftexemption_index'))
            ->add('shiftExemption', EntityType::class, array(
                'label' => 'Motif',
                'class' => 'AppBundle:ShiftExemption',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
            ))
            ->add('filter', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'filtrer')
            ))
            ->getForm();

        $res['form']->handleRequest($request);

        if ($res['form']->isSubmitted() && $res['form']->isValid()) {
            $res["shiftExemption"] = $res["form"]->get("shiftExemption")->getData();
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

        if($filter["shiftExemption"]) {
            $findByFilter["shiftExemption"] = $filter["shiftExemption"];
        }

        $page = $request->get('page', 1);
        $limit = 50;
        $order = array('createdAt' => 'DESC');

        $nb_exemptions = $em->getRepository('AppBundle:MembershipShiftExemption')->count([]);
        if ($nb_exemptions == 0) {
            $max_page = 1;
        } else {
            $max_page = intval(($nb_exemptions-1) / $limit) + 1;
        }

        $membershipShiftExemptions = $em->getRepository('AppBundle:MembershipShiftExemption')
            ->findBy($findByFilter, $order, $limit, ($page - 1) * $limit);

        return $this->render('admin/membershipshiftexemption/index.html.twig', array(
            'membershipShiftExemptions' => $membershipShiftExemptions,
            'filter_form' => $filter['form']->createView(),
            'current_page' => $page,
            'max_page' => $max_page,
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
        $membershipShiftExemption = new MembershipShiftExemption();
        $form = $this->createForm('AppBundle\Form\MembershipShiftExemptionType', $membershipShiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $membership = $form->get("beneficiary")->getData()->getMembership();
            $membershipShiftExemption->setMembership($membership);

            if ($this->isMembershipHasShiftsOnExemptionPeriod($membershipShiftExemption)) {
                $session->getFlashBag()->add("error", "Désolé, les bénéficiaires ont déjà des créneaux planifiés sur la plage d'exemption.");
                return $this->redirectToRoute('admin_membershipshiftexemption_new');
            }

            $current_user = $this->get('security.token_storage')->getToken()->getUser();
            $membershipShiftExemption->setCreatedBy($current_user);
            $em->persist($membershipShiftExemption);
            $em->flush();

            $session->getFlashBag()->add('success', 'L\'exemption de créneau a bien été crée !');
            return $this->redirectToRoute('admin_membershipshiftexemption_index');
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
        $deleteForm = $this->createDeleteForm($membershipShiftExemption);
        $editForm = $this->createForm('AppBundle\Form\MembershipShiftExemptionType', $membershipShiftExemption);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            if ($this->isMembershipHasShiftsOnExemptionPeriod($membershipShiftExemption)) {
                $session->getFlashBag()->add("error", "Désolé, les bénéficiaires ont déjà des créneaux planifiés sur la plage d'exemption.");
            } else {
                $session->getFlashBag()->add('success', 'L\'exemption de créneau a bien été éditée !');
                $this->getDoctrine()->getManager()->flush();
            }

            return $this->redirectToRoute('admin_membershipshiftexemption_index');
        }

        return $this->render('admin/membershipshiftexemption/edit.html.twig', array(
            'membershipShiftExemption' => $membershipShiftExemption,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
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
        $form = $this->createDeleteForm($membershipShiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $current_user = $this->get('security.token_storage')->getToken()->getUser();
            $today = new Datetime('now');
            $today->setTime(0, 0, 0);
            if (($membershipShiftExemption->getStart() < $today) && !$current_user->hasRole('ROLE_SUPER_ADMIN')) {
                $session->getFlashBag()->add('warning', 'Vous n\'avez pas les droits pour supprimer une exemption déjà commencée');
                return $this->redirectToRoute('admin_membershipshiftexemption_edit', array('id' => $membershipShiftExemption->getId()));
            }

            $em = $this->getDoctrine()->getManager();
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
    private function createDeleteForm(MembershipShiftExemption $membershipShiftExemption)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_membershipshiftexemption_delete', array('id' => $membershipShiftExemption->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    private function isMembershipHasShiftsOnExemptionPeriod(MembershipShiftExemption $membershipShiftExemption)
    {
        $em = $this->getDoctrine()->getManager();
        $shifts = $em->getRepository('AppBundle:Shift')->findInProgressAndUpcomingShiftsForMembership($membershipShiftExemption->getMembership());
        return $shifts->exists(function($key, $value) use ($membershipShiftExemption) {
            return $membershipShiftExemption->isCurrent($value->getStart());
        });
    }
}
