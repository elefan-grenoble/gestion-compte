<?php

namespace AppBundle\Event;

use AppBundle\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftDeletedEvent extends Event
{
    const NAME = 'shift.deleted';

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
