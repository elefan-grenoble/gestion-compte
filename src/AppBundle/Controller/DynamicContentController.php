<?php

namespace AppBundle\Controller;

use AppBundle\Entity\DynamicContent;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Dynamic content controller.
 *
 * @Route("content")
 */
class DynamicContentController extends Controller
{
    private $_current_app_user;

    public function getCurrentAppUser()
    {
        if (!$this->_current_app_user) {
            $this->_current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        }
        return $this->_current_app_user;
    }

    /**
     * Lists all dynamic contents.
     *
     * @Route("/", name="dynamic_content_list")
     * @Method("GET")
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $dynamicContents = $em->getRepository('AppBundle:DynamicContent')->findAll();
        $dynamicContentsByType = array();

        foreach ($dynamicContents as $dynamicContent) {
            $type = $dynamicContent->getType();
            if (!isset($dynamicContentsByType[$type])) {
                $dynamicContentsByType[$type] = array();
            }
            $dynamicContentsByType[$type][] = $dynamicContent;
        }

        return $this->render('admin/content/list.html.twig', array(
            'dynamicContentsByType' => $dynamicContentsByType,
        ));
    }

    /**
     * Edit a dynamic content
     *
     * @Route("/{id}/edit", name="dynamic_content_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function dynamicContentEditAction(Request $request, DynamicContent $dynamicContent)
    {
        $form = $this->createForm('AppBundle\Form\DynamicContentType', $dynamicContent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();
            if ($dynamicContent->getContent() == null) {
                $dynamicContent->setContent('');
            }
            $em->persist($dynamicContent);
            $em->flush();

            $session->getFlashBag()->add('success', 'Contenu dynamique édité');
            return $this->redirectToRoute('dynamic_content_list');
        }

        return $this->render('admin/content/edit.html.twig', array(
            'dynamicContent' => $dynamicContent,
            'form' => $form->createView()
        ));
    }

}
