<?php

namespace App\Event;

use App\Entity\Membership;
use Symfony\Component\EventDispatcher\Event;

class MemberCycleStartEvent extends Event
{
    const NAME = 'member.cycle.start';

    private $membership;
    private $date;

    public function __construct(Membership $user, \DateTime $date)
    {
        $this->membership = $user;
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
