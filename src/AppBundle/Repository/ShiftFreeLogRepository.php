<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\PeriodPosition;

/**
 * ShiftFreeLogRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ShiftFreeLogRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * Get number of freed shifts for a given beneficiary, with possible filter on PeriodPosition
     * @param Beneficiary $beneficiary
     * @param PeriodPosition $position
     */
    public function getBeneficiaryShiftFreedCount(Beneficiary $beneficiary, PeriodPosition $position = null)
    {
        $qb = $this->createQueryBuilder('sfl')
            ->select('count(sfl.id)')
            ->where('sfl.beneficiary = :beneficiary')
            ->setParameter('beneficiary', $beneficiary);

        if ($position != null) {
            $qb = $qb->andwhere('sfl.fixe = 1')
                ->leftJoin('sfl.shift', 's')
                ->andwhere('s.position = :position')
                ->setParameter('position', $position);
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countMemberShiftsFreed($membership, $start_after, $end_before, $less_than_min_time_in_advance_days = null) {
        $qb = $this->createQueryBuilder('sfl')
            ->leftJoin('sfl.shift', 's')
            ->addSelect('s')
            ->select('count(sfl.id)')
            ->where('sfl.beneficiary IN (:beneficiaries)')
            ->andwhere('s.start > :start_after')
            ->andwhere('s.end < :end_before')
            ->setParameter('beneficiaries', $membership->getBeneficiaries())
            ->setParameter('start_after', $start_after)
            ->setParameter('end_before', $end_before);

        if ($less_than_min_time_in_advance_days) {
            $qb = $qb->andwhere("s.start < DATE_ADD(sfl.createdAt, :min_time_in_advance_days, 'day')")
                ->setParameter('min_time_in_advance_days', $less_than_min_time_in_advance_days);
        }

        return $qb->getQuery()
            ->getSingleScalarResult();
    }
}
