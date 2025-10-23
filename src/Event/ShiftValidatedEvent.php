<?php

namespace App\Event;

use App\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftValidatedEvent extends Event
{
    const NAME = 'shift.validated';

    private $shift;

    public function __construct(Shift $shift)
    {
        $this->shift = $shift;
    }

    public function getShift()
    {
        return $this->shift;
    }
}
