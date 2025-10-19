<?php

namespace App\Controller;

use App\Entity\DynamicContent;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Dynamic content controller.
 *
 * @Route("content")
 */
class DynamicContentController extends Controller
{
    /**
     * Lists all dynamic contents.
     *
     * @Route("/", name="dynamic_content_list", methods={"GET"})
     * @Security("is_granted('ROLE_PROCESS_MANAGER')")
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $dynamicContents = $em->getRepository('App:DynamicContent')->findAll();
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
     * @Route("/{id}/edit", name="dynamic_content_edit", methods={"GET","POST"})
     * @Security("is_granted('ROLE_PROCESS_MANAGER')")
     */
    public function dynamicContentEditAction(Request $request, DynamicContent $dynamicContent)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $form = $this->createForm('App\Form\DynamicContentType', $dynamicContent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($dynamicContent->getContent() == null) {
                $dynamicContent->setContent('');
            }
            $dynamicContent->setUpdatedBy($current_user);
            $em->persist($dynamicContent);
            $em->flush();

            $session->getFlashBag()->add('success', 'Contenu dynamique édité !');
            return $this->redirectToRoute('dynamic_content_list');
        }

        return $this->render('admin/content/edit.html.twig', array(
            'dynamicContent' => $dynamicContent,
            'form' => $form->createView()
        ));
    }
}
