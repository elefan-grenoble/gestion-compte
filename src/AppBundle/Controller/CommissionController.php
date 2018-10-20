<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Commission;
use AppBundle\Entity\Role;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("/commissions")
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
     * Commission edit
     *
     * @Route("/{id}/edit", name="commission_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request,Commission $commission)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $beneficiary = $current_app_user->getBeneficiary();

        if (! $current_app_user->hasRole('ROLE_SUPER_ADMIN') && ! $beneficiary->getOwnedCommissions()->contains($commission)) {
            throw $this->createAccessDeniedException();
        }

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

            if ($current_app_user->hasRole('ROLE_SUPER_ADMIN'))
                return $this->redirectToRoute('admin_commissions');

        }

        $add_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('commission_add_beneficiary', array('id' => $commission->getId())))
            ->add('beneficiaries', EntityType::class, array(
                'class' => Beneficiary::class,
                'label' => 'Membre à ajouter',
                'choice_label'=> 'display_name',
                //'choices' => $beneficiaries,
                'multiple' => true,
                'required' => false
            ))
            ->setMethod('POST')
            ->getForm();

        return $this->render('admin/commission/edit.html.twig', array(
            'commission' => $commission,
            'form' => $form->createView(),
            'add_form' => $add_form->createView(),
            'remove_beneficiary_form' => $this->getRemoveBeneficiaryForm($commission)->createView(),
            'delete_form' => $this->getDeleteForm($commission)->createView(),
        ));

    }

    /**
     * Commission add beneficiary'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('b')
                        ->join("b.user", "u")
                        ->addSelect("u")
                        ->where('u.withdrawn = 0');
                },
     *
     * @Route("/{id}/add_beneficiary/", name="commission_add_beneficiary")
     * @Method({"POST"})
     */
    public function addBeneficiaryAction(Request $request,Commission $commission)
    {
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

        if (! $current_app_user->hasRole('ROLE_SUPER_ADMIN') && ! $current_app_user->getBeneficiary()->getOwnedCommissions()->contains($commission)) {
            throw $this->createAccessDeniedException();
        }
        $session = new Session();
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('commission_add_beneficiary', array('id' => $commission->getId())))
            ->add('beneficiaries', EntityType::class, array(
                'class' => Beneficiary::class,
                'label' => 'Membre à ajouter',
                'choice_label'=> 'display_name',
                //'choices' => $beneficiaries,
                'multiple' => true,
                'required' => false
            ))
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $beneficiaries = $form->getData('beneficiaries')['beneficiaries'];
            foreach ( $beneficiaries as $beneficiary){
                if (!$commission->getBeneficiaries()->contains($beneficiary)) {
                    $beneficiary->addCommission($commission);
                    $em->persist($beneficiary);
                }
            }
            $em->flush();
            if (count($beneficiaries))
                $session->getFlashBag()->add('success', 'Les membres ont bien été ajoutés !');
            else
                $session->getFlashBag()->add('success', 'Le membre a bien été ajouté !');

        }
        return $this->redirectToRoute('commission_edit',array('id' => $commission->getId()));
    }

    /**
     * Commission remove beneficiary
     *
     * @Route("/{id}/remove_beneficiary/", name="commission_remove_beneficiary")
     * @Method({"POST"})
     */
    public function removeBeneficiaryAction(Request $request,Commission $commission)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

        if (! $current_app_user->hasRole('ROLE_SUPER_ADMIN') && ! $current_app_user->getBeneficiary()->getOwnedCommissions()->contains($commission)) {
            throw $this->createAccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();
        $beneficiary = $em->getRepository('AppBundle:Beneficiary')->find($_POST['beneficiary']);
        if ($beneficiary->getId()){
            $beneficiary->removeCommission($commission);
            $em->persist($beneficiary);
            $em->flush();
        }
        $session->getFlashBag()->add('success', 'Le membre '.$beneficiary.' a bien été retiré de la commission !');


        return $this->redirectToRoute('commission_edit',array('id' => $commission->getId()));
    }

    /**
     * Comission delete
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

    /**
     * @param Commission $commission
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getRemoveBeneficiaryForm(Commission $commission){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('commission_remove_beneficiary', array('id' => $commission->getId())))
            ->setMethod('POST')
            ->getForm();
    }
}
