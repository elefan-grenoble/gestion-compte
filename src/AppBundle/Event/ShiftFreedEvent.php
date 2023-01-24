<?php

namespace AppBundle\Event;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use Symfony\Component\EventDispatcher\Event;

class ShiftFreedEvent extends Event
{
    const NAME = 'shift.freed';

    private $shift;
    private $member;
    private $createdBy;
    private $reason;

    public function __construct(Shift $shift, Membership $member, User $createdBy, $reason = null)
    {
        $this->shift = $shift;
        $this->member = $member;
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
     * @return Membership
     */
    public function getMember()
    {
        return $this->member;
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
