<?php

namespace AppBundle\Event;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftDismissedEvent extends Event
{
    const NAME = 'shift.dismissed';

    private $shift;
    private $beneficiary;
    private $reason;

    public function __construct(Shift $shift, Beneficiary $beneficiary, string $reason)
    {
        $this->shift = $shift;
        $this->beneficiary = $beneficiary;
        $this->reason = $reason;
    }

    /**
     * @return Beneficiary
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }

    /**
     * @return Shift
     */
    public function getShift()
    {
        return $this->shift;
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }
}
