<?php

namespace App\Event;

use App\Entity\Shift;

class ShiftValidatedEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'shift.validated';

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
