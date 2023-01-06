<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use phpDocumentor\Reflection\Types\Array_;
use Symfony\Component\DependencyInjection\Container;
use \Datetime;

class MembershipService
{
    protected $em;
    protected $registration_duration;
    protected $registration_every_civil_year;
    protected $cycle_type;

    public function __construct(EntityManagerInterface $em, $registration_duration, $registration_every_civil_year, $cycle_type)
    {
        $this->em = $em;
        $this->registration_duration = $registration_duration;
        $this->registration_every_civil_year = $registration_every_civil_year;
        $this->cycle_type = $cycle_type;
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
            if ($membership->getLastRegistration()){
                $expire = $membership->getLastRegistration()->getDate();
            } else {
                $expire = new \DateTime('-1 year');
            }
            $expire = new \DateTime('last day of December '.$expire->format('Y'));
        } else {
            if ($membership->getLastRegistration()){
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
     * @param Membership $membership
     * @return bool
     * @throws \Exception
     */
    public function isUptodate(Membership $membership)
    {
        $expire = $this->getExpire($membership);
        $today = new \DateTime('now');
        $today->setTime(0,0);
        return ($expire > $today);
    }

    /**
     * Get start date of current cycle
     * @param Membership $membership
     * @param int $cycleOffset
     * @return DateTime|null
     */
    public function getStartOfCycle(Membership $membership, $cycleOffset = 0)
    {
        if ($this->cycle_type == "abcd") {
            $date = new DateTime('now');
            // 0 (for Monday) through 6 (for Sunday)
            $day = $date->format("N") - 1;
            // 0 (for week A) through 3 (for week D)
            $week = ($date->format("W") - 1) % 4;
            // Set date to last monday
            $date->modify('-' . $day . ' days');
            // Set date to monday of week A
            $date->modify('-'. (7 * $week) . ' days');
        } else {
            $firstDate = $membership->getFirstShiftDate();
            if ($firstDate) {
                $now = new DateTime('now');
                $date = clone($firstDate);
                if ($firstDate < $now) {
                    // Compute the number of elapsed cycles until today
                    $diff = $firstDate->diff($now)->format("%a");
                    $currentCycleCount = intval($diff / 28);
                    $date->modify("+" . (28 * $currentCycleCount) . " days");
                }
            } else {
                $date = new DateTime('now');
            }
        }
        // Set time to 0h:0m:0s
        $date->setTime(0, 0, 0);
        if ($cycleOffset != 0 ){
            // Set date cycleOffset
            // TODO should use cycle_duration instead of hardcoded 28
            $date->modify((($cycleOffset>0) ? "+" : "") . (28 * $cycleOffset) . ' days');
        }
        return $date;
    }

    /**
     * Get end date of current cycle
     * @param Membership $membership
     * @param int $cycleIndex
     * @return DateTime|null
     */
    public function getEndOfCycle(Membership $membership, $cycleOffset = 0)
    {
        $date = clone($this->getStartOfCycle($membership, $cycleOffset));
        $date->modify("+27 days");
        $date->setTime(23, 59, 59);
        return $date;
    }
}
