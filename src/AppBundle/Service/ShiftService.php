<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Service\MembershipService;
use AppBundle\Service\BeneficiaryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use phpDocumentor\Reflection\Types\Array_;
use Symfony\Component\DependencyInjection\Container;

class ShiftService
{
    private $em;
    private $beneficiaryService;
    private $membershipService;
    private $due_duration_by_cycle;
    private $min_shift_duration;
    private $newUserStartAsBeginner;
    private $allowExtraShifts;
    private $maxTimeInAdvanceToBookExtraShifts;
    private $forbidShiftOverlapTime;
    private $use_fly_and_fixed;
    private $fly_and_fixed_allow_fixed_shift_free;
    private $use_time_log_saving;
    private $time_log_saving_shift_free_min_time_in_advance_days;

    public function __construct(EntityManagerInterface $em, BeneficiaryService $beneficiaryService, MembershipService $membershipService,
        $due_duration_by_cycle, $min_shift_duration, $newUserStartAsBeginner, $allowExtraShifts, $maxTimeInAdvanceToBookExtraShifts, $forbidShiftOverlapTime,
        $use_fly_and_fixed, $fly_and_fixed_allow_fixed_shift_free,
        $use_time_log_saving, $time_log_saving_shift_free_min_time_in_advance_days)
    {
        $this->em = $em;
        $this->beneficiaryService = $beneficiaryService;
        $this->membershipService = $membershipService;
        $this->due_duration_by_cycle = $due_duration_by_cycle;
        $this->min_shift_duration = $min_shift_duration;
        $this->newUserStartAsBeginner = $newUserStartAsBeginner;
        $this->allowExtraShifts = $allowExtraShifts;
        $this->maxTimeInAdvanceToBookExtraShifts = $maxTimeInAdvanceToBookExtraShifts;
        $this->forbidShiftOverlapTime = $forbidShiftOverlapTime;
        $this->use_fly_and_fixed = $use_fly_and_fixed;
        $this->fly_and_fixed_allow_fixed_shift_free = $fly_and_fixed_allow_fixed_shift_free;
        $this->use_time_log_saving = $use_time_log_saving;
        $this->time_log_saving_shift_free_min_time_in_advance_days = $time_log_saving_shift_free_min_time_in_advance_days;
    }

