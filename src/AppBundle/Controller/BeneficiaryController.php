<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Form\BeneficiaryType;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;


/**
 * Beneficiary controller.
 *
 * @Route("beneficiary")
 */
class BeneficiaryController extends Controller
{
    private $_current_app_user;

    public function getCurrentAppUser()
    {
        if (!$this->_current_app_user){
            $this->_current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        }
        return $this->_current_app_user;
    }

    /**
     * Displays a form to edit an existing user entity.
     *
     * @Route("/{id}/edit", name="beneficiary_edit")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @param Beneficiary $beneficiary
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editBeneficiaryAction(Request $request, Beneficiary $beneficiary)
    {
        $session = new Session();
        $member = $beneficiary->getMembership();
        $this->denyAccessUnlessGranted('edit', $member);

        $editForm = $this->createForm(BeneficiaryType::class, $beneficiary);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $em->flush();
            $session->getFlashBag()->add('success', 'Mise à jour effectuée');

            return $this->redirectToShow($member);
        }

        return $this->render('beneficiary/edit_beneficiary.html.twig', array(
            'beneficiary' => $beneficiary,
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Set as main beneficiary
     *
     * @Route("/beneficiary/{id}", name="beneficiary_set_main")
     * @Method("GET")
     * @param Beneficiary $beneficiary
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function setAsMainBeneficiaryAction(Beneficiary $beneficiary)
    {
        $session = new Session();
        $member = $beneficiary->getMembership();
        $this->denyAccessUnlessGranted('edit', $member);
        $em = $this->getDoctrine()->getManager();
        $member->setMainBeneficiary($beneficiary);
        $em->persist($member);
        $em->flush();
        $session->getFlashBag()->add('success', 'Le changement de bénéficiaire principal a été effectué');
        return $this->redirectToShow($member);
    }

    /**
     * Detaches a beneficiary entity.
     *
     * @Route("/{id}/detach", name="beneficiary_detach")
     * @Method("POST")
     * @param Request $request
     * @param Beneficiary $beneficiary
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function detachBeneficiaryAction(Request $request, Beneficiary $beneficiary)
    {
        $session = new Session();
        $member = $beneficiary->getMembership();

        $this->denyAccessUnlessGranted('edit', $member);

        if ($beneficiary->isMain()) {
            $session->getFlashBag()->add('error', 'Un bénéficiaire principal ne peut pas être détaché');
            return $this->redirectToShow($member);
        }

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('beneficiary_detach', array('id' => $beneficiary->getId())))
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            // first we remove the beneficiary from the current member
            $member->removeBeneficiary($beneficiary);
            $em->persist($member);

            // check if there is a existing membership with this main beneficiary (artefact ?)
            $existing_member = $em->getRepository('AppBundle:Membership')->findOneBy(array('mainBeneficiary' => $beneficiary));
            if ($existing_member) {
                $new_member = $existing_member;
                $new_member->setMainBeneficiary($beneficiary);
            } else {
                // then we create a new membership
                $new_member = new Membership();
                // init member id
                $m = $em->getRepository('AppBundle:Membership')->findOneBy(array(), array('member_number' => 'DESC'));
                $mm = 1;
                if ($m)
                    $mm = $m->getMemberNumber() + 1;
                $new_member->setMemberNumber($mm);
                // set main beneficiary
                $new_member->setMainBeneficiary($beneficiary);
            }
            // init other fields
            $new_member->setWithdrawn(false);
            $new_member->setFrozen(false);
            $new_member->setFrozenChange(false);

            $em->persist($new_member);

            $em->flush();

            $session->getFlashBag()->add('success', 'Le bénéficiaire a été détaché ! Il a maintenant son propre compte.');
            return $this->redirectToShow($new_member);
        }

        return $this->redirectToShow($member);
    }

    /**
     * Deletes a beneficiary entity.
     *
     * @Route("/beneficiary/{id}", name="beneficiary_delete")
     * @Method("DELETE")
     * @param Request $request
     * @param Beneficiary $beneficiary
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteBeneficiaryAction(Request $request, Beneficiary $beneficiary)
    {
        $member = $beneficiary->getMembership();

        $this->denyAccessUnlessGranted('edit', $member);

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('beneficiary_delete', array('id' => $beneficiary->getId())))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($beneficiary);
            $em->flush();
        }

        return $this->redirectToShow($member);
    }

    private function getErrorMessages(Form $form)
    {
        $errors = array();

        foreach ($form->getErrors() as $key => $error) {
            if ($form->isRoot()) {
                $errors['#'][] = $error->getMessage();
            } else {
                $errors[] = $error->getMessage();
            }
        }

        foreach ($form->all() as $child) {
            if (!$child->isValid()) {
                $key = (isset($child->getConfig()->getOptions()['label'])) ? $child->getConfig()->getOptions()['label'] : $child->getName();
                $errors[$key] = $this->getErrorMessages($child);
            }
        }

        return $errors;
    }

    /**
     * @Route("/find_member_number", name="find_member_number")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function findMemberNumberAction(Request $request)
    {
        $securityContext = $this->container->get('security.authorization_checker');
        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $form = $this->createFormBuilder()
                ->add('firstname', TextType::class, array('label' => 'Le prénom', 'attr' => array(
                    'placeholder' => 'babar',
                )))
                ->add('find', SubmitType::class, array('label' => 'Trouver le numéro'))
                ->getForm();
        } else {
            $form = $this->createFormBuilder()
                ->add('firstname', TextType::class, array('label' => 'Mon prénom', 'attr' => array(
                    'placeholder' => 'babar',
                )))
                ->add('find', SubmitType::class, array('label' => 'Trouver mon numéro'))
                ->getForm();
        }

        if ($form->handleRequest($request)->isValid()) {
            $firstname = $form->get('firstname')->getData();
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            $beneficiaries = $qb->select('b')->from('AppBundle\Entity\Beneficiary', 'b')
                ->join('b.membership', 'm')
                ->where($qb->expr()->like('b.firstname', $qb->expr()->literal('%' . $firstname . '%')))
                ->andWhere("m.withdrawn != 1 or m.withdrawn is NULL")
                ->orderBy("m.member_number", 'ASC')
                ->getQuery()
                ->getResult();
            return $this->render('beneficiary/find_member_number.html.twig', array(
                'form' => null,
                'beneficiaries' => $beneficiaries,
                'return_path' => 'confirm',
                'routeParam' => 'id',
                'params' => array()
            ));
        }
        return $this->render('beneficiary/find_member_number.html.twig', array(
            'form' => $form->createView(),
            'beneficiaries' => ''
        ));
    }

    /**
     * @Route("/{id}/confirm", name="confirm")
     * @Method({"POST"})
     * @param Beneficiary $beneficiary
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function confirmAction(Beneficiary $beneficiary, Request $request)
    {
        return $this->render('beneficiary/confirm.html.twig', array('beneficiary' => $beneficiary));
    }

    private function redirectToShow(Membership $member)
    {
        $user = $member->getMainBeneficiary()->getUser(); // FIXME
        $session = new Session();
        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber()));
        else
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber(), 'token' => $user->getTmpToken($session->get('token_key') . $this->getCurrentAppUser()->getUsername())));
    }
}
