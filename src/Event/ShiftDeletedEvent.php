<?php

namespace App\Event;

use App\Entity\Membership;
use App\Entity\Beneficiary;
use App\Entity\Shift;

class ShiftDeletedEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'shift.deleted';

    private $shift;
    private $beneficiary;

    public function __construct(Shift $shift, ?Beneficiary $beneficiary = null)
    {
        $this->shift = $shift;
        $this->beneficiary = $beneficiary;
    }

    public function getShift()
    {
        return $this->shift;
    }

    /**
     * @return null|Beneficiary
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }

    /**
     * @return null|Membership
     */
    public function getMember()
    {
        if ($this->beneficiary) {
            return $this->beneficiary->getMembership();
        }

        return null;
    }
}
