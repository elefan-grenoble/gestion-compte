<?php

namespace App\Controller;


use App\Entity\Client;
use App\Entity\Service;
use App\Entity\Task;
use App\Form\ClientType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;


/**
 * Task controller.
 *
 * @Route("clients")
 */
class ClientController extends Controller
{

    /**
     * Lists all clients.
     *
     * @Route("/", name="admin_clients")
     * @Method("GET")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function listAction()
    {
        $clients = $this->getDoctrine()->getManager()->getRepository('App:Client')->findAll();
        return $this->render('admin/client/list.html.twig',array('clients'=>$clients));
    }

    /**
     * Add new Client //todo put this auto in service création
     *
     * @Route("/client_new", name="client_new")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request){

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

            return $this->redirectToRoute('admin_clients');

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
     * @Route("/edit/{id}", name="client_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function editAction(Request $request,Client $client){
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

            return $this->redirectToRoute('admin_clients');

        }elseif ($form->isSubmitted()){
            foreach ($this->getErrorMessages($form) as $key => $errors){
                foreach ($errors as $error)
                    $session->getFlashBag()->add('error', $key." : ".$error);
            }
        }
        $delete_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('client_remove', array('id' => $client->getId())))
            ->setMethod('DELETE')
            ->getForm();

        return $this->render('admin/client/edit.html.twig', array(
            'form' => $form->createView(),
            'delete_form' => $delete_form->createView()
        ));

    }

    /**
     * remove client.
     *
     * @Route("/remove/{id}", name="client_remove")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removeAction(Request $request,Client $client)
    {
        $session = new Session();
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('client_remove', array('id' => $client->getId())))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($client);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le client a bien été supprimé !');
        }

        return $this->redirectToRoute('admin_clients');
    }

}
