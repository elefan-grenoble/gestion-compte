<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Beneficiary;
use App\Entity\Event;
use App\Entity\Membership;
use App\Entity\Proxy;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class EventTest extends TestCase
{
    /**
     * A proxy waiting for its owner has none (I-BUG-2: the filter
     * dereferenced the missing owner).
     */
    public function testProxiesByOwnerSkipTheProxiesWaitingForAnOwner(): void
    {
        $owner = new Beneficiary();
        $membership = new Membership();
        $membership->setMainBeneficiary($owner);
        $owner->setMembership($membership);

        $event = new Event();
        $held = (new Proxy())->setOwner($owner);
        $event->addProxy($held);
        $event->addProxy((new Proxy())->setGiver(new Membership()));

        $this->assertSame([$held], array_values($event->getProxiesByOwnerMembershipMainBeneficiary($owner)->toArray()));
    }
}
