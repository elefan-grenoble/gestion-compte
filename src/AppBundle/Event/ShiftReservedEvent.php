<?php

namespace AppBundle\Event;

use AppBundle\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftReservedEvent extends Event
{
    const NAME = 'shift.reserved';

    private $shift;
    private $formerShift;

    public function __construct(Shift $shift, Shift $formerShift)
    {
        $this->shift = $shift;
        $this->formerShift = $formerShift;
    }

    public function getShift()
    {
        return $this->shift;
    }

    public function getFormerShift()
    {
        return $this->formerShift;
    }
}
