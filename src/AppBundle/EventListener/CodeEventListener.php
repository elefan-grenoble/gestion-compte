<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\User;
use AppBundle\Entity\Code;
use AppBundle\Event\CodeNewEvent;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;

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
     * @param CodeNewEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onCodeNew(CodeNewEvent $event)
    {
        $this->logger->info("Code Listener: onCodeNew");
//        $code = $event->getCode();
//        $display = $event->getDisplay();
    }

}
