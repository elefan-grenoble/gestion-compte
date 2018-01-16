<?php

namespace AppBundle\Controller;

use AppBundle\Entity\BookedShift;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use DateTime;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\Date;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("booking")
 */
class BookingController extends Controller
{
    /**
     * @Route("/", name="booking")
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED', user)")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $shifts = $em->getRepository('AppBundle:Shift')->findFutures();

        $hours = array();
        for ($i = 6; $i < 22; $i++) {
            $hours[] = $i;
        }

        $shiftsByDay = array();
        foreach ($shifts as $shift) {
            $day = $shift->getStart()->format("d m Y");
            if (!isset($shiftsByDay[$day])) {
                $shiftsByDay[$day] = array();
            }
            $shiftsByDay[$day][] = $shift;
        }

        return $this->render('booking/index.html.twig', [
            'shiftsByDay' => $shiftsByDay,
            'hours' => $hours
        ]);
    }

    /**
     * Book a shift.
     *
     * @Route("/shift/{id}/book", name="shift_book")
     * @Method("POST")
     */
    public function bookShift(Request $request, Shift $shift)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        if ($shift->getRemainingShifters() <= 0) {
            $session->getFlashBag()->add("error", "Désolé, le créneau est plein");
            return $this->redirectToRoute("booking");   
        }

        $beneficiaryId = $request->get("beneficiaryId");

        $em = $this->getDoctrine()->getManager();

        $beneficiary = $em->getRepository('AppBundle:Beneficiary')->find($beneficiaryId);

        $bookedShift = $em->getRepository('AppBundle:BookedShift')->findFirstDismissed($shift);
        if (!$bookedShift) {
            $bookedShift = new BookedShift();
            $bookedShift->setShift($shift);
            $bookedShift->setBookedTime(new DateTime('now'));
            $bookedShift->setBooker($beneficiary);
        }
        $bookedShift->setShifter($current_app_user->getMainBeneficiary());
        $bookedShift->setIsDismissed(false);
        $bookedShift->setDismissedReason(null);
        $bookedShift->setDismissedTime(null);

        $em->persist($bookedShift);
        $em->flush();

        return $this->redirectToRoute('homepage');
    }

    /**
     * Dismiss a booked shift.
     *
     * @Route("/shift/{id}/dismiss", name="shift_dismiss")
     * @Method("POST")
     */
    public function dismissShift(Request $request, BookedShift $shift)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        if (!$current_app_user->getBeneficiaries()->contains($shift->getBooker())){
            $session->getFlashBag()->add('error', 'Oups, ce créneau ne vous appartient pas !');
            return $this->redirectToRoute('booking');
        }

        $em = $this->getDoctrine()->getManager();

        $shift->setIsDismissed(true);
        $shift->setDismissedTime(new DateTime('now'));
        $shift->setDismissedReason($request->get("reason"));

        $em->persist($shift);
        $em->flush();

        return $this->redirectToRoute('homepage');
    }

}
