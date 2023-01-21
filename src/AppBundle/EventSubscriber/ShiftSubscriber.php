<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Service\ShiftFreeLogger;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

class ShiftSubscriber implements EventSubscriber
{
    protected $logger;
    private ShiftFreeLogger $shiftFreeLogger;

    public function __construct(LoggerInterface $logger, ShiftFreeLogger $shiftFreeLogger) {
        $this->logger = $logger;
        $this->shiftFreeLogger = $shiftFreeLogger;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postUpdate,
        ];
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->logger->info("Shift Subscriber: postUpdate");
        $entity = $args->getObject();
        $entityClass = get_class($entity);
        // $entity is not a Shift
        if ($entityClass !== 'AppBundle\Entity\Shift') {
            return;
        }
        $this->logger->info("Shift Subscriber: postUpdate");

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $entityData = $uow->getEntityChangeSet($entity);
        // Shifter does not change
        if(!array_key_exists('shifter', $entityData)) {
            return;
        }
        $this->logger->info("Shift Subscriber: postUpdate");
        $this->logger->info($entityData['shifter'][0]);
        $this->logger->info($entityData['shifter'][1]);
        // Shifter does not change from 'something' to null
        if (is_null($entityData['shifter'][0]) || !is_null($entityData['shifter'][1])) {
            return;
        }
        $this->logger->info("Shift Subscriber: postUpdate");
        $this->shiftFreeLogger->log($entity, $entityData['shifter'][0]);
    }
}
