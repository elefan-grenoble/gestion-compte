<?php

namespace App\Event;

use App\Entity\Proxy;
use Symfony\Contracts\EventDispatcher\Event;

class EventProxyCreatedEvent extends Event
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
