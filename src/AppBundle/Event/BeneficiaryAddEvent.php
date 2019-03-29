<?php

namespace AppBundle\Event;

use AppBundle\Entity\Beneficiary;
use Symfony\Component\EventDispatcher\Event;

class BeneficiaryAddEvent extends Event
{
    const NAME = 'beneficiary.add';

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
