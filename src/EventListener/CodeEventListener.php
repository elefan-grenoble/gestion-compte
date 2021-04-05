<?php

namespace App\EventListener;

use App\Entity\User;
use App\Entity\Code;
use App\Event\CodeNewEvent;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;

class CodeEventListener
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param CodeNewEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onCodeNew(CodeNewEvent $event)
    {
        $this->logger->info("Code Listener: onCodeNew");
    }

}
