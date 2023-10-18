<?php

namespace AppBundle\Event;

use AppBundle\Entity\Proxy;
use Symfony\Component\EventDispatcher\Event;

class EventProxyCreatedEvent extends Event
{
    const NAME = 'event.proxy.created';

    private $proxy;

    public function __construct(Proxy $proxy)
    {
        $this->proxy = $proxy;
    }

    public function getProxy()
    {
        return $this->proxy;
    }
}
