<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Event;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\ShiftScenarios;

/**
 * I-BUG-10 (SPEC.11): the eligibility check of the proxies read the date of
 * the last registration without checking there is one. A membership without
 * registration is refused like an outdated one.
 *
 * @internal
 */
class EventProxyNoRegistrationTest extends FunctionalTestCase
{
    use ShiftScenarios;

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

        $client->request('GET', sprintf('/events/%d/proxy/%s', $event->getId(), $route));

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertStringContainsString('peuvent voter à cet événement', static::flashes($client)['error'][0] ?? '');
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
