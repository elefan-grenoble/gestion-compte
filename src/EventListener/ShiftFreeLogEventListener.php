<?php

namespace App\EventListener;

use App\Entity\Membership;
use App\Entity\Shift;
use App\Event\ShiftFreedEvent;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;

class ShiftFreeLogEventListener
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
     * @param ShiftFreedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onShiftFreed(ShiftFreedEvent $event)
    {
        $this->logger->info("Shift Free Log Listener: onShiftFreed");
        $log = $this->container->get('shift_free_log_service')->initShiftFreeLog($event->getShift(), $event->getBeneficiary(), $event->getFixe(), $event->getReason());
        $this->em->persist($log);
        $this->em->flush();
    }
}
