<?php

namespace AppBundle\Controller;


use AppBundle\Entity\ClosingException;
use AppBundle\Form\ClosingExceptionType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;


/**
 * Admin ClosingException controller ("fermetures exceptionnelles" coté admin)
 *
 * @Route("admin/closingexceptions")
 */
class AdminClosingExceptionController extends Controller
{
    /**
     * List all closing exceptions
     *
     * @Route("/", name="admin_closingexception_index", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $closingExceptions = $em->getRepository('AppBundle:ClosingException')->findAll();

        return $this->render('admin/closingexception/index.html.twig', array(
            'closingExceptions' => $closingExceptions
        ));
    }

    /**
     * Add new closing exception
     *
     * @Route("/new", name="admin_closingexception_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $closingException = new ClosingException();

        $form = $this->createForm(ClosingExceptionType::class, $closingException);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $closingException->setCreatedBy($current_user);

            $em->persist($closingException);
            $em->flush();

            $session->getFlashBag()->add('success', "La fermeture exceptionnelle a bien été crée !");
            return $this->redirectToRoute('admin_closingexception_index');
        }

        return $this->render('admin/closingexception/new.html.twig', array(
            'form' => $form->createView()
        ));
    }
}
