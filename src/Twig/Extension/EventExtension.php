<?php

namespace App\Twig\Extension;

use App\Entity\Event;
use App\Entity\Proxy;
use App\Entity\User;
use App\Service\EventService;
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
            new TwigFilter('receivedProxy', array($this, 'receivedProxy')),
        );
    }

    public function givenProxy(Event $event) : ?Proxy
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if (!$user) {
            return null;
        }
        return $this->eventService->getGivenProxyOfMembershipForAnEvent($event, $user->getBeneficiary()->getMembership());
    }

    public function receivedProxy(Event $event) : ?Proxy
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if (!$user) {
            return null;
        }
        return $this->eventService->getReceivedProxyOfBeneficiaryForAnEvent($event, $user->getBeneficiary());
    }
}