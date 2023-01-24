<?php

namespace AppBundle\Event;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftInvalidatedEvent extends Event
{
    const NAME = 'shift.invalidated';

    private $shift;
    private $member;

    public function __construct(Shift $shift, Membership $member)
    {
        $this->shift = $shift;
        $this->membership = $member;
    }

    /**
     * @return Membership
     */
    public function getMember()
    {
        return $this->member;
    }

    /**
     * @return Shift
     */
    public function getShift()
    {
        return $this->shift;
    }

}
