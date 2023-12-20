<?php

namespace App\EventListener;

use App\Entity\Membership;
use App\Entity\PeriodPosition;
use App\Event\PeriodPositionFreedEvent;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;

class PeriodPositionFreeLogEventListener
{
    protected $em;
    protected $logger;
    protected $container;

    public function __construct(EntityManager $entityManager, Logger $logger, Container $container)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->container = $container;
    }

    /**
     * @param PeriodPositionFreedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onPeriodPositionFreed(PeriodPositionFreedEvent $event)
    {
        $this->logger->info("PeriodPosition Free Log Listener: onPeriodPositionFreed");
        $log = $this->container->get('period_position_free_log_service')->initPeriodPositionFreeLog($event->getPeriodPosition(), $event->getBeneficiary(), $event->getBookedTime());
        $this->em->persist($log);
        $this->em->flush();
    }
}
