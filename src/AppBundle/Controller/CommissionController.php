<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Commission;
use AppBundle\Entity\Role;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("admin/commissions")
 * @Security("has_role('ROLE_ADMIN')")
 */
class CommissionController extends Controller
{

    /**
     * Comissions list
     *
     * @Route("/", name="admin_commissions")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction(Request $request)
    {
        $commissions = $this->getDoctrine()->getManager()->getRepository('AppBundle:Commission')->findAll();
        return $this->render('admin/commission/list.html.twig',array('commissions'=>$commissions));
    }

    /**
     * Comission new
     *
     * @Route("/new", name="commission_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function newAction(Request $request)
    {

        $session = new Session();

        $commission = new Commission();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm('AppBundle\Form\CommissionType', $commission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($commission);
            $em->flush();

            $session->getFlashBag()->add('success', 'La nouvelle commission a bien été créée !');

            return $this->redirectToRoute('commission_edit', array('id' => $commission->getId()));

        }

        return $this->render('admin/commission/new.html.twig', array(
            'commission' => $commission,
            'form' => $form->createView(),
        ));
    }

    /**
     * Comission edit
     *
     * @Route("/{id}/edit", name="commission_edit")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function editAction(Request $request,Commission $commission)
    {
        $session = new Session();

        $form = $this->createForm('AppBundle\Form\CommissionType', $commission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();

            foreach ($commission->getBeneficiaries() as $beneficiary){
                $beneficiary->setOwn();
                $em->persist($beneficiary);
            }
            $owners = $commission->getOwners();
            foreach ($owners as $beneficiary){
                $beneficiary->setOwn($commission);
                $em->persist($beneficiary);
            }

            $em->persist($commission);
            $em->flush();

            $session->getFlashBag()->add('success', 'La commission a bien été éditée !');

            return $this->redirectToRoute('admin_commissions');

        }

        return $this->render('admin/commission/edit.html.twig', array(
            'commission' => $commission,
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($commission)->createView(),
        ));
    }

    /**
     * Comission edit
     *
     * @Route("/{id}", name="commission_delete")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removeAction(Request $request,Commission $commission)
    {
        $session = new Session();
        $form = $this->getDeleteForm($commission);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            foreach ($commission->getBeneficiaries() as $beneficiary){
                $beneficiary->removeCommission($commission);
                $em->persist($beneficiary);
            }
            foreach ($commission->getOwners() as $owner){
                $owner->setOwn();
                $em->persist($owner);
            }
            $em->remove($commission);
            $em->flush();
            $session->getFlashBag()->add('success', 'La commission a bien été supprimée !');
        }
        return $this->redirectToRoute('admin_commissions');
    }

    /**
     * @param Commission $commission
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Commission $commission){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('commission_delete', array('id' => $commission->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
