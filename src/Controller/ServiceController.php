<?php

namespace App\Controller;


use App\Entity\Service;
use App\Entity\Task;
use App\Form\ServiceType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;


/**
 * Task controller.
 *
 * @Route("services")
 */
class ServiceController extends Controller
{
    /**
     * Lists all services.
     *
     * @Route("/", name="admin_services")
     * @Method("GET")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $services = $em->getRepository('App:Service')->findAll();
        return $this->render('admin/service/list.html.twig', array(
            'services' => $services
        ));

    }

    /**
     * Lists all services.
     *
     * @Route("/navlist", name="nav_list_services")
     * @Method("GET")
     * @Security("has_role('ROLE_USER')")
     */
    public function navlistAction()
    {
        $em = $this->getDoctrine()->getManager();
        $services = $em->getRepository('App:Service')->findBy(array('public'=>1));
        return $this->render('admin/service/navlist.html.twig', array(
            'services' => $services
        ));
    }

    /**
     * add new services.
     *
     * @Route("/new", name="service_new")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();

        $service = new Service();

        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($service);
            $em->flush();

            if ($service->getLogo()){
                $this->resolveLogo($service);
            }

            $session->getFlashBag()->add('success', 'Le nouveau service a bien été créée !');

            return $this->redirectToRoute('admin_services');

        }
        return $this->render('admin/service/new.html.twig', array(
            'form' => $form->createView()
        ));

    }

    /**
     * edit service.
     *
     * @Route("/edit/{id}", name="service_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function editAction(Request $request,Service $service)
    {
        $session = new Session();

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(ServiceType::class, $service);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($service);
            $em->flush();

            if ($service->getLogo()){
                $this->resolveLogo($service);
            }

            $session->getFlashBag()->add('success', 'Le service a bien été édité !');

            return $this->redirectToRoute('admin_services');

        }
        return $this->render('admin/service/edit.html.twig', array(
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($service)->createView()
        ));


    }


    /**
     * edit service.
     *
     * @Route("/{id}", name="service_remove")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removeAction(Request $request,Service $service)
    {
        $session = new Session();

        $form = $this->getDeleteForm($service);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->remove($service);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le service a bien été supprimé !');

            return $this->redirectToRoute('admin_services');

        }

        return $this->redirectToRoute('admin_services');

    }

    /**
     * @param Service $service
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Service $service)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('service_remove', array('id' => $service->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * @param Service $service
     * @return string
     */
    protected function resolveLogo(Service $service)
    {
        $helper = $this->container->get('vich_uploader.templating.helper.uploader_helper');
        $path = $helper->asset($service, 'logoFile');
        $imagineCacheManager = $this->get('liip_imagine.cache.manager');
        $resolvedPath = $imagineCacheManager->getBrowserPath($path, 'service_logo');
        return $resolvedPath;
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
}
