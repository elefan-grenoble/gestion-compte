<?php

namespace App\Event;

use App\Entity\AnonymousBeneficiary;
use Symfony\Component\EventDispatcher\Event;

class AnonymousBeneficiaryCreatedEvent extends Event
{
    const NAME = 'anonymous_beneficiary.created';

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
