<?php

namespace AppBundle\Event;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\PeriodPosition;
use Symfony\Component\EventDispatcher\Event;

class PeriodPositionFreedEvent extends Event
{
    const NAME = 'period_position.freed';

    private $period_position;
    private $beneficiary;

    public function __construct(PeriodPosition $period_position, Beneficiary $beneficiary)
    {
        $this->period_position = $period_position;
        $this->beneficiary = $beneficiary;
    }

    /**
     * @return PeriodPosition
     */
    public function getPeriodPosition()
    {
        return $this->period_position;
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
}
