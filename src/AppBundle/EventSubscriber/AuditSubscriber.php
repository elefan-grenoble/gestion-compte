<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Service\AuditLogger;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class which listens on Doctrine events and writes an audit log of any entity changes made via Doctrine.
 */
class AuditSubscriber implements EventSubscriber
{

    const IGNORED_ATTRIBUTES = [
        'password',
        'lastLogin',
        'updatedAt',
        'updatedBy',
    ];

    private AuditLogger $auditLogger;
    private SerializerInterface $serializer;

    public function __construct(AuditLogger $auditLogger, SerializerInterface $serializer) {
        $this->auditLogger = $auditLogger;
        $this->serializer = $serializer;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
            Events::postRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityManager = $args->getObjectManager();
        $this->log($entity, 'insert', $entityManager);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityManager = $args->getObjectManager();
        $this->log($entity, 'update', $entityManager);
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        // We need to store the entity in a temporary array here, because the entity's ID is no longer
        // available in the postRemove event.
        // Deprecated in PHP 8.2 : https://wiki.php.net/rfc/deprecate_dynamic_properties
        // See:
        // - https://github.com/doctrine/orm/issues/2326
        // - https://github.com/doctrine/orm/pull/10188
        $entity->historicalId = $entity->getId();
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityManager = $args->getObjectManager();
        $this->log($entity, 'delete', $entityManager);
    }

    // This is the function which calls the AuditLogger service, constructing
    // the call to `AuditLogger::log()` with the appropriate parameters.
    private function log($entity, string $action, EntityManagerInterface $em): void
    {
        $entityClass = get_class($entity);
        // If the class is AuditLog entity, ignore. We don't want to audit our own audit logs!
        if ($entityClass === 'AppBundle\Entity\AuditLog') {
            return;
        }
        $entityId = $entity->getId();
        $entityType = str_replace('AppBundle\Entity\\', '', $entityClass);
        // The Doctrine unit of work keeps track of all changes made to entities.
        $uow = $em->getUnitOfWork();
        if ($action === 'delete') {
            $entityData = $this->serializer->normalize($entity);
            $entityId = $entity->historicalId;
        } elseif ($action === 'insert') {
            $entityData = $this->serializer->normalize($entity);
        } else {
            // For updates, we get the change set from Doctrine's Unit of Work manager.
            // This gives an array which contains only the fields which have
            // changed. We then just convert the numerical indexes to something
            // a bit more readable; "from" and "to" keys for the old and new values.
            $entityData = $uow->getEntityChangeSet($entity);
            foreach ($entityData as $field => $change) {
                if (in_array($field, self::IGNORED_ATTRIBUTES)) {
                    unset($entityData[$field]);
                } else {
                    $entityData[$field] = $this->formatChange($change);
                }
            }
        }
        if (sizeof($entityData) > 0) {
            $this->auditLogger->log($entityType, $entityId, $action, $entityData);
        }
    }

    private function formatChange($change) {
        if (is_string($change[0]) && is_string($change[1]) && strlen($change[0]) + strlen($change[1]) > 60) {
            $a1 = explode("\r\n" , $change[0]);
            $a2 = explode("\r\n", $change[1]);
            return [ 'from' => implode("\r\n",array_diff($a1, $a2)), 'to' => implode("\r\n",array_diff($a2, $a1)) ];
        } else {
            return [ 'from' => $this->formatAttribute($change[0]), 'to' => $this->formatAttribute($change[1]) ];
        }
    }

    private function formatAttribute($value) {
        if ($value instanceof \DateTimeInterface) {
            return $this->serializer->normalize($value);
        } else {
            return $value;
        }
    }
}
