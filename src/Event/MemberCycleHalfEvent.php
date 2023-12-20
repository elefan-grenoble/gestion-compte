<?php

namespace App\Event;

use App\Entity\Membership;
use Symfony\Component\EventDispatcher\Event;

class MemberCycleHalfEvent extends Event
{
    const NAME = 'member.cycle.half';

    private $membership;
    private $date;
    private $currentCycleShifts;

    public function __construct(Membership $user, \DateTime $date, $currentCycleShifts)
    {
        $this->membership = $user;
        $this->date = $date;
        $this->currentCycleShifts = $currentCycleShifts;
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

    /**
     * @return array
     */
    public function getCurrentCycleShifts()
    {
        return $this->currentCycleShifts;
    }

}
