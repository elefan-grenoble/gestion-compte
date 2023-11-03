<?php

namespace App\Event;

use App\Entity\Membership;
use App\Entity\Beneficiary;
use App\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftInvalidatedEvent extends Event
{
    const NAME = 'shift.invalidated';

    private $shift;
    private $beneficiary;

    public function __construct(Shift $shift, Beneficiary $beneficiary)
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
