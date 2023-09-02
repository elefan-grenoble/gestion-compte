<?php

namespace AppBundle\Controller;


use AppBundle\Entity\ClosingException;
use AppBundle\Form\ClosingExceptionType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
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
     * Admin closing exception home
     *
     * @Route("/", name="admin_closingexception_index", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $closingExceptionsFuture = $em->getRepository('AppBundle:ClosingException')->findFutures();
        $closingExceptionsPast = $em->getRepository('AppBundle:ClosingException')->findPast(10);  # only the 10 last

        return $this->render('admin/closingexception/index.html.twig', array(
            'closingExceptionsFuture' => $closingExceptionsFuture,
            'closingExceptionsPast' => $closingExceptionsPast
        ));
    }

    /**
     * Admin closing exception list
     *
     * @Route("/list", name="admin_closingexception_list", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $closingExceptions = $em->getRepository('AppBundle:ClosingException')->findAll();

        return $this->render('admin/closingexception/list.html.twig', array(
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

    /**
     * Closing exception widget generator
     *
     * @Route("/widget_generator", name="admin_closingexception_widget_generator", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function widgetGeneratorAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('title', CheckboxType::class, array(
                'required' => false,
                'data' => true,
                'label' => 'Afficher le titre du widget ?',
                'attr' => array('class' => 'filled-in')
            ))
            ->add('generate', SubmitType::class, array('label' => 'Générer'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {
            $data = $form->getData();

            $widgetQueryString = 'title=' . ($data['title'] ? 1 : 0);

            return $this->render('admin/closingexception/widget_generator.html.twig', array(
                'form' => $form->createView(),
                'query_string' => $widgetQueryString
            ));
        }

        return $this->render('admin/closingexception/widget_generator.html.twig', array(
            'form' => $form->createView(),
        ));
    }
}
