<?php

namespace AppBundle\Controller;

use AppBundle\Entity\EmailTemplate;
use AppBundle\Form\EmailTemplateType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Email template controller.
 *
 * @Route("emailTemplate")
 */
class EmailTemplateController extends Controller
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
     * Lists all email templates.
     *
     * @Route("/", name="email_template_list", methods={"GET"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function listAction(Request $request)
    {

        $em = $this->getDoctrine()->getManager();
        $emailTemplates = $em->getRepository('AppBundle:EmailTemplate')->findAll();
        return $this->render('admin/mail/template/list.html.twig', array(
            'emailTemplates' => $emailTemplates,
        ));
    }


    /**
     * Create an email template
     *
     * @Route("/new", name="email_template_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function newAction(Request $request)
    {
        $emailTemplate = new EmailTemplate();
        $form = $this->createForm(EmailTemplateType::class, $emailTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $em = $this->getDoctrine()->getManager();
            $em->persist($emailTemplate);
            $em->flush();
            $session->getFlashBag()->add('success', "Modèle d'email créé");
            return $this->redirectToRoute('email_template_list');
        }

        return $this->render('admin/mail/template/new.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * Edit an email template
     *
     * @Route("/{id}/edit", name="email_template_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function editAction(Request $request, EmailTemplate $emailTemplate)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $this->denyAccessUnlessGranted('edit', $emailTemplate);

        $form = $this->createForm(EmailTemplateType::class, $emailTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($emailTemplate);
            $em->flush();

            $session->getFlashBag()->add('success', "Modèle d'email édité");
            return $this->redirectToRoute('email_template_list');
        }

        return $this->render('admin/mail/template/edit.html.twig', array(
            'form' => $form->createView()
        ));
    }

}
