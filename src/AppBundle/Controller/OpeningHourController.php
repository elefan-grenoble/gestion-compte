<?php

namespace AppBundle\Controller;


use AppBundle\Entity\OpeningHour;
use AppBundle\Entity\Task;
use AppBundle\Form\OpeningHourType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;


/**
 * OpeningHour controller.
 *
 * @Route("admin/openinghours")
 */
class OpeningHourController extends Controller
{
    /**
     * List all opening hours.
     *
     * @Route("/", name="admin_openinghour_index", methods={"GET"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $openingHours = $em->getRepository('AppBundle:OpeningHour')->findAll();

        return $this->render('admin/openinghour/index.html.twig', array(
            'openingHours' => $openingHours
        ));
    }

    /**
     * Add new opening hour.
     *
     * @Route("/new", name="admin_openinghour_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $openingHour = new OpeningHour();

        $form = $this->createForm(OpeningHourType::class, $openingHour);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $start = $form->get('start')->getData();
            $openingHour->setStart(new \DateTime($start));
            $end = $form->get('end')->getData();
            $openingHour->setEnd(new \DateTime($end));

            $em->persist($openingHour);
            $em->flush();

            $session->getFlashBag()->add('success', "L'horaire a bien été crée !");
            return $this->redirectToRoute('admin_openinghour_index');
        }

        return $this->render('admin/openinghour/new.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * Edit opening hour.
     *
     * @Route("/edit/{id}", name="admin_openinghour_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request, OpeningHour $openingHour)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(OpeningHourType::class, $openingHour);
        $form->handleRequest($request);

        if ($request->isMethod('GET')) {
            $form->get('start')->setData($openingHour->getStart()->format('H:i'));
            $form->get('end')->setData($openingHour->getEnd()->format('H:i'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $start = $form->get('start')->getData();
            $openingHour->setStart(new \DateTime($start));
            $end = $form->get('end')->getData();
            $openingHour->setEnd(new \DateTime($end));

            $em->persist($openingHour);
            $em->flush();

            $session->getFlashBag()->add('success', "L'horaire a bien été éditée !");
            return $this->redirectToRoute('admin_openinghour_index');
        }

        return $this->render('admin/openinghour/edit.html.twig', array(
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($openingHour)->createView()
        ));
    }

    /**
     * Delete opening hour.
     *
     * @Route("/{id}", name="admin_openinghour_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deleteAction(Request $request, OpeningHour $openingHour)
    {
        $session = new Session();

        $form = $this->getDeleteForm($openingHour);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($openingHour);
            $em->flush();

            $session->getFlashBag()->add('success', "L'horaire a bien été supprimée !");
            return $this->redirectToRoute('admin_openinghour_index');
        }

        return $this->redirectToRoute('admin_openinghour_index');
    }

    /**
     * @param OpeningHour $openingHour
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(OpeningHour $openingHour)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_openinghour_delete', array('id' => $openingHour->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
