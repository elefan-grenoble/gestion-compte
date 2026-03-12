<?php

namespace App\Event;

use App\Entity\Beneficiary;

class BeneficiaryAddEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'beneficiary.add';

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
