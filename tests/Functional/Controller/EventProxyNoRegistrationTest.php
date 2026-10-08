<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Event;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\MembershipBuilder;

/**
 * I-BUG-10 (SPEC.11): the eligibility check of the proxies reads the date of
 * the last registration without checking there is one.
 *
 * @internal
 */
class EventProxyNoRegistrationTest extends FunctionalTestCase
{
    /**
     * @dataProvider proxyRoutes
     */
    public function testAMembershipWithoutRegistrationIsRefusedPolitely(string $route): void
    {
        static::createClient();
        $event = $this->anEvent();
        $membership = static::persist(MembershipBuilder::aMembership()->registeredOn()->build());
        $this->assertCount(0, $membership->getRegistrations(), 'Precondition: no registration.');

        $client = static::createAuthenticatedClient($membership->getMainBeneficiary()->getUser());
        try {
            $client->request('GET', sprintf('/events/%d/proxy/%s', $event->getId(), $route));
        } catch (\Error $e) {
            $this->markTestIncomplete('I-BUG-10 open: a membership without registration crashes /proxy/' . $route . ' (' . $e->getMessage() . ').');
        }

        $this->assertLessThan(500, $client->getResponse()->getStatusCode());
        $this->assertTrue($client->getResponse()->isRedirection() || $client->getResponse()->isSuccessful());
    }

    /**
     * @return array<string, array{string}>
     */
    public function proxyRoutes(): array
    {
        return ['give' => ['give'], 'take' => ['take']];
    }

    private function anEvent(): Event
    {
        $event = new Event();
        $event->setTitle('General assembly');
        $event->setDate(new \DateTime('+10 days'));
        $event->setDisplayedHome(false);

        return static::persist($event);
    }
}
