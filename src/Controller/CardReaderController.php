<?php

namespace App\Controller;

use App\Entity\BookedShift;
use App\Entity\Code;
use App\Entity\HelloassoPayment;
use App\Entity\Membership;
use App\Entity\SwipeCard;
use App\Event\SwipeCardEvent;
use App\Event\ShiftValidatedEvent;
use App\Service\MembershipService;
use App\Service\ShiftService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Booking controller.
 *
 * @Route("card_reader")
 */
class CardReaderController extends AbstractController
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
    public function indexAction(Request $request, ShiftService $shift_service)
    {
        $this->denyAccessUnlessGranted('card_reader', $this->getUser());
        $em = $this->getDoctrine()->getManager();

        // in progress shifts
        $shifts_in_progress = $em->getRepository('App:Shift')->findInProgress();
        $buckets_in_progress = $shift_service->generateShiftBuckets($shifts_in_progress);
        // upcoming shifts
        $shifts_upcoming = $em->getRepository('App:Shift')->findUpcomingToday();
        $buckets_upcoming = $shift_service->generateShiftBuckets($shifts_upcoming);

        $dynamicContent = $em->getRepository('App:DynamicContent')->findOneByCode('CARD_READER')->getContent();

        return $this->render('card_reader/index.html.twig', [
            "buckets_in_progress" => $buckets_in_progress,
            "buckets_upcoming" => $buckets_upcoming,
            "dynamicContent" => $dynamicContent
        ]);
    }

    /**
     * @Route("/check", name="card_reader_check", methods={"POST"})
     */
    public function checkAction(Request $request, MembershipService $membership_service, EventDispatcherInterface $event_dispatcher)
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
        $card = $em->getRepository('App:SwipeCard')->findOneBy(array('code' => $code, 'enable' => 1));
        if (!$card) {
            $session->getFlashBag()->add("error", "Oups, ce badge n'est pas actif ou n'existe pas");
            return $this->redirectToRoute('card_reader_index');
        }

        // find corresponding beneficiary
        $beneficiary = $card->getBeneficiary();
        $member = $beneficiary->getMembership();

        // validate beneficiary ongoing shift(s)
        $now = new \Datetime('now');
        $now_plus_ten = new \Datetime('now +10 minutes');
        $ongoingShifts = $em->getRepository('App:Shift')->findShiftsForBeneficiaries([$beneficiary], null, null, $now_plus_ten, $now);
        $ongoingShiftsValidated = 0;
        if ($ongoingShifts) {
            foreach ($ongoingShifts as $shift) {
                if ($shift->getWasCarriedOut() == 0) {
                    $shift->validateShiftParticipation();

                    $em->persist($shift);
                    $em->flush();

                    $event_dispatcher->dispatch(ShiftValidatedEvent::NAME, new ShiftValidatedEvent($shift));

                    $ongoingShiftsValidated += 1;
                }
            }

            $em->refresh($member);  // added to prevent from returning cached (old) data
        }

        $cycle_end = $membership_service->getEndOfCycle($member, 0);
        $counter = $member->getShiftTimeCount($cycle_end);
        if ($this->swipeCardLogging) {
            if ($this->swipeCardLoggingAnonymous) {
                $card = null;
            }
            $event_dispatcher->dispatch(SwipeCardEvent::SWIPE_CARD_SCANNED, new SwipeCardEvent($card, $counter));
        }

        return $this->render('card_reader/check.html.twig', [
            'beneficiary' => $beneficiary,
            'counter' => $counter,
            'ongoingShifts' => $ongoingShifts,
            'ongoingShiftsValidated' => $ongoingShiftsValidated
        ]);
    }
}
