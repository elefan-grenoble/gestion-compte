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
    private $swipeCardLogging;
    private $swipeCardLoggingAnonymous;

    public function __construct(bool $swipeCardLogging, bool $swipeCardLoggingAnonymous)
    {
        $this->swipeCardLogging = $swipeCardLogging;
        $this->swipeCardLoggingAnonymous = $swipeCardLoggingAnonymous;
    }

    /**
     * @Route("/", name="card_reader_index")
     */
    public function indexAction(Request $request)
    {
        $this->denyAccessUnlessGranted('card_reader', $this->getUser());
        $em = $this->getDoctrine()->getManager();

        // in progress shifts
        $shifts_in_progress = $em->getRepository('AppBundle:Shift')->findInProgress();
        $buckets_in_progress = $this->get('shift_service')->generateShiftBuckets($shifts_in_progress);
        // upcoming shifts
        $shifts_upcoming = $em->getRepository('AppBundle:Shift')->findUpcomingToday();
        $buckets_upcoming = $this->get('shift_service')->generateShiftBuckets($shifts_upcoming);

        $dynamicContent = $em->getRepository('AppBundle:DynamicContent')->findOneByCode('CARD_READER')->getContent();

        return $this->render('card_reader/index.html.twig', [
            "buckets_in_progress" => $buckets_in_progress,
            "buckets_upcoming" => $buckets_upcoming,
            "dynamicContent" => $dynamicContent
        ]);
    }

    /**
     * @Route("/check", name="card_reader_check", methods={"POST"})
     */
    public function checkAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $code = $request->get('swipe_code');

        // verify code
        if (!$code) {
            return $this->redirectToRoute('card_reader_index');
        }
        if (!SwipeCard::checkEAN13($code)) {
            return $this->redirectToRoute('card_reader_index');
        }
        $code = substr($code, 0, -1);  // remove controle
        $card = $em->getRepository('AppBundle:SwipeCard')->findOneBy(array('code' => $code, 'enable' => 1));
        if (!$card) {
            $session->getFlashBag()->add("error", "Oups, ce badge n'est pas actif ou n'existe pas");
            return $this->redirectToRoute('card_reader_index');
        }

        // find corresponding beneficiary
        $beneficiary = $card->getBeneficiary();
        $member = $beneficiary->getMembership();

        // validate beneficiary ongoing shift(s)
        $ongoingShifts = $em->getRepository('AppBundle:Shift')->getOngoingShifts($beneficiary);
        $ongoingShiftsValidated = 0;
        if ($ongoingShifts) {
            foreach ($ongoingShifts as $shift) {
                if ($shift->getWasCarriedOut() == 0) {
                    $shift->validateShiftParticipation();

                    $em->persist($shift);
                    $em->flush();

                    $dispatcher = $this->get('event_dispatcher');
                    $dispatcher->dispatch(ShiftValidatedEvent::NAME, new ShiftValidatedEvent($shift));

                    $ongoingShiftsValidated += 1;
                }
            }

            $em->refresh($member);  // added to prevent from returning cached (old) data
        }

        $cycle_end = $this->get('membership_service')->getEndOfCycle($member, 0);
        $counter = $member->getShiftTimeCount($cycle_end);
        if ($this->swipeCardLogging) {
            if ($this->swipeCardLoggingAnonymous) {
                $card = null;
            }
            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(SwipeCardEvent::SWIPE_CARD_SCANNED, new SwipeCardEvent($card, $counter));
        }

        return $this->render('card_reader/check.html.twig', [
            'beneficiary' => $beneficiary,
            'counter' => $counter,
            'ongoingShifts' => $ongoingShifts,
            'ongoingShiftsValidated' => $ongoingShiftsValidated
        ]);
    }
}
