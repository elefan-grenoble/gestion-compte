<?php

namespace AppBundle\Event;

use AppBundle\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftReminderEvent extends Event
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
