<?php

namespace App\Event;

use App\Entity\Shift;
use Symfony\Contracts\EventDispatcher\Event;

class ShiftBookedEvent extends Event
{
    public const NAME = 'shift.booked';

    private $shift;
    private $fromAdmin;

    public function __construct(Shift $shift, bool $fromAdmin)
    {
        $this->shift = $shift;
    }

    public function getShift()
    {
        return $this->shift;
    }

    public function isFromAdmin()
    {
        return $this->fromAdmin;
    }
}
