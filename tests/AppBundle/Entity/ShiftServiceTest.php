<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use AppBundle\Service\ShiftService;
use AppBundle\Twig\Extension\AppExtension;
use PHPUnit\Framework\TestCase;

class ShiftServiceTest extends TestCase
{

    protected $beneficiary;

    protected $member;

    /**
     * @var ShiftService
     */
    protected $shiftService;

    public function setUp()
    {
        $this->shiftService = new ShiftService(180, 90);
    }

    public function test_canBookNoFirstShift()
    {
        $member = new Membership();
        $beneficiary = new Beneficiary();
        $member->setMainBeneficiary($beneficiary);

        $this->assertEquals(true, $this->shiftService->canBookOnCycle($beneficiary, 0));
    }


}
