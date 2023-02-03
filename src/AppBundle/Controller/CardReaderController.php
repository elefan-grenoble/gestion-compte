<?php

namespace AppBundle\Controller;

use AppBundle\Entity\BookedShift;
use AppBundle\Entity\Code;
use AppBundle\Entity\HelloassoPayment;
use AppBundle\Entity\Membership;
use AppBundle\Entity\SwipeCard;
use AppBundle\Event\SwipeCardEvent;
use AppBundle\Event\ShiftValidatedEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Booking controller.
 *
 * @Route("card_reader")
 */
class CardReaderController extends Controller
{
    /**
     * @var boolean
     */
    private $swipeCardLogging;
    private $swipeCardLoggingAnonymous;

    public function __construct(string $swipeCardLogging, string $swipeCardLoggingAnonymous)
    {
        $this->swipeCardLogging = $swipeCardLogging;
        $this->swipeCardLoggingAnonymous = $swipeCardLoggingAnonymous;
    }

    /**
     * @Route("/check", name="card_reader_check", methods={"POST"})
     */
    public function checkAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $code = $request->get('swipe_code');

        if (!$code) {
            return $this->redirectToRoute('cardReader');
        }
        if (!SwipeCard::checkEAN13($code)) {
            return $this->redirectToRoute('cardReader');
        }

        $code = substr($code, 0, -1); //remove controle
        $card = $em->getRepository('AppBundle:SwipeCard')->findOneBy(array('code' => $code, 'enable' => 1));

        if (!$card) {
            $session->getFlashBag()->add("error", "Oups, ce badge n'est pas actif ou n'existe pas");
        } else {
            $beneficiary = $card->getBeneficiary();
            $membership = $beneficiary->getMembership();
            $cycle_end = $this->get('membership_service')->getEndOfCycle($membership, 0);
            $counter = $membership->getTimeCount($cycle_end);
            if ($this->swipeCardLogging) {
                $dispatcher = $this->get('event_dispatcher');
                if ($this->swipeCardLoggingAnonymous) {
                    $card = null;
                }
                $dispatcher->dispatch(SwipeCardEvent::SWIPE_CARD_SCANNED, new SwipeCardEvent($card, $counter));
            }
            $shifts = $em->getRepository('AppBundle:Shift')->getOnGoingShifts($beneficiary);
            $dispatcher = $this->get('event_dispatcher');
            foreach ($shifts as $shift) {
                if ($shift->getWasCarriedOut() == 0) {
                    $shift->validateShiftParticipation();
                    $em->persist($shift);
                    $em->flush();
                    $dispatcher->dispatch(ShiftValidatedEvent::NAME, new ShiftValidatedEvent($shift));
                }
            }
            return $this->render('user/check.html.twig', [
                'beneficiary' => $beneficiary,
                'counter' => $counter
            ]);
        }

        return $this->redirectToRoute('cardReader');
    }
}
