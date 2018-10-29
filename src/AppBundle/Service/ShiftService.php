<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use Symfony\Component\DependencyInjection\Container;

class ShiftService
{

    protected $container;
    protected $due_duration_by_cycle;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->due_duration_by_cycle = $this->container->getParameter('due_duration_by_cycle');
    }

    public function canBook(Beneficiary $beneficiary, $duration = 90, $cycle = 0)
    {
        $member = $beneficiary->getMembership();
        $beneficiary_counter = $beneficiary->getTimeCount($cycle);

        //check if beneficiary booked time is ok
        //if timecount < due_duration_by_cycle : some shift to catchup, can book more than what's due
        if ($member->getTimeCount($member->endOfCycle($cycle)) >= $this->due_duration_by_cycle && $beneficiary_counter >= $this->due_duration_by_cycle) { //Beneficiary is already ok
            return false;
        }

        //time count at start of cycle (before decrease)
        $timeCounter = $member->getTimeCount($member->startOfCycle($cycle));
        //time count at start of cycle  (after decrease)
        if ($timeCounter > $this->due_duration_by_cycle) {
            $timeCounter = 0;
        } else {
            $timeCounter -= $this->due_duration_by_cycle;
        }
        // duration of shift + what beneficiary already booked for cycle + timecount (may be < 0) minus due should be <= what can membership book for this cycle
        return ($duration + $beneficiary_counter + $timeCounter <= ($cycle + 1) * $this->due_duration_by_cycle);
    }

}
