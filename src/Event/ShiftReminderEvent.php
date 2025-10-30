<?php

namespace App\Event;

use App\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftReminderEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    const NAME = 'shift.reminder';

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
