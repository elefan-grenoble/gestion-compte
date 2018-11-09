<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Container;

class ShiftService
{

    protected $em;
    protected $due_duration_by_cycle;
    protected $min_shift_duration;

    public function __construct($em, $due_duration_by_cycle, $min_shift_duration)
    {
        $this->em = $em;
        $this->due_duration_by_cycle = $due_duration_by_cycle;
        $this->min_shift_duration = $min_shift_duration;
    }

    public function remainingToBook(Membership $member)
    {
        return $this->due_duration_by_cycle - $member->getTimeCount($member->endOfCycle());
    }


    public function canBookOnCycle(Beneficiary $beneficiary, $cycle)
    {
        return $this->canBookDuration($beneficiary, $this->min_shift_duration, $cycle);
    }

    public function canBookSomething(Beneficiary $beneficiary)
    {
        return $this->canBookDuration($beneficiary, $this->min_shift_duration, 0);
    }

    public function canBookDuration(Beneficiary $beneficiary, $duration, $cycle = 0)
    {
        $member = $beneficiary->getMembership();
        $beneficiary_counter = $beneficiary->getTimeCount($cycle);

        //check if beneficiary booked time is ok
        //if timecount < due_duration_by_cycle : some shift to catchup, can book more than what's due
        if ($member->getTimeCount($member->endOfCycle($cycle)) >= $this->due_duration_by_cycle && $beneficiary_counter >= $this->due_duration_by_cycle) { //Beneficiary is already ok
            return false;
        }

        //time count at start of cycle (before decrease)
        $timeCounter = $member->getTimeCount($member->startOfCycle($cycle));
        //time count at start of cycle  (after decrease)
        if ($timeCounter > $this->due_duration_by_cycle) {
            $timeCounter = 0;
        } else {
            $timeCounter -= $this->due_duration_by_cycle;
        }
        // duration of shift + what beneficiary already booked for cycle + timecount (may be < 0) minus due should be <= what can membership book for this cycle
        return ($duration + $beneficiary_counter + $timeCounter <= ($cycle + 1) * $this->due_duration_by_cycle);
    }

    /**
     * Get total shift time for a cycle
     * @param Membership $member
     * @return float|int
     */
    public function shiftTimeByCycle(Membership $member)
    {
        return $this->due_duration_by_cycle * count($member->getBeneficiaries());
    }

