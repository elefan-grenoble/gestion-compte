<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Service\MembershipService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use phpDocumentor\Reflection\Types\Array_;
use Symfony\Component\DependencyInjection\Container;

class BeneficiaryService
{
    protected $em;
    private $membershipService;

    public function __construct(EntityManagerInterface $em, MembershipService $membershipService)
    {
        $this->em = $em;
        $this->membershipService = $membershipService;
    }

    /**
     * Return autocomplete information
     */
    public function getAutocompleteBeneficiaries()
    {
        $returnArray = array();
        $beneficiaries = $this->em->getRepository('AppBundle:Beneficiary')->findAllActive();

        foreach ($beneficiaries as $beneficiary) {
            $returnArray[$beneficiary->getDisplayNameWithMemberNumber()] = '';
        }

        return $returnArray;
    }

    public function getTimeCount(Beneficiary $beneficiary, $cycle = 0)
    {
        $member = $beneficiary->getMembership();
        $cycle_start = $this->membershipService->getStartOfCycle($member, $cycle);
        $cycle_end = $this->membershipService->getEndOfCycle($member, $cycle);

        $shifts = $this->em->getRepository('AppBundle:Shift')->findShiftsForBeneficiary($beneficiary, $cycle_start, $cycle_end);

        $counter = 0;
        foreach ($shifts as $shift) {
            $counter += $shift->getDuration();
        }
        return $counter;
    }
}
