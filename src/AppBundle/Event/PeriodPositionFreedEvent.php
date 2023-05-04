<?php

namespace AppBundle\Event;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\PeriodPosition;
use Symfony\Component\EventDispatcher\Event;

class PeriodPositionFreedEvent extends Event
{
    const NAME = 'period_position.freed';

    private $periodPosition;
    private $beneficiary;

    public function __construct(PeriodPosition $periodPosition, Beneficiary $beneficiary, $bookedTime = null)
    {
        $this->periodPosition = $periodPosition;
        $this->beneficiary = $beneficiary;
        $this->bookedTime = $bookedTime;
    }

    /**
     * @return PeriodPosition
     */
    public function getPeriodPosition()
    {
        return $this->periodPosition;
    }

    /**
     * @return Beneficiary
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }

    /**
     * @return Membership
     */
    public function getMember()
    {
        return $this->beneficiary->getMembership();
    }

    /**
     * @return \DateTime|null
     */
    public function getBookedTime()
    {
        return $this->bookedTime;
    }
}
