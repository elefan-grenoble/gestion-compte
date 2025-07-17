<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Client;
use AppBundle\Entity\Service;
use AppBundle\Entity\Task;
use AppBundle\Form\ClientType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;


/**
 * Task controller.
 *
 * @Route("admin/clients")
 */
class ClientController extends Controller
{

    /**
     * Lists all clients.
     *
     * @Route("/", name="client_list", methods={"GET"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function listAction()
    {
        $clients = $this->getDoctrine()->getManager()->getRepository('AppBundle:Client')->findAll();

        return $this->render('admin/client/list.html.twig',array('clients'=>$clients));
    }

    /**
     * Add new Client //todo put this auto in service création
     *
     * @Route("/new", name="client_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();

        $form = $this->createForm(ClientType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $urls = $form->get('urls')->getData();
            $service = $form->get('service')->getData();

            $clientManager = $this->container->get('fos_oauth_server.client_manager.default');
            $client = $clientManager->createClient();
            $client->setRedirectUris(explode(',',$urls));
            $client->setAllowedGrantTypes($form->get('grant_types')->getData());
            $client->setService($service);
            $clientManager->updateClient($client);

            $session->getFlashBag()->add('success', 'Le client a bien été créé !');
            return $this->redirectToRoute('client_list');

//            return $this->redirect($this->generateUrl('fos_oauth_server_authorize', array(
//                'client_id' => $client->getPublicId(),
//                'redirect_uri' => $url,
//                'response_type' => 'code'
//            )));
        }

        return $this->render('admin/client/new.html.twig', array(
            'form' => $form->createView()
        ));

    }

    /**
     * edit client.
     *
     * @Route("/{id}/edit", name="client_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function editAction(Request $request, Client $client)
    {
        $session = new Session();

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(ClientType::class);
        $form->get('urls')->setData($client->getUrls());
        $form->get('grant_types')->setData($client->getAllowedGrantTypes());
        $form->get('service')->setData($client->getService());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $urls = $form->get('urls')->getData();
            $client->setRedirectUris(explode(',',$urls));
            $service = $form->get('service')->getData();
            $client->setService($service);
            $grant_types = $form->get('grant_types')->getData();
            $client->setAllowedGrantTypes($grant_types);

            $em->persist($client);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le client a bien été édité !');
            return $this->redirectToRoute('client_list');

        } elseif ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $key => $error) {
                $session->getFlashBag()->add('error', 'Erreur ' . ($key + 1) . " : " . $error->getMessage());
            }
        }

        $delete_form = $this->getDeleteForm($client);

        return $this->render('admin/client/edit.html.twig', array(
            'form' => $form->createView(),
            'delete_form' => $delete_form->createView()
        ));

    }

    /**
     * delete client.
     *
     * @Route("/{id}", name="client_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function deleteAction(Request $request, Client $client)
    {
        $session = new Session();

        $form = $this->getDeleteForm($client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($client);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le client a bien été supprimé !');
        }

        return $this->redirectToRoute('client_list');
    }

    /**
     * @param Client $client
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Client $client)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('client_delete', array('id' => $client->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
