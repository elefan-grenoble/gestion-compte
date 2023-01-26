<?php

namespace AppBundle\Event;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use Symfony\Component\EventDispatcher\Event;

class ShiftFreedEvent extends Event
{
    const NAME = 'shift.freed';

    private $shift;
    private $beneficiary;
    private $source;
    private $reason;

    public function __construct(Shift $shift, Beneficiary $beneficiary, $source = null, $reason = null)
    {
        $this->shift = $shift;
        $this->beneficiary = $beneficiary;
        $this->source = $source;
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
     * @return string|null
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @return string|null
     */
    public function getReason()
    {
        return $this->reason;
    }
}
