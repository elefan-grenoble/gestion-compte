<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use App\Entity\Commission;
use App\Entity\HelloassoPayment;
use App\Entity\Role;
use App\Event\CommissionJoinOrLeaveEvent;
use App\Event\HelloassoEvent;
use App\Form\AutocompleteBeneficiaryType;
use App\Form\CommissionType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

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
    public function indexAction(Request $request, EntityManagerInterface $em)
    {
        $commissions = $em->getRepository('App:Commission')->findAll();
        return $this->render('admin/commission/list.html.twig',array('commissions'=>$commissions));
    }

    /**
     * Comission new
     *
     * @Route("/new", name="commission_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function newAction(Request $request, EntityManagerInterface $em)
    {

        $session = new Session();

        $commission = new Commission();

        $form = $this->createForm(CommissionType::class, $commission);
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
     * @Security("has_role('ROLE_USER')")
     */
    public function editAction(Request $request, Commission $commission, EntityManagerInterface $em)
    {
        $session = new Session();
        $current_app_user = $this->getUser();
        $beneficiary = $current_app_user->getBeneficiary();

        if (! $current_app_user->hasRole('ROLE_SUPER_ADMIN') && ! $beneficiary->getOwnedCommissions()->contains($commission)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(CommissionType::class, $commission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

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

        $add_form = $this->getAddBeneficiaryForm($commission);

        return $this->render('admin/commission/edit.html.twig', array(
            'commission' => $commission,
            'form' => $form->createView(),
            'add_form' => $add_form->createView(),
            'remove_beneficiary_form' => $this->getRemoveBeneficiaryForm($commission)->createView(),
            'delete_form' => $this->getDeleteForm($commission)->createView(),
        ));

    }

    private function getAddBeneficiaryForm(Commission $commission){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('commission_add_beneficiary', array('id' => $commission->getId())))
            ->add('beneficiary',AutocompleteBeneficiaryType::class,array('label'=>'Email ou nom de la personne','required'=>true))
            ->setMethod('POST')
            ->getForm();
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
    public function addBeneficiaryAction(Request $request, Commission $commission, EntityManagerInterface $em, EventDispatcherInterface $dispatcher)
    {
        $current_app_user = $this->getUser();

        if (! $current_app_user->hasRole('ROLE_SUPER_ADMIN') && ! $current_app_user->getBeneficiary()->getOwnedCommissions()->contains($commission)) {
            throw $this->createAccessDeniedException();
        }
        $session = new Session();
        $success = true;
        $form = $this->getAddBeneficiaryForm($commission);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $beneficiary = $form->getData('beneficiary')['beneficiary'];
            if (!$commission->getBeneficiaries()->contains($beneficiary)) {
                $beneficiary->addCommission($commission);
                $em->persist($beneficiary);
                $em->flush();
                $message = $beneficiary->getFirstname().' a bien été ajouté à la commission';
                $dispatcher->dispatch(
                    CommissionJoinOrLeaveEvent::JOIN_EVENT_NAME,
                    new CommissionJoinOrLeaveEvent($beneficiary,$commission)
                );
            }else{
                $success = false;
                $message = $beneficiary->getFirstname().' fait déjà partie de la commission';
            }
        }

        if ($request->isXmlHttpRequest()){
            $html = $this->render('beneficiary/_partial/chip.html.twig', [
                'beneficiary' => $beneficiary,
                'close' => true,
            ]);
            return new JsonResponse(array('success'=>$success,'message'=>$message,'html'=>$html));
        }

        $session->getFlashBag()->add($success ? 'success' : 'error', $message);

        return $this->redirectToRoute('commission_edit',array('id' => $commission->getId()));
    }

    /**
     * Commission remove beneficiary
     *
     * @Route("/{id}/remove_beneficiary/", name="commission_remove_beneficiary")
     * @Method({"POST"})
     */
    public function removeBeneficiaryAction(Request $request, Commission $commission, EntityManagerInterface $em, EventDispatcherInterface $dispatcher)
    {
        $session = new Session();
        $current_app_user = $this->getUser();

        if (! $current_app_user->hasRole('ROLE_SUPER_ADMIN') && ! $current_app_user->getBeneficiary()->getOwnedCommissions()->contains($commission)) {
            throw $this->createAccessDeniedException();
        }

        $beneficiary = $em->getRepository('App:Beneficiary')->find($_POST['beneficiary']);
        /** @var Beneficiary $beneficiary */
        if ($beneficiary->getId()){
            $beneficiary->removeCommission($commission);
            $em->persist($beneficiary);
            $em->flush();
            $dispatcher->dispatch(
                CommissionJoinOrLeaveEvent::LEAVE_EVENT_NAME,
                new CommissionJoinOrLeaveEvent($beneficiary,$commission)
            );
        }
        if ($request->isXmlHttpRequest()){
            return new JsonResponse(array('success'=>true,'message'=>$beneficiary->getFirstname().' a bien été retiré de la commission'));
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
    public function removeAction(Request $request, Commission $commission, EntityManagerInterface $em)
    {
        $session = new Session();
        $form = $this->getDeleteForm($commission);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
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
