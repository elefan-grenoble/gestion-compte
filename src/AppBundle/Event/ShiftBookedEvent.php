<?php

namespace AppBundle\Event;

use AppBundle\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftBookedEvent extends Event
{
    const NAME = 'shift.booked';

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
