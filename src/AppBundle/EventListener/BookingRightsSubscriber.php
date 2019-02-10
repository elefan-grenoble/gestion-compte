<?php

namespace AppBundle\EventListener;

use AppBundle\Event\ShiftEvent;
use AppBundle\Service\ShiftService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BookingRightsSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var ShiftService
     */
    private $shiftService;

    public function __construct(EntityManagerInterface $entityManager, ShiftService $shiftService)
    {
        $this->entityManager = $entityManager;
        $this->shiftService = $shiftService;
    }

    public static function getSubscribedEvents()
    {
        return array(
            ShiftEvent::EVENT_BOOKED => 'updateFirstShiftBookerRights',
            ShiftEvent::EVENT_DELETED => 'removeBookerFirstShiftRights',
            ShiftEvent::EVENT_DISMISSED => 'removeBookerFirstShiftRights',
            ShiftEvent::EVENT_FREED => 'removeBookerFirstShiftRights',
        );
    }

    public function updateFirstShiftBookerRights(ShiftEvent $event)
    {
        if (!$event->getShifter()) {
            return;
        }

        $shift = $event->getShift();
        $user = $event->getShifter()->getUser();

        // Apply the shoft first booker role if the user hasn't the role yet and if the shift is in its current cycle
        if (!$user->hasRole('ROLE_SHIFT_FIRST_BOOKER') && $this->shiftService->getShiftCycleIndex($shift, $user->getBeneficiary()->getMembership()) === 0) {
            $shift->setBeginner(true);
            $user->addRole('ROLE_SHIFT_FIRST_BOOKER');
            $this->entityManager->flush();
        }
    }

    public function removeBookerFirstShiftRights(ShiftEvent $event)
    {
        if (!$event->getShifter()) {
            return;
        }

        $shift = $event->getShift();
        $user = $event->getShifter()->getUser();

        if ($user->hasRole('ROLE_FIRST_SHIFT_BOOKER') && $shift->isBeginner()) {
            $user->removeRole('ROLE_FIRST_SHIFT_BOOKER');
            $this->entityManager->flush();
        }
    }
}