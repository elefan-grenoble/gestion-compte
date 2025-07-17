<?php

namespace AppBundle\Event;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Commission;
use Symfony\Component\EventDispatcher\Event;

class CommissionJoinOrLeaveEvent extends Event
{
    const JOIN_EVENT_NAME = 'commission.join';
    const LEAVE_EVENT_NAME = 'commission.leave';

    private $beneficiary;
    private $commission;

    public function __construct(Beneficiary $beneficiary, Commission $commission)
    {
        $this->beneficiary = $beneficiary;
        $this->commission = $commission;
    }

    /**
     * @return Beneficiary
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }

    /**
     * @return Commission
     */
    public function getCommission()
    {
        return $this->commission;
    }
}
