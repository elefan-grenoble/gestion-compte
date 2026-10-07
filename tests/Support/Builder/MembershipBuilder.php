<?php

namespace App\Tests\Support\Builder;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Registration;

/**
 * Builds a Membership with its main beneficiary (and that beneficiary's
 * user), active by default: not withdrawn, not frozen, not flying, and
 * registered today.
 *
 *     $membership = MembershipBuilder::aMembership()->frozen()->build();
 *     $beneficiary = $membership->getMainBeneficiary();
 *
 * Persisting the membership persists the whole graph (cascades).
 */
final class MembershipBuilder
{
    /** @var int */
    private $memberNumber;

    /** @var bool */
    private $withdrawn = false;

    /** @var bool */
    private $frozen = false;

    /** @var bool */
    private $flying = false;

    /** @var null|\DateTimeInterface */
    private $firstShiftDate;

    /** @var Beneficiary|BeneficiaryBuilder */
    private $mainBeneficiary;

    /** @var array<Beneficiary|BeneficiaryBuilder> */
    private $otherBeneficiaries = [];

    /** @var \DateTime[] */
    private $registrationDates;

    private function __construct()
    {
        $this->memberNumber = UniqueSequence::next();
        $this->mainBeneficiary = BeneficiaryBuilder::aBeneficiary();
        $this->registrationDates = [new \DateTime('today')];
    }

    public static function aMembership(): self
    {
        return new self();
    }

    public function withMemberNumber(int $memberNumber): self
    {
        $this->memberNumber = $memberNumber;

        return $this;
    }

    public function withdrawn(bool $withdrawn = true): self
    {
        $this->withdrawn = $withdrawn;

        return $this;
    }

    public function frozen(bool $frozen = true): self
    {
        $this->frozen = $frozen;

        return $this;
    }

    public function flying(bool $flying = true): self
    {
        $this->flying = $flying;

        return $this;
    }

    public function withFirstShiftDate(\DateTimeInterface $date): self
    {
        $this->firstShiftDate = $date;

        return $this;
    }

    /**
     * @param Beneficiary|BeneficiaryBuilder $beneficiary
     */
    public function withMainBeneficiary($beneficiary): self
    {
        $this->mainBeneficiary = $beneficiary;

        return $this;
    }

    /**
     * @param Beneficiary|BeneficiaryBuilder $beneficiary
     */
    public function withBeneficiary($beneficiary): self
    {
        $this->otherBeneficiaries[] = $beneficiary;

        return $this;
    }

    /**
     * Replaces the default registration (today) with the given dates.
     */
    public function registeredOn(\DateTime ...$dates): self
    {
        $this->registrationDates = $dates;

        return $this;
    }

    public function build(): Membership
    {
        $membership = new Membership();
        $membership->setMemberNumber($this->memberNumber);
        $membership->setWithdrawn($this->withdrawn);
        $membership->setFrozen($this->frozen);
        $membership->setFrozenChange(false);
        $membership->setFlying($this->flying);
        if (null !== $this->firstShiftDate) {
            $membership->setFirstShiftDate($this->firstShiftDate);
        }

        $membership->setMainBeneficiary(self::resolve($this->mainBeneficiary));
        foreach ($this->otherBeneficiaries as $beneficiary) {
            $beneficiary = self::resolve($beneficiary);
            $membership->addBeneficiary($beneficiary);
            $beneficiary->setMembership($membership);
        }

        foreach ($this->registrationDates as $date) {
            $registration = new Registration();
            $registration->setDate($date);
            $registration->setAmount('10');
            $registration->setMode(Registration::TYPE_CASH);
            $registration->setMembership($membership);
            $membership->addRegistration($registration);
        }

        return $membership;
    }

    /**
     * @param Beneficiary|BeneficiaryBuilder $beneficiary
     */
    private static function resolve($beneficiary): Beneficiary
    {
        return $beneficiary instanceof BeneficiaryBuilder ? $beneficiary->build() : $beneficiary;
    }
}
