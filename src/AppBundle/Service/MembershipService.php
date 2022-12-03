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
use \Datetime;

class MembershipService
{

    protected $em;
    protected $registration_duration;
    protected $registration_every_civil_year;
    protected $cycle_type;

    public function __construct($em, $registration_duration, $registration_every_civil_year, $cycle_type)
    {
        $this->em = $em;
        $this->registration_duration = $registration_duration;
        $this->registration_every_civil_year = $registration_every_civil_year;
        $this->cycle_type = $cycle_type;
    }

    /**
     * get remainder
     * @param Membership $membership
     * @param \DateTime $date
     * @return \DateInterval|false
     * @throws \Exception
     */
    public function getRemainder(Membership $membership, \DateTime $date = null)
    {
        if (!$date){
            $date = new \DateTime('now');
        }
        if (!$membership->getLastRegistration()){
            $expire = new \DateTime('-1 day');
        } else {
            $expire = $this->getExpire($membership);
        }
        return date_diff($date,$expire);
    }

    /**
     * Check if registration is possible
     *
     * @param Membership $membership
     * @param \DateTime $date
     * @return boolean
     * @throws \Exception
     */
    public function canRegister(Membership $membership,\DateTime $date = null)
    {
        $remainder = $this->getRemainder($membership,$date);
        if ( ! $remainder->invert ){ //still some days
            $min_delay_to_anticipate =  \DateInterval::createFromDateString('28 days');
            $now = new \DateTimeImmutable();
            $away = $now->add($min_delay_to_anticipate);
            $now = new \DateTimeImmutable();
            $expire = $now->add($remainder);
            return ($expire < $away);
        }
        else {
            return true;
        }
    }

    /**
     * @param Membership $membership
     * @return \DateTime|null
     */
    public function getExpire($membership): ?\DateTime
    {
        $expire = clone $membership->getLastRegistration()->getDate();
        if ($this->registration_every_civil_year) {
            $expire = new \DateTime('last day of December '.$expire->format('Y'));
        } else {
            $expire = $expire->add(\DateInterval::createFromDateString($this->registration_duration));
            $expire->modify('-1 day');
        }
        return $expire;
    }

    /**
     * @param Membership $membership
     * @return bool
     * @throws \Exception
     */
    public function isUptodate(Membership $membership)
    {
        return ($this->getRemainder($membership)->format("%R%a") >= 0);
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
            $firstDate = $this->getFirstShiftDate();
            if ($firstDate) {
                $now = new DateTime('now');
                $date = clone($firstDate);
                if ($firstDate < $now) {
                    // Compute the number of elapsed cycles until today
                    $diff = $firstDate->diff($now)->format("%a");
                    $currentCycleCount = intval($diff / 28);
                    $date->modify("+" . (28 * $currentCycleCount) . " days");
                }
            }else{
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
