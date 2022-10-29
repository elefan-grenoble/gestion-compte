<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\MembershipShiftExemption;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * MembershipShiftExemption controller.
 *
 * @Route("admin/membershipshiftexemption")
 */
class MembershipShiftExemptionController extends Controller
{
    /**
     * Lists all membershipShiftExemption entities.
     *
     * @Route("/", name="admin_membershipshiftexemption_index")
     * @Security("has_role('ROLE_USER_MANAGER')")
     * @Method("GET")
     */
    public function indexAction(Request $request)
    {
        $page = $request->get('page', 1);
        $limit = 50;
        $em = $this->getDoctrine()->getManager();
        $nb_exemptions = $em->getRepository('AppBundle:MembershipShiftExemption')->count([]);
        if ($nb_exemptions == 0) {
            $max_page = 1;
        } else {
            $max_page = intval(($nb_exemptions-1) / $limit) + 1;
        }
        $em = $this->getDoctrine()->getManager();

        $membershipShiftExemptions = $em->getRepository('AppBundle:MembershipShiftExemption')
            ->findBy([], ['createdAt' => 'DESC'], $limit, ($page - 1) * $limit);

        return $this->render('admin/membershipshiftexemption/index.html.twig', array(
            'membershipShiftExemptions' => $membershipShiftExemptions,
            'current_page' => $page,
            'max_page' => $max_page,
        ));
    }

    /**
     * Creates a new membershipShiftExemption entity.
     *
     * @Route("/new", name="admin_membershipshiftexemption_new")
     * @Security("has_role('ROLE_USER_MANAGER')")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $membershipShiftExemption = new MembershipShiftExemption();
        $form = $this->createForm('AppBundle\Form\MembershipShiftExemptionType', $membershipShiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $membership = $form->get("beneficiary")->getData()->getMembership();
            $membershipShiftExemption->setMembership($membership);
            $current_user = $this->get('security.token_storage')->getToken()->getUser();
            $membershipShiftExemption->setCreatedBy($current_user);
            $em->persist($membershipShiftExemption);
            $em->flush();

            return $this->redirectToRoute('admin_membershipshiftexemption_index');
        }
        $beneficiaries = $em->getRepository(Beneficiary::class)->findAllActive();

        return $this->render('admin/membershipshiftexemption/new.html.twig', array(
            'membershipShiftExemption' => $membershipShiftExemption,
            'beneficiaries' => $beneficiaries,
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing membershipShiftExemption entity.
     *
     * @Route("/{id}/edit", name="admin_membershipshiftexemption_edit")
     * @Security("has_role('ROLE_USER_MANAGER')")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, MembershipShiftExemption $membershipShiftExemption)
    {
        $deleteForm = $this->createDeleteForm($membershipShiftExemption);
        $editForm = $this->createForm('AppBundle\Form\MembershipShiftExemptionType', $membershipShiftExemption, ['edit' => true]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('admin_membershipshiftexemption_edit', array('id' => $membershipShiftExemption->getId()));
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
     * @Route("/{id}", name="admin_membershipshiftexemption_delete")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, MembershipShiftExemption $membershipShiftExemption)
    {
        $form = $this->createDeleteForm($membershipShiftExemption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($membershipShiftExemption);
            $em->flush();
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
}
