<?php

namespace AppBundle\Event;

use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use Symfony\Component\EventDispatcher\Event;

class MemberCycleHalfEvent extends Event
{
    const NAME = 'member.cycle.half';

    private $user;
    private $date;

    public function __construct(User $user, \DateTime $date)
    {
        $this->user = $user;
        $this->date = $date;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

}
