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

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
        $shift = $event->getShift();
        $user = $shift->getBooker()->getUser();

        if (!$user->hasRole('ROLE_SHIFT_FIRST_BOOKER')) {
            $user->addRole('ROLE_SHIFT_FIRST_BOOKER');
            $this->entityManager->flush();
        }
    }
}