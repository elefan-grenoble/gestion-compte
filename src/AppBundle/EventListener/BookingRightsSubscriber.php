<?php

namespace AppBundle\EventListener;

use AppBundle\Event\ShiftBookedEvent;
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
            ShiftBookedEvent::NAME => 'updateFirstShiftBookerRights',
        );
    }

    public function updateFirstShiftBookerRights(ShiftBookedEvent $event)
    {
        $shift = $event->getShift();
        $user = $shift->getBooker()->getUser();

        if (!$user->hasRole('ROLE_SHIFT_FIRST_BOOKER')) {
            $user->addRole('ROLE_SHIFT_FIRST_BOOKER');
            $this->entityManager->flush();
        }
    }
}