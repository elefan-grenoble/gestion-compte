<?php

namespace App\Event;

use App\Entity\Membership;
use App\Entity\Beneficiary;
use App\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftDeletedEvent extends Event
{
    const NAME = 'shift.deleted';

    private $shift;
    private $beneficiary;

    public function __construct(Shift $shift, Beneficiary $beneficiary = null)
    {
        $this->shift = $shift;
        $this->beneficiary = $beneficiary;
    }

    public function getShift()
    {
        return $this->shift;
    }

    /**
     * @return Beneficiary|null
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }

    /**
     * @return Membership|null
     */
    public function getMember()
    {
        if ($this->beneficiary) {
            return $this->beneficiary->getMembership();
        }
        return null;
    }
}
