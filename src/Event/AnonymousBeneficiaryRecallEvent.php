<?php

namespace App\Event;

use App\Entity\AnonymousBeneficiary;

class AnonymousBeneficiaryRecallEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'anonymous_beneficiary.recall';

    private $anonymous_beneficiary;

    public function __construct(AnonymousBeneficiary $anonymous_beneficiary)
    {
        $this->anonymous_beneficiary = $anonymous_beneficiary;
    }

    /**
     * @return AnonymousBeneficiary
     */
    public function getAnonymousBeneficiary()
    {
        return $this->anonymous_beneficiary;
    }
}
