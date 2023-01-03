<?php

namespace AppBundle\Service;

use AppBundle\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class AuditLogger
{
    private EntityManagerInterface $em;
    protected $requestStack;
    private $tokenStorage;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack, TokenStorage $tokenStorage)
    {
        $this->em = $entityManager;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }

    public function log(string $entityType, string $entityId, string $action, array $eventData): void
    {
        $user = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;
        $request = $this->requestStack->getCurrentRequest();
        $log = new AuditLog;
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setAction($action);
        $log->setEventData($eventData);
        $log->setUser($user);
        $log->setRequestRoute($request->get('_route'));
        $this->em->persist($log);
        $this->em->flush();
    }
}
