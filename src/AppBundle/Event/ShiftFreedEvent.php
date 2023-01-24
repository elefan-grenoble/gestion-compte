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
    private $createdBy;
    private $reason;

    public function __construct(Shift $shift, Beneficiary $beneficiary, User $createdBy, $reason = null)
    {
        $this->shift = $shift;
        $this->beneficiary = $beneficiary;
        $this->createdBy = $createdBy;
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
     * @return User
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * @return
     */
    public function getReason()
    {
        return $this->reason;
    }
}
