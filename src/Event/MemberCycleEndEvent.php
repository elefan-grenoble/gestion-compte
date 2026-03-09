<?php

namespace App\Event;

use App\Entity\Membership;

class MemberCycleEndEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'member.cycle.end';

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
