<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\User;
use AppBundle\Entity\Code;
use AppBundle\Event\CodeNewEvent;
use AppBundle\Event\CommissionJoinOrLeaveEvent;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;

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
