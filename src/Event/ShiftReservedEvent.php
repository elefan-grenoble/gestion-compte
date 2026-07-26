<?php

namespace App\Event;

use App\Entity\Shift;

class ShiftReservedEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'shift.reserved';

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
