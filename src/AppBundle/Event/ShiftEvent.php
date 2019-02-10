<?php

namespace AppBundle\Event;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftEvent extends Event
{
    const EVENT_DELETED = 'shift.deleted';
    const EVENT_BOOKED = 'shift.booked';
    const EVENT_DISMISSED = 'shift.dismissed';
    const EVENT_FREED = 'shift.freed';

    /**
     * @var Shift
     */
    private $shift;

    /**
     * @var Beneficiary|null
     */
    private $shifter;

    public function __construct(Shift $shift, ?Beneficiary $shifter)
    {
        $this->shift = $shift;
        $this->shifter = $shifter;
    }

    public function getShift() : Shift
    {
        return $this->shift;
    }

    /**
     * @return Beneficiary|null
     */
    public function getShifter(): ?Beneficiary
    {
        return $this->shifter;
    }
}