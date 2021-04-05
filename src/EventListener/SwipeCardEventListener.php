<?php

namespace App\EventListener;

use App\Entity\SwipeCardLog;
use App\Event\SwipeCardEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SwipeCardEventListener implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents()
    {
        return [
            SwipeCardEvent::SWIPE_CARD_SCANNED => 'onSwipeCardScanned'
        ];
    }

    public function onSwipeCardScanned(SwipeCardEvent $event)
    {
        $log = new SwipeCardLog();
        $log->setDate(new \DateTime());
        $log->setCounter($event->getCounter());
        $this->em->persist($log);;
        $this->em->flush();
    }
}
