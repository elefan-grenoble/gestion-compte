<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use Symfony\Component\DependencyInjection\Container;

class ShiftService
{

    protected $container;
    protected $due_duration_by_cycle;
    protected $min_shift_duration;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->due_duration_by_cycle = $this->container->getParameter('due_duration_by_cycle');
        $this->min_shift_duration = $this->container->getParameter('min_shift_duration');
    }

    public function canBookShift()
    {
        // TODO
        return false;
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
     * Can book a shift
     *
     * @param Membership $member
     * @param \AppBundle\Entity\Beneficiary $beneficiary
     * @param \AppBundle\Entity\Shift $shift
     * @param string $current_cycle index of cycle
     *
     * @return Boolean
     */
    //todo get ride of this once we dont use membership anymore but the connected beneficiary
    public function canBook(Membership $member, Beneficiary $beneficiary = null, Shift $shift = null, $current_cycle = 'undefined')
    {
        $can = false;
        $beneficiaries = array();
        if ($beneficiary) {
            $beneficiaries[] = $beneficiary;
        } else {
            $beneficiaries = $member->getBeneficiaries();
        }
        foreach ($beneficiaries as $beneficiary) {
            if (is_int($current_cycle)) {
                if ($shift) {
                    $can = $can || $this->isShiftBookable($shift, $beneficiary);
                } else {
                    $can = $can || $beneficiary->canBook($this->min_shift_duration, $current_cycle);
                }
            } else {
                if ($shift) {
                    $can = $can || $this->isShiftBookable($shift, $beneficiary);
                } else {
                    $can = $can || $beneficiary->canBook($this->min_shift_duration);
                }
            }
        }
        return $can;
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

        if ($shift->getIsPast()) { // Do not book old
            return false;
        }
        if ($shift->getShifter() && !$shift->getIsDismissed()) { // Do not book already booked
            return false;
        }
        if ($shift->getLastShifter() && $beneficiary != $shift->getLastShifter()) { // Do not book pre-booked shift
            return false;
        }
        if (!$beneficiary) {
            return true;
        }
        if ($shift->getFormation() && !$beneficiary->getFormations()->contains($shift->getFormation())) { // Do not book shift i do not know how to handle (formation)
            return false;
        }

        $member = $beneficiary->getMembership();
        if ($member->isWithdrawn())
            return false;

        if ($member->getFirstShiftDate() > $shift->getStart())
            return false;

        $current_cycle = $this->getShiftCycleIndex($shift, $member);

        if ($member->getFrozen()) {
            if (!$current_cycle) //current cycle : cannot book when frozen
                return false;
            if ($current_cycle > 0 && !$member->getFrozenChange()) //next cycle : cannot book if frozen
                return false;
        }

        return $this->canBookDuration($beneficiary, $shift->getDuration(), $current_cycle);
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

}
