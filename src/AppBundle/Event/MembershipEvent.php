<?php

namespace AppBundle\Event;

use AppBundle\Entity\Membership;
use Symfony\Component\EventDispatcher\Event;

class MembershipEvent extends Event
{
    const CREATED = 'membership.created';
    const BENEFICIARY_ADDED = 'membership.beneficiary_added';
    const BENEFICIARY_REMOVED = 'membership.beneficiary_removed';

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
