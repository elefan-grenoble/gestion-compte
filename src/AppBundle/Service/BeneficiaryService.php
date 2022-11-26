<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use Doctrine\Common\Collections\ArrayCollection;
use phpDocumentor\Reflection\Types\Array_;
use Symfony\Component\DependencyInjection\Container;

class BeneficiaryService
{

    protected $em;

    public function __construct($em)
    {
        $this->em = $em;
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

}
