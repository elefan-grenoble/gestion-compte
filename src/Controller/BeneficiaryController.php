<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Form\BeneficiaryType;
use App\Service\MembershipService;
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

    public function getCurrentAppUser(){
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

    private function getErrorMessages(Form $form) {
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

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $firstname = $form->get('firstname')->getData();
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            $beneficiaries = $qb->select('b')->from('App\Entity\Beneficiary', 'b')
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

    /**
     * @Route("/list", name="beneficiary_list")
     * @Method({"POST"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_USER')")
     */
    public function listAction(Request $request){

        $granted = false;
        if ($this->get('security.authorization_checker')->isGranted('ROLE_USER_MANAGER'))
            $granted = true;
        if ($this->getUser()->getBeneficiary() && count($this->getUser()->getBeneficiary()->getOwnedCommissions()))
            $granted = true;
        if ($granted && $request->isXmlHttpRequest()){
            $em = $this->getDoctrine()->getManager();
            $userRepo = $em->getRepository(Beneficiary::class);

            $string = $request->get('string');

            $rsm = new ResultSetMappingBuilder($em);
            $rsm->addRootEntityFromClassMetadata('App:Beneficiary', 'b');

            $query = $em->createNativeQuery('SELECT b.* FROM beneficiary AS b LEFT JOIN fos_user as u ON u.id = b.user_id WHERE LOWER(CONCAT_WS(u.username,u.email,b.lastname,b.firstname)) LIKE :key', $rsm);

            $beneficiaries = $query->setParameter('key', '%' . $string . '%')
                ->getResult();

            $returnArray = array();
            foreach ($beneficiaries as $beneficiary){
                $dead = false;
                if ($beneficiary->getMembership()->isWithdrawn()){
                    $dead = true;
                }
                if (!$this->get(MembershipService::class)->isUptodate($beneficiary->getMembership())){
                    $dead = true;
                }
                if (!$beneficiary->getMembership()){
                    $dead = true;
                }
                $returnArray[] = array('name' => $beneficiary->getAutocompleteLabelFull() ,'icon' => (!$dead) ? $request->getUriForPath('/build/images/cancel.svg') : '');
            }
            return new JsonResponse($returnArray);
        }
        return new Response("Ajax only",400);
    }
}
