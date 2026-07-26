<?php

namespace App\Event;

use App\Entity\AnonymousBeneficiary;
use Symfony\Contracts\EventDispatcher\Event;

class AnonymousBeneficiaryRecallEvent extends Event
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
