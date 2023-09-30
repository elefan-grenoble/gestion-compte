<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\MembershipShiftExemption;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use phpDocumentor\Reflection\Types\Array_;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \Datetime;

class MembershipService
{
    protected $em;
    protected $registration_duration;
    protected $registration_every_civil_year;
    protected $cycle_type;

    public function __construct(ContainerInterface $container, EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->registration_duration = $container->getParameter('registration_duration');
        $this->registration_every_civil_year = $container->getParameter('registration_every_civil_year');
        $this->cycle_type = $container->getParameter('cycle_type');
    }

     /**
     * Return autocomplete information
     */
    public function getAutocompleteMemberships()
    {
        $returnArray = array();
        $memberships = $this->em->getRepository('AppBundle:Membership')->findAllActive();

        foreach ($memberships as $membership) {
            $returnArray[$membership->getMemberNumberWithBeneficiaryListString()] = '';
        }

        return $returnArray;
    }

    /**
     * get remainder
     * @param Membership $membership
     * @return \DateInterval|false
     * @throws \Exception
     */
    public function getRemainder(Membership $membership)
    {
        return date_diff(new \DateTime('now'), $this->getExpire($membership));
    }

    /**
     * Check if registration is possible
     *
     * @param Membership $membership
     * @return boolean
     */
    public function canRegister(Membership $membership)
    {
        $expire = $this->getExpire($membership);
        $date = new \DateTime('+28 days');
        $date->setTime(0,0);
        return ($expire < $date);
    }

    /**
     * @param Membership $membership
     * @return \DateTime|null
     */
    public function getExpire($membership): ?\DateTime
    {
        if ($this->registration_every_civil_year) {
            if ($membership->getLastRegistration()) {
                $expire = $membership->getLastRegistration()->getDate();
            } else {
                $expire = new \DateTime('-1 year');
            }
            $expire = new \DateTime('last day of December ' . $expire->format('Y'));
        } else {
            if ($membership->getLastRegistration()) {
                $expire = clone $membership->getLastRegistration()->getDate();
                $expire = $expire->add(\DateInterval::createFromDateString($this->registration_duration));
                $expire->modify('-1 day');
            } else {
                $expire = new \DateTime('-1 day');
            }
        }
        $expire->setTime(23, 59, 59);
        return $expire;
    }

    /**
     * @param Membership $member
     * @return bool
     * @throws \Exception
     */
    public function isUptodate(Membership $member)
    {
        $expire = $this->getExpire($member);
        $today = new \DateTime('now');
        $today->setTime(0,0);
        return ($expire > $today);
    }

    /**
     * Get start date of current cycle
     * @param Membership $member
     * @param int $cycleOffset
     * @return DateTime|null
     */
    public function getStartOfCycle(Membership $member, $cycleOffset = 0)
    {
        // init
        $now = new DateTime('now');
        $date = clone($now);
        if ($this->cycle_type == "abcd") {
            // Set date to last monday
            // format "N": 0 (for Monday) through 6 (for Sunday))
            $day = $date->format("N") - 1;
            $date->modify('-' . $day . ' days');
            // Set date to monday of week A
            // format "W": 0 (for week A) through 3 (for week D))
            $week = ($date->format("W") - 1) % 4;
            $date->modify('-'. (7 * $week) . ' days');
        } else {
            // firstShiftDate = start of cycle
            $firstShiftDate = $member->getFirstShiftDate();
            if ($firstShiftDate) {
                $date = clone($firstShiftDate);
                // Compute the number of elapsed cycles until today
                $diff = $firstShiftDate->diff($now)->format("%r%a");
                $currentCycleCount = floor($diff / 28);
                $date->modify((($currentCycleCount > 0) ? "+" : "") . (28 * $currentCycleCount) . " days");
            }
        }
        // Set time to 0h:0m:0s
        $date->setTime(0, 0, 0);
        // offset
        if ($cycleOffset != 0) {
            // Set date cycleOffset
            // TODO should use cycle_duration instead of hardcoded 28
            $date->modify((($cycleOffset > 0) ? "+" : "") . (28 * $cycleOffset) . ' days');
        }
        return $date;
    }

    /**
     * Get end date of current cycle
     * @param Membership $member
     * @param int $cycleIndex
     * @return DateTime|null
     */
    public function getEndOfCycle(Membership $member, $cycleOffset = 0)
    {
        $date = clone($this->getStartOfCycle($member, $cycleOffset));
        $date->modify("+27 days");
        $date->setTime(23, 59, 59);
        return $date;
    }

    public function getCycleNumber(Membership $member, $date) {
        $cycle_end = $this->getEndOfCycle($member, -1);
        for ($cycle = -1; $cycle < 3; $cycle++) {
            if ($date <= $cycle_end) {
                return $cycle;
            }
            $cycle_end->modify("+28 days");
        }
        return null;
    }

    public function getCycleShiftMissedCount(Membership $member, $date) {
        $shift_cycle = $this->getCycleNumber($member, $date);
        $cycle_start = $this->getStartOfCycle($member, $shift_cycle);
        $cycle_end = $this->getEndOfCycle($member, $shift_cycle);
        return $this->em->getRepository('AppBundle:Shift')->getMemberShiftMissedCount($member, $cycle_start, $cycle_end);
    }

    public function getCycleShiftFreedCount(Membership $member, $date, $less_than_min_time_in_advance_days = null) {
        $shift_cycle = $this->getCycleNumber($member, $date);
        $cycle_start = $this->getStartOfCycle($member, $shift_cycle);
        $cycle_end = $this->getEndOfCycle($member, $shift_cycle);
        return $this->em->getRepository('AppBundle:ShiftFreeLog')->getMemberShiftFreedCount($member, $cycle_start, $cycle_end, $less_than_min_time_in_advance_days);
    }

    /**
     * Return true if the membership is in a "warning" status
     */
    public function hasWarningStatus(Membership $member): bool
    {
        return $member->getWithdrawn() ||
            $member->getFrozen() ||
            $member->isCurrentlyExemptedFromShifts() ||
            !$this->isUptodate($member);
    }

    public function getShiftFreeLogs(Membership $member)
    {
        return $this->em->getRepository('AppBundle:ShiftFreeLog')->getMemberShiftFreed($member);
    }

    public function memberHasShiftsOnExemptionPeriod(MembershipShiftExemption $membershipShiftExemption)
    {
        $shifts = $this->em->getRepository('AppBundle:Shift')->findInProgressAndUpcomingShiftsForMembership($membershipShiftExemption->getMembership());
        return $shifts->exists(function($key, $value) use ($membershipShiftExemption) {
            return $membershipShiftExemption->isCurrent($value->getStart());
        });
    }
}
