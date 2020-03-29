<?php

namespace App\Controller;

use App\Entity\Job;
use App\Entity\Shift;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use App\Form\ShiftType;

/**
 * @Route("shift")
 */
class ShiftController extends Controller
{

    /**
     * @Route("/new", name="shift_new")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $shift = new Shift();

        $em = $this->getDoctrine()->getManager();
        $job = $em->getRepository(Job::class)->findOneBy(array());

        if (!$job) {
            $session->getFlashBag()->add('warning', 'Commençons par créer un poste de bénevolat');
            return $this->redirectToRoute('job_new');
        }

        $form = $this->createForm(ShiftType::class, $shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $request->request->all();

            if (count($data) === 1){
                $number = array_values($data)[0]["number"];

                while (1 < $number ){
                    $s = clone($shift);
                    $em->persist($s);
                    $number --;
                }
            }

            $em->persist($shift);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le créneau a bien été créé !');
            return $this->redirectToRoute('booking_admin');
        }

        return $this->render('admin/shift/new.html.twig', array(
            "form" => $form->createView()
        ));
    }

    /**
     * remove a shift.
     *
     * @Route("/shift/{id}", name="shift_delete")
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @Method("DELETE")
     */
    public function removeShiftAction(Request $request, Shift $shift)
    {
        $session = new Session();

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('shift_delete', array('id' => $shift->getId())))
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($shift);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le créneau a bien été supprimé !');
        }

        return $this->redirectToRoute('booking_admin');
    }

}
