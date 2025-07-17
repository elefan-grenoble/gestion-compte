<?php

namespace AppBundle\Twig\Extension;

use AppBundle\Entity\Event;
use AppBundle\Entity\Proxy;
use AppBundle\Entity\User;
use AppBundle\Service\EventService;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class EventExtension extends AbstractExtension
{
    /**
     * @var EventService
     */
    private $eventService;
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(EventService $eventService, TokenStorageInterface $tokenStorage)
    {
        $this->eventService = $eventService;
        $this->tokenStorage = $tokenStorage;
    }

    public function getFilters()
    {
        return array(
            new TwigFilter('givenProxy', array($this, 'givenProxy')),
            new TwigFilter('receivedProxies', array($this, 'receivedProxies')),
        );
    }

    public function givenProxy(Event $event): ?Proxy
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if (!$user) {
            return null;
        }
        return $this->eventService->getGivenProxyOfMembershipForAnEvent($event, $user->getBeneficiary()->getMembership());
    }

    public function receivedProxies(Event $event): array
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if (!$user) {
            return null;
        }
        return $this->eventService->getReceivedProxiesOfBeneficiaryForAnEvent($event, $user->getBeneficiary());
    }
}
