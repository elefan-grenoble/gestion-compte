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

class MembershipService
{

    protected $em;
    protected $registration_duration;

    public function __construct($em, $registration_duration, $registration_every_civil_year)
    {
        $this->em = $em;
        $this->registration_duration = $registration_duration;
        $this->registration_every_civil_year = $registration_every_civil_year;
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
}
