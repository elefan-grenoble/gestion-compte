<?php

namespace App\Controller;

use App\Entity\Service;
use App\Form\ServiceType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\FormInterface;

/**
 * Service controller.
 *
 * @Route("services")
 */
class ServiceController extends AbstractController
{
    /**
     * Lists all services.
     *
     * @Route("/", name="service_list", methods={"GET"})
     *
     * @Security("is_granted('ROLE_SUPER_ADMIN')")
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $services = $em->getRepository('App:Service')->findAll();

        return $this->render('admin/service/list.html.twig', [
            'services' => $services,
        ]);

    }

    /**
     * Lists all services (header navlist).
     *
     * @Route("/navlist", name="service_navlist", methods={"GET"})
     *
     * @Security("is_granted('ROLE_USER')")
     */
    public function navlistAction()
    {
        $em = $this->getDoctrine()->getManager();
        $services = $em->getRepository('App:Service')->findBy(['public' => 1]);

        return $this->render('admin/service/navlist.html.twig', [
            'services' => $services,
        ]);
    }

    /**
     * add new services.
     *
     * @Route("/new", name="service_new", methods={"GET","POST"})
     *
     * @Security("is_granted('ROLE_SUPER_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();

        $service = new Service();

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($service);
            $em->flush();

            if ($service->getLogo()) {
                $this->resolveLogo($service);
            }

            $session->getFlashBag()->add('success', 'Le nouveau service a bien été créée !');

            return $this->redirectToRoute('service_list');
        }

        return $this->render('admin/service/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * edit service.
     *
     * @Route("/{id}/edit", name="service_edit", methods={"GET","POST"})
     *
     * @Security("is_granted('ROLE_SUPER_ADMIN')")
     */
    public function editAction(Request $request, Service $service)
    {
        $session = new Session();

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($service);
            $em->flush();

            if ($service->getLogo()) {
                $this->resolveLogo($service);
            }

            $session->getFlashBag()->add('success', 'Le service a bien été édité !');

            return $this->redirectToRoute('service_list');
        }

        return $this->render('admin/service/edit.html.twig', [
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($service)->createView(),
        ]);
    }

    /**
     * delete service.
     *
     * @Route("/{id}", name="service_delete", methods={"DELETE"})
     *
     * @Security("is_granted('ROLE_SUPER_ADMIN')")
     */
    public function deleteAction(Request $request, Service $service)
    {
        $session = new Session();

        $form = $this->getDeleteForm($service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($service);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le service a bien été supprimé !');
        }

        return $this->redirectToRoute('service_list');
    }

    /**
     * @return FormInterface
     */
    protected function getDeleteForm(Service $service)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('service_delete', ['id' => $service->getId()]))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     * @return string
     */
    protected function resolveLogo(Service $service)
    {
        $helper = $this->container->get('vich_uploader.templating.helper.uploader_helper');
        $path = $helper->asset($service, 'logoFile');
        $imagineCacheManager = $this->get('liip_imagine.cache.manager');

        return $imagineCacheManager->getBrowserPath($path, 'service_logo');
    }

    private function getErrorMessages(Form $form)
    {
        $errors = [];

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
}
