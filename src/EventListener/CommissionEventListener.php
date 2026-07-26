<?php

namespace App\EventListener;

use App\Event\CommissionJoinOrLeaveEvent;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;

class CommissionEventListener
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
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function onLeave(CommissionJoinOrLeaveEvent $event)
    {
        $this->logger->info('Commission Listener: onLeave');
        $beneficiary = $event->getBeneficiary();
        $commission = $event->getCommission();

        if ($commission->getOwners()->contains($beneficiary)) {
            $beneficiary->setOwn(null);
            $this->em->persist($beneficiary);
            $this->em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function onJoin(CommissionJoinOrLeaveEvent $event)
    {
        $this->logger->info('Commission Listener: onJoin');
    }
}
