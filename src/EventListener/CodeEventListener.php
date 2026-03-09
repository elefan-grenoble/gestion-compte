<?php

namespace App\EventListener;

use App\Event\CodeNewEvent;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;

class CodeEventListener
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
    public function onCodeNew(CodeNewEvent $event)
    {
        $this->logger->info('Code Listener: onCodeNew');
        //        $code = $event->getCode();
        //        $display = $event->getDisplay();
    }
}
