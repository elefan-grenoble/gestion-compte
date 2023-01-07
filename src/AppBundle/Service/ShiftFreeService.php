<?php

namespace AppBundle\Service;

use AppBundle\Entity\ShiftFreeLog;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class ShiftFreeLogger
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

    public function log(Shift $shift, Beneficiary $beneficiary): void
    {
        $user = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;
        $request = $this->requestStack->getCurrentRequest();
        $log = new ShiftFreeLog;
        $log->setShift($shift);
        $log->setBeneficiary($beneficiary);
        $log->setReason($shift->getReason());
        $log->setCreatedBy($user);
        $log->setRequestRoute($request->get('_route'));
        $this->em->persist($log);
        $this->em->flush();
    }
}
