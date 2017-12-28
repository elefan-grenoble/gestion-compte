<?php

namespace AppBundle\Controller;

use AppBundle\Entity\BookedShift;
use AppBundle\Entity\Period;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Validator\Constraints\DateTime;

/**
* @Route("period")
*/
class PeriodController extends Controller
{
    /**
     * @Route("/", name="period")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $periods = array();
        for($i=0;$i<7;$i++){
            $periods[$i] = $em->getRepository('AppBundle:Period')->findBy(array('dayOfWeek'=>$i),array('start'=>'ASC'));
        }
        return $this->render('admin/period/list.html.twig',array(
            "periods" => $periods
        ));
    }

    /**
     * @Route("/new", name="period_new")
     * @Security("has_role('ROLE_ADMIN')")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $period = new Period();

        $form = $this->createForm('AppBundle\Form\PeriodType',$period);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $time = $form->get('start')->getData();
            $period->setStart(new \DateTime($time));
            $time = $form->get('end')->getData();
            $period->setEnd(new \DateTime($time));

            $em = $this->getDoctrine()->getManager();
            $em->persist($period);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le nouveau creneau type a bien été créé !');
            return $this->redirectToRoute('period');
        }

        return $this->render('admin/period/new.html.twig',array(
            "form" => $form->createView()
        ));
    }

    /**
     * @Route("/edit/{id}", name="period_edit")
     * @Security("has_role('ROLE_ADMIN')")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request,Period $period)
    {
        $session = new Session();

        $form = $this->createForm('AppBundle\Form\PeriodType',$period);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $time = $form->get('start')->getData();
            $period->setStart(new \DateTime($time));
            $time = $form->get('end')->getData();
            $period->setEnd(new \DateTime($time));

            $em = $this->getDoctrine()->getManager();
            $em->persist($period);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le créneau type a bien été édité !');
            return $this->redirectToRoute('period');
        }

        $form->get('start')->setData($period->getStart()->format('H:i'));
        $form->get('end')->setData($period->getEnd()->format('H:i'));

        $delete_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('period_delete', array('id' => $period->getId())))
            ->setMethod('DELETE')
            ->getForm();

        return $this->render('admin/period/edit.html.twig',array(
            "form" => $form->createView(),
            "delete_form" => $delete_form->createView(),
        ));
    }

    /**
     * Deletes a period entity.
     *
     * @Route("/period/{id}", name="period_delete")
     * @Security("has_role('ROLE_ADMIN')")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Period $period)
    {
        $session = new Session();

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('period_delete', array('id' => $period->getId())))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($period);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le créneau type a bien été supprimé !');
        }

        return $this->redirectToRoute('period');

    }
    
}
