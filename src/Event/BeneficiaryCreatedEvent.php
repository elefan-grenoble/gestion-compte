<?php

namespace App\Event;

use App\Entity\Beneficiary;

class BeneficiaryCreatedEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'beneficiary.created';

    private $beneficiary;

    public function __construct(Beneficiary $beneficiary)
    {
        $this->beneficiary = $beneficiary;
    }

    /**
     * @return Beneficiary
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }
}
