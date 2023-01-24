<?php

namespace AppBundle\Event;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftInvalidatedEvent extends Event
{
    const NAME = 'shift.invalidated';

    private $shift;
    private $beneficiary;

    public function __construct(Shift $shift, Membership $beneficiary)
    {
        $this->shift = $shift;
        $this->beneficiary = $beneficiary;
    }

    /**
     * @return Shift
     */
    public function getShift()
    {
        return $this->shift;
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
