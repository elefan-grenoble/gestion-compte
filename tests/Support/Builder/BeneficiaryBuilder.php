<?php

namespace App\Tests\Support\Builder;

use App\Entity\Beneficiary;
use App\Entity\User;

/**
 * Builds a Beneficiary with its User account (the column is NOT NULL). It
 * belongs to no membership: attach it with MembershipBuilder.
 *
 *     $beneficiary = BeneficiaryBuilder::aBeneficiary()->flying()->build();
 */
final class BeneficiaryBuilder
{
    /** @var string */
    private $firstname = 'Jane';

    /** @var string */
    private $lastname = 'Doe';

    /** @var bool */
    private $flying = false;

    /** @var User|UserBuilder */
    private $user;

    private function __construct()
    {
        $this->user = UserBuilder::aUser();
    }

    public static function aBeneficiary(): self
    {
        return new self();
    }

    public function named(string $firstname, string $lastname): self
    {
        $this->firstname = $firstname;
        $this->lastname = $lastname;

        return $this;
    }

    public function flying(bool $flying = true): self
    {
        $this->flying = $flying;

        return $this;
    }

    /**
     * @param User|UserBuilder $user
     */
    public function withUser($user): self
    {
        $this->user = $user;

        return $this;
    }

    public function build(): Beneficiary
    {
        $user = $this->user instanceof UserBuilder ? $this->user->build() : $this->user;

        $beneficiary = new Beneficiary();
        $beneficiary->setFirstname($this->firstname);
        $beneficiary->setLastname($this->lastname);
        $beneficiary->setFlying($this->flying);
        $beneficiary->setUser($user);
        $user->setBeneficiary($beneficiary);

        return $beneficiary;
    }
}
