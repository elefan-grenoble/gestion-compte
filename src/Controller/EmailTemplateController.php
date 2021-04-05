<?php

namespace App\Controller;

use App\Entity\EmailTemplate;
use App\Form\EmailTemplateType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Email template controller.
 *
 * @Route("emailTemplate")
 */
class EmailTemplateController extends Controller
{
    /**
     * Lists all email templates.
     *
     * @Route("/", name="email_template_list")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function listAction(Request $request, EntityManagerInterface $em)
    {
        $emailTemplates = $em->getRepository('App:EmailTemplate')->findAll();
        return $this->render('admin/mail/template/list.html.twig', array(
            'emailTemplates' => $emailTemplates,
        ));
    }


    /**
     * Create an email template
     *
     * @Route("/new", name="email_template_new")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request, EntityManagerInterface $em)
    {
        $emailTemplate = new EmailTemplate();
        $form = $this->createForm(EmailTemplateType::class, $emailTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $em->persist($emailTemplate);
            $em->flush();
            $session->getFlashBag()->add('success', "Modèle d'email créé");
            return $this->redirectToRoute('email_template_list');

        }

        return $this->render('admin/mail/template/edit.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * Edit an email template
     *
     * @Route("/{id}/edit", name="email_template_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request, EmailTemplate $emailTemplate, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('edit', $emailTemplate);

        $form = $this->createForm(EmailTemplateType::class, $emailTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
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
