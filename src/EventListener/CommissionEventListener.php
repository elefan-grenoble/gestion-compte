<?php

namespace App\EventListener;

use App\Event\CommissionJoinOrLeaveEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CommissionEventListener
{
    protected $em;
    protected $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
    }

    /**
     * @param CommissionJoinOrLeaveEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onLeave(CommissionJoinOrLeaveEvent $event)
    {
        $this->logger->info("Commission Listener: onLeave");
        $beneficiary = $event->getBeneficiary();
        $commission = $event->getCommission();

        if ($commission->getOwners()->contains($beneficiary)){
            $beneficiary->setOwn(null);
            $this->em->persist($beneficiary);
            $this->em->flush();
        }
    }

    /**
     * @param CommissionJoinOrLeaveEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onJoin(CommissionJoinOrLeaveEvent $event)
    {
        $this->logger->info("Commission Listener: onJoin");
    }

}
