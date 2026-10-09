<?php

namespace App\Tests\Unit\Twig;

use App\Entity\Event;
use App\Service\EventService;
use App\Twig\Extension\EventExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @internal
 */
class EventExtensionTest extends TestCase
{
    /**
     * Without a logged-in user there is no received proxy (I-BUG-3: the
     * filter returned null from a method declared `: array`).
     */
    public function testNoUserHasReceivedNoProxy(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);
        $eventService = $this->createMock(EventService::class);
        $eventService->expects($this->never())->method('getReceivedProxiesOfBeneficiaryForAnEvent');

        $this->assertSame([], (new EventExtension($eventService, $tokenStorage))->receivedProxies(new Event()));
    }
}
