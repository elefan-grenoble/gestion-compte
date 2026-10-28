<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Beneficiary;
use App\Entity\Event;
use App\Entity\Proxy;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\MembershipBuilder;

/**
 * @internal
 */
class EventProxyTakeTest extends FunctionalTestCase
{
    /**
     * C-BUG-3 (SPEC.11, fixed in b0c7097): taking one proxy more than
     * MAX_EVENT_PROXY_PER_MEMBER (1 in .env.test) called getOwner() on the
     * array findBy() returns, an Error, instead of refusing politely.
     */
    public function testAMemberAlreadyHoldingTheMaximumOfProxiesIsRefusedPolitely(): void
    {
        static::createClient();
        $event = $this->anEvent();
        $holder = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
        $this->aProxyHeldBy($holder, $event);

        $client = static::createAuthenticatedClient($holder->getUser());
        $crawler = $client->request('GET', sprintf('/events/%d/proxy/take', $event->getId()));
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $client->submit($crawler->selectButton('J\'accepte une procuration')->form([
            'App_proxy[owner]' => $holder->getId(),
        ]));

        $this->assertTrue($client->getResponse()->isRedirect(sprintf('/events/%d/proxy/take', $event->getId())));
        $client->followRedirect();
        $this->assertStringContainsString('accepte déjà 1 procuration', $client->getResponse()->getContent());
        $this->assertSame(1, static::entityManager()->getRepository(Proxy::class)->count(['event' => $event]));
    }

    private function anEvent(): Event
    {
        $event = new Event();
        $event->setTitle('General assembly');
        $event->setDate(new \DateTime('+10 days'));
        $event->setDisplayedHome(false);

        return static::persist($event);
    }

    private function aProxyHeldBy(Beneficiary $owner, Event $event): Proxy
    {
        $proxy = new Proxy();
        $proxy->setEvent($event);
        $proxy->setOwner($owner);

        return static::persist($proxy);
    }
}
