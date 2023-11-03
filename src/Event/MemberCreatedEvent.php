<?php

namespace App\Event;

use App\Entity\Membership;
use Symfony\Component\EventDispatcher\Event;

class MemberCreatedEvent extends Event
{
    const NAME = 'member.created';

    private $membership;

    public function __construct(Membership $membership)
    {
        $this->membership = $membership;
    }

    /**
     * @return Membership
     */
    public function getMembership()
    {
        return $this->membership;
    }

}
