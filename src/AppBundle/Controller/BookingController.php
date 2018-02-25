<?php

namespace AppBundle\Controller;

use DateTime;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
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
     * @Method({"GET","POST"})
     */
    public function indexAction(Request $request)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        if ($current_app_user->getBeneficiaries()->count()<1){
            $session->getFlashBag()->add('error', 'Oups, tu n\'as pas de bénéficiaire enregistré !');
            return $this->redirectToRoute('homepage');
        }else{
            $remainder = $current_app_user->getRemainder();
            if (intval($remainder->format("%R%a"))<0){
                $session->getFlashBag()->add('warning', 'Oups, ton adhésion  a expiré il y a '.$remainder->format('%a jours').'... n\'oublie pas de ré-adhérer pour effectuer ton bénévolat !');
                return $this->redirectToRoute('homepage');
            }
        }

        $beneficiaryForm = $this->createFormBuilder()
            ->setAction($this->generateUrl('booking'))
            ->setMethod('POST')
            ->add('beneficiary', EntityType::class, array(
                'label' => 'Reserver un pour',
                'required' => true,
                'class' => 'AppBundle:Beneficiary',
                'choices' => $current_app_user->getBeneficiaries(),
                'choice_label' => 'firstname',
                'multiple' => false,
            ))
            ->getForm();

        $beneficiaryForm->handleRequest($request);

        if ($beneficiaryForm->isSubmitted() && $beneficiaryForm->isValid() || $current_app_user->getBeneficiaries()->count()==1) {

            if ($current_app_user->getBeneficiaries()->count() > 1){
                $beneficiary = $beneficiaryForm->get('beneficiary')->getData();
            }else{
                $beneficiary = $current_app_user->getBeneficiaries()->first();
            }
            $em = $this->getDoctrine()->getManager();
            $shifts = $em->getRepository('AppBundle:Shift')->findFutures($beneficiary->getRoles());

            $hours = array();
            for ($i = 6; $i < 22; $i++) { //todo put this in conf
                $hours[] = $i;
            }

            $bucketsByDay = array();
            foreach ($shifts as $shift) {
                $day = $shift->getStart()->format("d m Y");
                $job = $shift->getJob()->getId();
                $interval = $shift->getIntervalCode();
                if (!isset($bucketsByDay[$day])) {
                    $bucketsByDay[$day] = array();
                }
                if (!isset($bucketsByDay[$day][$job])) {
                    $bucketsByDay[$day][$job] = array();
                }
                if (!isset($bucketsByDay[$day][$job][$interval])) {
                    $bucket = new ShiftBucket();
                    $bucketsByDay[$day][$job][$interval] = $bucket;
                }
                $bucketsByDay[$day][$job][$interval]->addShift($shift);
            }

            return $this->render('booking/index.html.twig', [
                'bucketsByDay' => $bucketsByDay,
                'hours' => $hours,
                'beneficiary' => $beneficiary,
                'jobs' => $em->getRepository('AppBundle:Job')->findAll()
            ]);
        }else{
            return $this->render('booking/index.html.twig', [
                'beneficiary_form' => $beneficiaryForm->createView(),
            ]);
        }

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

        if ($shift->getShifter() && !$shift->getIsDismissed()) {
            $session->getFlashBag()->add("error", "Désolé, ce créneau est déjà réservé");
            return $this->redirectToRoute("booking");   
        }

        $beneficiaryId = $request->get("beneficiaryId");

        $em = $this->getDoctrine()->getManager();

        $beneficiary = $em->getRepository('AppBundle:Beneficiary')->find($beneficiaryId);

        if (!$shift->getBooker()) {
            $shift->setBooker($beneficiary);
            $shift->setBookedTime(new DateTime('now'));
        }
        $shift->setShifter($beneficiary);
        $shift->setIsDismissed(false);
        $shift->setDismissedReason(null);
        $shift->setDismissedTime(null);

        $em->persist($shift);
        $em->flush();

        return $this->redirectToRoute('homepage');
    }

    /**
     * Dismiss a booked shift.
     *
     * @Route("/shift/{id}/dismiss", name="shift_dismiss")
     * @Method("POST")
     */
    public function dismissShift(Request $request, Shift $shift)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        if (!$current_app_user->getBeneficiaries()->contains($shift->getShifter())) {
            $session->getFlashBag()->add('error', 'Oups, ce créneau ne vous appartient pas !');
            return $this->redirectToRoute('booking');
        }

        $em = $this->getDoctrine()->getManager();

        $shift->setIsDismissed(true);
        $shift->setDismissedTime(new DateTime('now'));
        $shift->setDismissedReason($request->get("reason"));
        $shift->setShifter($shift->getBooker());

        $em->persist($shift);
        $em->flush();

        return $this->redirectToRoute('homepage');
    }

}
