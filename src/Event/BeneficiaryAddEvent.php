<?php

namespace App\Event;

use App\Entity\Beneficiary;
use Symfony\Contracts\EventDispatcher\Event;

class BeneficiaryAddEvent extends Event
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
