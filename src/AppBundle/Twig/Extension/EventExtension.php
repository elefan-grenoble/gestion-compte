<?php

namespace AppBundle\Twig\Extension;

use AppBundle\Entity\Event;
use AppBundle\Entity\Proxy;
use AppBundle\Entity\User;
use AppBundle\Service\EventService;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EventExtension extends \Twig_Extension
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
            new \Twig_SimpleFilter('givenProxy', array($this, 'givenProxy')),
            new \Twig_SimpleFilter('receivedProxy', array($this, 'receivedProxy')),
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