<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
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

    public function canBookShift()

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

    /**
     * Can book a shift
     *
     * @param \AppBundle\Entity\Beneficiary $beneficiary
     * @param \AppBundle\Entity\Shift $shift
     * @param $current_cycle index of cycle
     *
     * @return Boolean
     */
    //todo get ride of this once we dont use membership anymore but the connected beneficiary
    public function canBook(Membership $member, Beneficiary $beneficiary = null, Shift $shift = null, $current_cycle = 'undefined')
    {
        $can = false;
        $beneficiaries = array();
        if ($beneficiary){
            $beneficiaries[] = $beneficiary;
        }else{
            $beneficiaries = $member->getBeneficiaries();
        }
        foreach ($beneficiaries as $beneficiary){
            if (is_int($current_cycle)) {
                if ($shift) {
                    $can = $can || $shift->isBookable($beneficiary);
                }else{
                    $can = $can || $beneficiary->canBook(90, $current_cycle);
                }
            }else {
                if ($shift) {
                    $can = $can || $shift->isBookable($beneficiary);
                }else{
                    $can = $can || $beneficiary->canBook(90);
                }
            }
        }
        return $can;
    }

    /**
     * Get beneficiaries who can still book
     *
     * @param Membership $member
     * @param Shift|null $shift
     * @param int $current_cycle
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBeneficiariesWhoCanBook(Membership $member, Shift $shift = null, $current_cycle = 0)
    {
        return $member->getBeneficiaries()->filter(function ($beneficiary) use ($shift, $current_cycle) {
            return $this->canBook($beneficiary, $shift, $current_cycle);
        });
    }

}
