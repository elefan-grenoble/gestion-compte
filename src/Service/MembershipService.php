<?php

namespace App\Service;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Registration;
use App\Entity\Shift;
use App\Entity\ShiftBucket;
use Doctrine\Common\Collections\ArrayCollection;
use phpDocumentor\Reflection\Types\Array_;
use Symfony\Component\DependencyInjection\Container;

class MembershipService
{

    protected $em;
    protected $registration_duration;

    public function __construct($em, $registration_duration)
    {
        $this->em = $em;
        $this->registration_duration = $registration_duration;
    }

    /**
     * get remainder
     * @param Membership $membership
     * @param \DateTime $date
     * @return \DateInterval|false
     * @throws \Exception
     */
    public function getRemainder(Membership $membership,\DateTime $date = null)
    {
        if (!$date){
            $date = new \DateTime('now');
        }
        if (!$membership->getLastRegistration()){
            $expire = new \DateTime('-1 day');
            return date_diff($date,$expire);
        }
        $expire = clone $membership->getLastRegistration()->getDate();
        $expire = $expire->add(\DateInterval::createFromDateString($this->registration_duration));
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
        $expire = $expire->add(\DateInterval::createFromDateString($this->registration_duration));
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
}
