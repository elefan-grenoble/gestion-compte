<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\PeriodPosition;
use AppBundle\Entity\PeriodPositionFreeLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class PeriodPositionFreeLogService
{
    protected $em;
    protected $requestStack;
    private $tokenStorage;

    public function __construct(EntityManagerInterface $em, RequestStack $requestStack, TokenStorage $tokenStorage)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }

    public function initPeriodPositionFreeLog(PeriodPosition $period_position, Beneficiary $beneficiary)
    {
        $current_user = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;
        $request = $this->requestStack->getCurrentRequest();

        $log = new PeriodPositionFreeLog;
        $log->setPeriodPosition($period_position);
        $log->setBeneficiary($beneficiary);
        if (is_object($current_user)) {
            $log->setCreatedBy($current_user);
        }
        $log->setRequestRoute($request->get('_route'));

        return $log;
    }
}