    /**
     * Return the remaining amount of time to book by the given membership in the current cycle
     * @param Membership $member
     * @return mixed
     */
    public function remainingToBook(Membership $member)
    {
        $cycle_end = $this->membershipService->getEndOfCycle($member);
        return $this->due_duration_by_cycle - $member->getShiftTimeCount($cycle_end);
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
     * Check if a beneficiary can book an extra shift
     * @param Beneficiary $beneficiary
     * @param Shift $shift
     * @return bool
     */
    public function canBookExtraShift(Beneficiary $beneficiary, Shift $shift)
    {
        if (true === $this->allowExtraShifts && NULL === $this->maxTimeInAdvanceToBookExtraShifts) {
            return true;
        }
        return true === $this->allowExtraShifts && $shift->isBefore($this->maxTimeInAdvanceToBookExtraShifts);
    }

    /**
     * Check if a beneficiary can book an extra shift bucket
     * @param Beneficiary $beneficiary
     * @param ShiftBucket $shiftBucket
     * @return bool
     */
    public function canBookExtraShiftBucket(Beneficiary $beneficiary, ShiftBucket $shiftBucket)
    {
        return $this->canBookExtraShift($beneficiary, $shiftBucket->getFirst());
    }

    /**
     * Check if a beneficiary can book on the current and next cycles
     * @param Beneficiary $beneficiary
     * @return bool
     */
    public function canBookSomething(Beneficiary $beneficiary)
    {
        if (true === $this->allowExtraShifts) {
            return true;
        }
        return $this->canBookOnCycle($beneficiary, 0) || $this->canBookOnCycle($beneficiary, 1);
    }

    /**
     * Check if a beneficiary do not have booked a shift that overlaps the current
     * @param Beneficiary $beneficiary
     * @param Shift $currentShift
     * @return bool
     */
    public function canBookShift(Beneficiary $beneficiary, Shift $currentShift) {
        if ($this->forbidShiftOverlapTime < 0) {
            return true;
        }
        $shifts = $beneficiary->getShifts()->filter(function ($shift) use ($currentShift) {
            $start = (clone $shift->getStart())->add(\DateInterval::createFromDateString($this->forbidShiftOverlapTime.' minutes'));
            $end = (clone $shift->getEnd())->sub(\DateInterval::createFromDateString($this->forbidShiftOverlapTime.' minutes'));
            return ($currentShift->getStart() < $end
                && $currentShift->getEnd() >= $shift->getEnd())
                || ($currentShift->getEnd() > $start
                && $currentShift->getStart() <= $shift->getStart());
        });
        return $shifts->count() == 0;
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
        if (true === $this->allowExtraShifts && NULL === $this->maxTimeInAdvanceToBookExtraShifts) {
            return true;
        }

        $member = $beneficiary->getMembership();
        $beneficiary_cycle_shift_duration = $this->beneficiaryService->getCycleShiftDurationSum($beneficiary, $cycle);
        $cycle_end = $this->membershipService->getEndOfCycle($member, $cycle);
        $membership_counter = $member->getShiftTimeCount($cycle_end);

        //check if beneficiary booked time is ok
        //if timecount < due_duration_by_cycle : some shift to catchup, can book more than what's due
        if ($membership_counter >= $this->due_duration_by_cycle && $beneficiary_cycle_shift_duration >= $this->due_duration_by_cycle) { //Beneficiary is already ok
            return false;
        }

        // Check if there is some time to catchup for the membership
        if ($duration + $membership_counter <= ($cycle + 1) * $this->due_duration_by_cycle) {
            return true;
        }

        // No time to catchup, check if this beneficiary can book on this cycle
        return $duration + $beneficiary_cycle_shift_duration <= ($cycle + 1) * $this->due_duration_by_cycle;
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
        if (!$beneficiary) {
            return true;
        }

        // Do not book old or locked or booked shifts
        if ($shift->getIsPast() || $shift->isLocked() || $shift->getShifter()) {
            return false;
        }
        // Do not book pre-booked shift
        if ($shift->getLastShifter() && $beneficiary->getId() != $shift->getLastShifter()->getId()) {
            return false;
        }
        // Do not book shift the beneficiary cannot handle (formation)
        if ($shift->getFormation() && !$beneficiary->getFormations()->contains($shift->getFormation())) {
            return false;
        }
        // First shift ever of the beneficiary, check he or she is not the first one to book the bucket
        if ($this->isBeginner($beneficiary) && $this->isShiftEmpty($shift)) {
            return false;
        }
        // Check that beneficiary did not book a shift that overlaps the current
        if (!$this->canBookShift($beneficiary, $shift)) {
            return false;
        }

        // membership rules (exemption, withdrawn, frozen)
        $member = $beneficiary->getMembership();
        if ($member->isCurrentlyExemptedFromShifts($shift->getStart())) {
            return false;
        }
        if ($member->isWithdrawn()) {
            return false;
        }
        if ($member->getFirstShiftDate() > $shift->getStart()) {
            return false;
        }
        if ($member->getFrozen()) {
            $cycle_end = $this->membershipService->getEndOfCycle($member);
            //current cycle : cannot book when frozen
            if ($shift->getStart() <= $cycle_end)
                return false;
            //next cycle : cannot book if frozen
            if ($shift->getStart() > $cycle_end && !$member->getFrozenChange())
                return false;
        }

        // TODO refactor code to remove shift_cycle
        // canBookDuration method should not use TimeLog but request shifts
        $shift_cycle = 0;
        for ($cycle = 0; $cycle < 3; $cycle++) {
            $current_cycle_end = $this->membershipService->getEndOfCycle($member, $cycle);
            if ($shift->getStart() <= $current_cycle_end) {
                $shift_cycle = $cycle;
                break;
            }
        }

        return $this->canBookDuration($beneficiary, $shift->getDuration(), $shift_cycle) or $this->canBookExtraShift($beneficiary, $shift);
    }

    /**
     * Check if the beneficiary is able to free the shift
     * @param Beneficiary $beneficiary
     * @param Shift $shift
     * @return bool
     */
    public function canFreeShift(Beneficiary $beneficiary, Shift $shift) {
        // cannot free a past or current shift
        if ($shift->getIsPast() || $shift->getIsCurrent()) {
            return false;
        }
        // cannot free a shift without shifter
        if (!$shift->getShifter()) {
            return false;
        }
        // can only free your own shift
        if ($shift->getShifter() != $beneficiary) {
            return false;
        }

        // Fly & fixed: check if there is a rule allowing to free fixed shifts
        if ($this->use_fly_and_fixed) {
            if ($shift->isFixe() && !$this->fly_and_fixed_allow_fixed_shift_free) {
                return false;
            }
        }

        // Time log saving: check if there is a min time in advance rule
        if ($this->use_time_log_saving) {
            if ($this->time_log_saving_shift_free_min_time_in_advance_days) {
                if ($shift->isBefore($this->time_log_saving_shift_free_min_time_in_advance_days . ' days')) {
                    return false;
                }
            }
        }

        return true;
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
            return $shift->getStart() < new \DateTime('now');
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

    public function getBookableShifts(ShiftBucket $bucket, Beneficiary $beneficiary = null)
    {
        if (!$beneficiary) {
            // return all free shifts
            $bookableShifts = $bucket->getShifts()->filter(function (Shift $shift) {
                return !$shift->getShifter();
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

        if (!$beneficiary) {
            return count($bookableShifts);
        }

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

    /**
     * build the bucket
     * group similar shifts together (same time & same job)
     */
    public function generateShiftBucketsByDayAndJob($shifts)
    {
        $bucketsByDay = array();

        foreach ($shifts as $shift) {
            $day = $shift->getStart()->format("d m Y");
            $jobId = $shift->getJob()->getId();
            $interval = $shift->getIntervalCode();

            if (!isset($bucketsByDay[$day])) {
                $bucketsByDay[$day] = array();
            }
            if (!isset($bucketsByDay[$day][$jobId])) {
                $bucketsByDay[$day][$jobId] = array();
            }
            if (!isset($bucketsByDay[$day][$jobId][$interval])) {
                $bucket = new ShiftBucket();
                $bucketsByDay[$day][$jobId][$interval] = $bucket;
            }
            $bucketsByDay[$day][$jobId][$interval]->addShift($shift);
        }

        return $bucketsByDay;
    }

    /**
     * Filter bucketsByDay by filling (empty / partial / full)
     */
    public function filterBucketsByDayAndJobByFilling($bucketsByDay, string $filling = null)
    {
        if ($filling) {
            foreach ($bucketsByDay as $day => $bucketsByJob) {
                foreach ($bucketsByJob as $jobId => $bucketByInterval) {
                    foreach ($bucketByInterval as $interval => $bucket) {
                        $nbShifts = count($bucket->getShifts());
                        $nbBookableShifts = count($this->getBookableShifts($bucket));
                        if (
                            ($filling == 'empty' and $nbBookableShifts != $nbShifts) ||
                            ($filling == 'full' and $nbBookableShifts != 0) ||
                            ($filling == 'partial' and ($nbBookableShifts == $nbShifts or $nbBookableShifts == 0))
                        ) {
                            unset($bucketsByDay[$day][$jobId][$interval]);
                            if (count($bucketsByDay[$day][$jobId]) == 0) {
                                unset($bucketsByDay[$day][$jobId]);
                                if (count($bucketsByDay[$day]) == 0) {
                                    unset($bucketsByDay[$day]);
                                }
                            }
                        }
                    }
                }
            }
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

    public function getMinimalShiftDuration()
    {
        return $this->min_shift_duration;
    }

    /**
     * Remove all empty shifts from an array of shift buckets
     * @param $buckets
     * @return array
     */
    public function removeEmptyShift($buckets)
    {
        foreach ($buckets as $bucket) {
            $bucket->removeEmptyShift();
        }
        return $buckets;
    }

    /**
     * Check if the beneficiary has shifts that match parameters
     * @param Beneficiary $beneficiary
     * @param Datetime $start_before
     * @param Datetime $start_after
     * @param Datetime $end_before
     * @return bool
     */
    public function isBeneficiaryHasShifts(Beneficiary $beneficiary, \Datetime $start_after, \Datetime $start_before, \Datetime $end_after)
    {
        return !$this->em->getRepository('AppBundle:Shift')->findShiftsForBeneficiary($beneficiary,
                $start_after,
                null,
                $start_before,
                $end_after)->isEmpty();
    }
}
