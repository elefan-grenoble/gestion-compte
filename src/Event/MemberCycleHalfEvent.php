<?php

namespace App\Event;

use App\Entity\Membership;
use Symfony\Component\EventDispatcher\Event;

class MemberCycleHalfEvent extends Event
{
    const NAME = 'member.cycle.half';

    private $membership;
    private $date;

    public function __construct(Membership $membership, \DateTime $date)
    {
        $this->membership = $membership;
        $this->date = $date;
    }

    /**
     * @return Membership
     */
    public function getMembership()
    {
        return $this->membership;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

}