    /**
     * Get beneficiaries who can still book
     *
     * @param Membership $member
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBeneficiariesWhoCanBook(Membership $member)
    {
        return $member->getBeneficiaries()->filter(function ($beneficiary) {
            return $this->canBookDuration($beneficiary, $this->min_shift_duration, 0);
        });
    }

    public function isShiftBookable(Shift $shift, Beneficiary $beneficiary = null)
    {
        // Do not book old
        if ($shift->getIsPast()) {
            return false;
        }
        // Do not book already booked
        if ($shift->getShifter() && !$shift->getIsDismissed()) {
            return false;
        }
        // Do not book pre-booked shift
        if ($shift->getLastShifter() && $beneficiary->getId() != $shift->getLastShifter()->getId()) {
            return false;
        }
        if (!$beneficiary) {
            return true;
        }
        // Do not book shift i do not know how to handle (formation)
        if ($shift->getFormation() && !$beneficiary->getFormations()->contains($shift->getFormation())) {
            return false;
        }

        $member = $beneficiary->getMembership();
        if ($member->isWithdrawn())
            return false;

        if ($member->getFirstShiftDate() > $shift->getStart())
            return false;

        // First shift ever of the beneficiary, check he or she is not the first one to book the bucket
        if ($this->isBeginner($beneficiary)) {
            $shifts = $this->em->getRepository('AppBundle:Shift')->findAlreadyBookedShiftsOfBucket($shift);
            if (count($shifts) == 0) {
                return false;
            }
        }

        $current_cycle = $this->getShiftCycleIndex($shift, $member);

        if ($member->getFrozen()) {
            //current cycle : cannot book when frozen
            if (!$current_cycle)
                return false;
            //next cycle : cannot book if frozen
            if ($current_cycle > 0 && !$member->getFrozenChange())
                return false;
        }

        return $this->canBookDuration($beneficiary, $shift->getDuration(), $current_cycle);
    }

    public function isBeginner(Beneficiary $beneficiary)
    {
        $shifts = $beneficiary->getShifts()->filter(function (Shift $shift) {
            return $shift->getStart() < new \DateTime('now') && !$shift->getIsDismissed();
        });

        return $shifts->count() == 0;
    }

    public function getShiftCycleIndex(Shift $shift, Membership $membership)
    {
        $current_cycle = 0;
        for ($cycle = 1; $cycle < 3; $cycle++) {
            if ($shift->getStart() > $membership->endOfCycle($cycle - 1)) {
                if ($shift->getStart() < $membership->endOfCycle($cycle)) {
                    $current_cycle = $cycle;
                    break;
                }
            }
        }
        return $current_cycle;
    }

    public function getBookableShifts(ShiftBucket $bucket, Beneficiary $beneficiary = null)
    {
        if (!$beneficiary) {
            $bookableShifts = $bucket->getShifts()->filter(function (Shift $shift) {
                return ($shift->getIsDismissed() || !$shift->getShifter()); //dismissed or free
            });
        } else {
            if ($bucket->canBookInterval($beneficiary)) {
                $bookableShifts = $bucket->getShifts()->filter(function (Shift $shift) use ($beneficiary) {
                    return $this->isShiftBookable($shift, $beneficiary);
                });
            } else {
                $bookableShifts = new ArrayCollection();
            }
        }
        return $bookableShifts;
    }

    /***
     * Renvoie le premier shift bookable.
     * @param ShiftBucket $bucket
     * @param Beneficiary|null $beneficiary
     * @return mixed|null
     */
    public function getFirstBookable(ShiftBucket $bucket, Beneficiary $beneficiary = null)
    {
        if ($beneficiary && $this->isBucketBookable($bucket, $beneficiary)) {
            $bookableShifts = $this->getBookableShifts($bucket, $beneficiary);
            $iterator = ShiftBucket::filterByFormations($bookableShifts, $beneficiary->getFormations())->getIterator();
            $iterator->uasort(function (Shift $a, Shift $b) use ($beneficiary) {
                return ShiftBucket::compareShifts($a, $b, $beneficiary);
            });
            $sorted = new \Doctrine\Common\Collections\ArrayCollection(iterator_to_array($iterator));
            return $sorted->isEmpty() ? null : $sorted->first();
        } else {
            return null;
        }
    }

    /***
     * Renvoie le nombre de shits bookable.
     * @param ShiftBucket $bucket
     * @param Beneficiary|null $beneficiary
     * @return int
     */
    public function getBookableShiftsCount(ShiftBucket $bucket, Beneficiary $beneficiary = null)
    {
        $bookableShifts = $this->getBookableShifts($bucket, $beneficiary);
        if (!$beneficiary)
            return count($bookableShifts);
        return count(ShiftBucket::filterByFormations($bookableShifts, $beneficiary->getFormations()));
    }

    public function isBucketBookable(ShiftBucket $bucket, Beneficiary $beneficiary = null)
    {
        return $this->getBookableShiftsCount($bucket, $beneficiary) > 0;
    }

    public function getShiftsForBeneficiary(ShiftBucket $bucket, Beneficiary $beneficiary)
    {
        $bookableShifts = $this->getBookableShifts($bucket, $beneficiary);
        $bookableIntersectFormations = ShiftBucket::shiftIntersectFormations($bookableShifts, $beneficiary->getFormations());
        return $bucket->getShifts()->filter(ShiftBucket::createShiftFilterCallback($bookableIntersectFormations));
    }
}
