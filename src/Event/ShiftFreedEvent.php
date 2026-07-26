<?php

namespace App\Event;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Shift;

class ShiftFreedEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'shift.freed';

    private $shift;
    private $beneficiary;
    private $fixe;
    private $reason;

    public function __construct(Shift $shift, Beneficiary $beneficiary, $fixe = false, $reason = null)
    {
        $this->shift = $shift;
        $this->beneficiary = $beneficiary;
        $this->fixe = $fixe;
        $this->reason = $reason;
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

    /**
     * @return bool
     */
    public function getFixe()
    {
        return $this->fixe;
    }

    /**
     * @return null|string
     */
    public function getReason()
    {
        return $this->reason;
    }
}
