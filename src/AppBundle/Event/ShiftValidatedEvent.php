<?php

namespace AppBundle\Event;

use AppBundle\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftValidatedEvent extends Event
{
    const NAME = 'shift.validated';

    private $shift;
    private $source;

    public function __construct(Shift $shift, $source = null)
    {
        $this->shift = $shift;
        $this->source = $source;
    }

    public function getShift()
    {
        return $this->shift;
    }

    public function getSource()
    {
        return $this->source;
    }
}
