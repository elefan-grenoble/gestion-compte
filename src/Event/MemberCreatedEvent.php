<?php

namespace App\Event;

use App\Entity\Membership;

class MemberCreatedEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'member.created';

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
