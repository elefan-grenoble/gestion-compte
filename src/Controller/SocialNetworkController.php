<?php

namespace App\Controller;


use App\Entity\SocialNetwork;
use App\Entity\Task;
use App\Form\SocialNetworkType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;


/**
 * SocialNetwork controller.
 *
 * @Route("admin/socialnetworks")
 */
class SocialNetworkController extends AbstractController
{
    /**
     * List all social networks.
     *
     * @Route("/", name="admin_socialnetwork_list", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $socialNetworks = $em->getRepository('App:SocialNetwork')->findAll();

        return $this->render('admin/socialnetwork/list.html.twig', array(
            'socialNetworks' => $socialNetworks
        ));
    }

    /**
     * Add new social network.
     *
     * @Route("/new", name="admin_socialnetwork_new", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();

        $socialNetwork = new SocialNetwork();

        $form = $this->createForm(SocialNetworkType::class, $socialNetwork);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($socialNetwork);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le nouveau réseau social a bien été créé !');
            return $this->redirectToRoute('admin_socialnetwork_list');
        }

        return $this->render('admin/socialnetwork/new.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * Edit social network.
     *
     * @Route("/edit/{id}", name="admin_socialnetwork_edit", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function editAction(Request $request, SocialNetwork $socialNetwork)
    {
        $session = new Session();

        $form = $this->createForm(SocialNetworkType::class, $socialNetwork);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($socialNetwork);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le réseau social a bien été édité !');
            return $this->redirectToRoute('admin_socialnetwork_list');
        }

        return $this->render('admin/socialnetwork/edit.html.twig', array(
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($socialNetwork)->createView()
        ));
    }

    /**
     * Delete social network.
     *
     * @Route("/{id}", name="admin_socialnetwork_delete", methods={"DELETE"})
     * @Security("is_granted('ROLE_SUPER_ADMIN')")
     */
    public function deleteAction(Request $request, SocialNetwork $socialNetwork)
    {
        $session = new Session();

        $form = $this->getDeleteForm($socialNetwork);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($socialNetwork);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le réseau social a bien été supprimé !');
            return $this->redirectToRoute('admin_socialnetwork_list');
        }

        return $this->redirectToRoute('admin_socialnetwork_list');
    }

    /**
     * @param SocialNetwork $socialNetwork
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(SocialNetwork $socialNetwork)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_socialnetwork_delete', array('id' => $socialNetwork->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
