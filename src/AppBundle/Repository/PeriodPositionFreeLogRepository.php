<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Membership;
use AppBundle\Entity\PeriodPosition;

/**
 * PeriodPositionFreeLogRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PeriodPositionFreeLogRepository extends \Doctrine\ORM\EntityRepository
{
    public function getMemberPeriodPositionFreed(Membership $member)
    {
        $qb = $this->createQueryBuilder('ppfl')
            ->leftJoin('ppfl.periodPosition', 'pp')
            ->addSelect('pp')
            ->where('pp.shifter IN (:beneficiaries)')
            ->setParameter('beneficiaries', $member->getBeneficiaries());

        $qb->orderBy('ppfl.createdAt', 'DESC');

        return $qb
            ->getQuery()
            ->getResult();
    }
}
