<?php

namespace App\Event;

use App\Entity\Proxy;

class EventProxyCreatedEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'event.proxy.created';

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
