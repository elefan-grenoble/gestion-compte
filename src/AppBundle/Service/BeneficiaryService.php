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
use Symfony\Component\DependencyInjection\ContainerInterface;

class BeneficiaryService
{
    private $container;
    protected $em;
    private $membershipService;

    public function __construct(ContainerInterface $container, EntityManagerInterface $em, MembershipService $membershipService)
    {
        $this->container = $container;
        $this->em = $em;
        $this->membershipService = $membershipService;
    }

    /**
     * Return autocomplete information
     */
    public function getAutocompleteBeneficiaries()
    {
        $beneficiaries = $this->em->getRepository('AppBundle:Beneficiary')->findAllActive();

        $returnArray = array();
        foreach ($beneficiaries as $beneficiary){
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

    public function getDisplayNameWithMemberNumberAndStatusIcon(Beneficiary $beneficiary): string
    {
        $label = '#' . $beneficiary->getMemberNumber();
        $label .= ' ' . $this->getStatusIcon($beneficiary);
        $label .=  ' ' . $beneficiary->getDisplayName();
        return $label;
    }

    /**
     * Return true if the beneficiary is in a "warning" status
     */
    public function hasWarningStatus(Beneficiary $beneficiary): bool
    {
        $hasWarningStatus = $this->membershipService->hasWarningStatus($beneficiary->getMembership());

        if ($this->container->getParameter('use_fly_and_fixed')) {
            $hasWarningStatus = $hasWarningStatus || $beneficiary->isFlying();
        }
        
        return $hasWarningStatus;
    }

    /**
     * Return a string with emoji between brackets depending on the
     * beneficiary status, if she/he is inactive (withdrawn), frozen or flying
     * or an empty string if none of those
     *
     * @param bool $includeLeadingSpace if true add a space at the beginning
     * @return string with ether emoji(s) for the beneficiary's status or empty
     */
    public function getStatusIcon(Beneficiary $beneficiary): string
    {
        $symbols = array();

        if ($beneficiary->getMembership()->getWithdrawn()) {
            $symbols[] = $this->container->getParameter('member_withdrawn_icon');
        }
        if ($beneficiary->getMembership()->getFrozen()) {
            $symbols[] = $this->container->getParameter('member_frozen_icon');
        }
        if ($beneficiary->isFlying()) {
            $symbols[] = $this->container->getParameter('beneficiary_flying_icon');;
        }
        if ($beneficiary->getMembership()->isCurrentlyExemptedFromShifts()) {
            $symbols[] = $this->container->getParameter('member_exempted_icon');
        }
        if (!$this->membershipService->isUptodate($beneficiary->getMembership())) {
            $symbols[] = $this->container->getParameter('member_registration_missing_icon');
        }

        if (count($symbols)) {
            return '[' . implode("/", $symbols) . ']';
        }
        return "";
    }
}
