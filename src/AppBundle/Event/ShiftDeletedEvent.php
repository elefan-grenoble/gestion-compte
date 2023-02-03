<?php

namespace AppBundle\Event;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Shift;
use Symfony\Component\EventDispatcher\Event;

class ShiftDeletedEvent extends Event
{
    const NAME = 'shift.deleted';

    private $shift;
    private $beneficiary;

    public function __construct(Shift $shift, Beneficiary $beneficiary = null)
    {
        $this->shift = $shift;
        $this->beneficiary = $beneficiary;
    }

    public function getShift()
    {
        return $this->shift;
    }

    /**
     * @return Beneficiary|null
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }

    /**
     * @return Membership
     */
    public function getMember()
    {
        return $this->beneficiary->getMembership();
    }
}
