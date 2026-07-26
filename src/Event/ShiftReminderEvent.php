<?php

namespace App\Event;

use App\Entity\Shift;
use Symfony\Contracts\EventDispatcher\Event;

class ShiftReminderEvent extends Event
{
    public const NAME = 'shift.reminder';

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
