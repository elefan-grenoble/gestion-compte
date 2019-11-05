<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use Doctrine\Common\Collections\ArrayCollection;
use phpDocumentor\Reflection\Types\Array_;
use Symfony\Component\DependencyInjection\Container;

class ShiftService
{

    protected $em;
    protected $due_duration_by_cycle;
    protected $min_shift_duration;
    private $newUserStartAsBeginner;
    private $unlimitedBookDuration;

    public function __construct($em, $due_duration_by_cycle, $min_shift_duration, $newUserStartAsBeginner, $unlimitedBookDuration)
    {
        $this->em = $em;
        $this->due_duration_by_cycle = $due_duration_by_cycle;
        $this->min_shift_duration = $min_shift_duration;
        $this->newUserStartAsBeginner = $newUserStartAsBeginner;
        $this->unlimitedBookDuration = $unlimitedBookDuration;
    }

    /**
     * Return the remaining amount of time to book by the given membership in the current cycle
     * @param Membership $member
     * @return mixed
     */
    public function remainingToBook(Membership $member)
    {
        return $this->due_duration_by_cycle - $member->getTimeCount($member->endOfCycle());
    }

    /**
     * Check if a beneficiary can book on the given cycle
     * @param Beneficiary $beneficiary
     * @param int $cycle
     * @return bool
     */
    public function canBookOnCycle(Beneficiary $beneficiary, $cycle)
    {
        return $this->canBookDuration($beneficiary, $this->min_shift_duration, $cycle);
    }

    /**
     * Check if a beneficiary can book on the current and next cycles
     * @param Beneficiary $beneficiary
     * @return bool
     */
    public function canBookSomething(Beneficiary $beneficiary)
    {
        return $this->canBookOnCycle($beneficiary, 0) || $this->canBookOnCycle($beneficiary, 1);
    }

    /**
     * Check if a beneficiary can book a specific duration on the given cycle
     * @param Beneficiary $beneficiary
     * @param $duration
     * @param int $cycle
     * @return bool
     */
    public function canBookDuration(Beneficiary $beneficiary, $duration, $cycle = 0)
    {
        if (true === $this->unlimitedBookDuration) {
            return true;
        }

        $member = $beneficiary->getMembership();
        $beneficiary_counter = $beneficiary->getTimeCount($cycle);

        //check if beneficiary booked time is ok
        //if timecount < due_duration_by_cycle : some shift to catchup, can book more than what's due
        if ($member->getTimeCount($member->endOfCycle($cycle)) >= $this->due_duration_by_cycle && $beneficiary_counter >= $this->due_duration_by_cycle) { //Beneficiary is already ok
            return false;
        }

        // Time count before the end of cycle
        $timeCounter = $member->getTimeCount($member->endOfCycle($cycle));

        // Check if there is some time to catchup for the membership
        if ($duration + $timeCounter <= ($cycle + 1) * $this->due_duration_by_cycle) {
            return true;
        }

        // No time to catchup, check if this beneficiary can book on this cycle
        return $duration + $beneficiary_counter <= ($cycle + 1) * $this->due_duration_by_cycle;
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
     * Get beneficiaries who can book for the current and next cycles
     *
     * @param Membership $member
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBeneficiariesWhoCanBook(Membership $member)
    {
        return $member->getBeneficiaries()->filter(function ($beneficiary) {
            return $this->canBookSomething($beneficiary);
        });
    }

    /**
     * Get beneficiaries who can book for the given cycle
     *
     * @param Membership $member
     * @param int $cycle
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBeneficiariesWhoCanBookForCycle(Membership $member, $cycle = 0)
    {
        return $member->getBeneficiaries()->filter(function ($beneficiary) use ($cycle) {
            return $this->canBookOnCycle($beneficiary, $cycle);
        });
    }

    public function isShiftBookable(Shift $shift, Beneficiary $beneficiary = null)
    {
        // Do not book old or locked shifts
        if ($shift->getIsPast() || $shift->isLocked()) {
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
        if ($this->isBeginner($beneficiary) && $this->isShiftEmpty($shift)) {
            return false;
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

    /**
     * Check if the beneficiary is a beginner, eg : no shift completed
     * @param Beneficiary $beneficiary
     * @return bool
     */
    public function isBeginner(Beneficiary $beneficiary)
    {
        if (!$this->newUserStartAsBeginner) {
            return false;
        }

        return !$this->hasPreviousValidShifts($beneficiary);
    }

    /**
     * Check if the given beneficiary did at least one shift
     * @param Beneficiary $beneficiary
     * @return bool
     */
    public function hasPreviousValidShifts(Beneficiary $beneficiary)
    {
        $shifts = $beneficiary->getShifts()->filter(function (Shift $shift) {
            return $shift->getStart() < new \DateTime('now') && !$shift->getIsDismissed();
        });

        return $shifts->count() > 0;
    }

    /**
     * Check if the bucket of the given shift doesn't contain any shifters
     * @param $shift
     * @return bool
     */
    public function isShiftEmpty($shift)
    {
        $shifts = $this->em->getRepository('AppBundle:Shift')->findAlreadyBookedShiftsOfBucket($shift);
        return count($shifts) === 0;
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

    public function generateShiftBucketsByDayAndJob($shifts)
    {
        $bucketsByDay = array();
        foreach ($shifts as $shift) {
            $day = $shift->getStart()->format("d m Y");
            $job = $shift->getJob()->getId();
            $interval = $shift->getIntervalCode();
            if (!isset($bucketsByDay[$day])) {
                $bucketsByDay[$day] = array();
            }
            if (!isset($bucketsByDay[$day][$job])) {
                $bucketsByDay[$day][$job] = array();
            }
            if (!isset($bucketsByDay[$day][$job][$interval])) {
                $bucket = new ShiftBucket();
                $bucketsByDay[$day][$job][$interval] = $bucket;
            }
            $bucketsByDay[$day][$job][$interval]->addShift($shift);
        }
        return $bucketsByDay;
    }

    /**
     * @param $shifts
     * @return array
     */
    public function generateShiftBuckets($shifts)
    {
        $buckets = array();
        foreach ($shifts as $shift) {
            $key = $shift->getIntervalCode().$shift->getJob()->getId();
            if (!isset($buckets[$key])) {
                $bucket = new ShiftBucket();
                $buckets[$key] = $bucket;
            }
            $buckets[$key]->addShift($shift);
        }
        return $buckets;
    }

    /**
     * Check if the given cycle is after the registration of this member
     * @param Membership $membership
     * @param $cycle
     * @return bool
     */
    public function hasCycle(Membership $membership, $cycle)
    {
        /** @var Registration $firstRegistration */
        $firstRegistration = $membership->getRegistrations()->first();
        if (!$firstRegistration) {
            return false;
        }
        $registrationDate = $firstRegistration->getDate();
        $startOfCycle = $membership->startOfCycle($cycle);

        return $registrationDate < $startOfCycle;
    }

    /**
     * Create a ShiftBucket from a single Shift
     * @param Shift $shift
     * @return ShiftBucket
     */
    public function getShiftBucketFromShift(Shift $shift)
    {
        $shiftBucket = new ShiftBucket();
        $shifts = $this->em->getRepository('AppBundle:Shift')->findBy([
            'job' => $shift->getJob(),
            'start' => $shift->getStart(),
            'end' => $shift->getEnd()
        ]);
        $shiftBucket->addShifts($shifts);

        return $shiftBucket;
    }
}
