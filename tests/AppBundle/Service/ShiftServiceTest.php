<?php

namespace AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Service\ShiftService;
use PHPUnit\Framework\TestCase;

class ShiftServiceTest extends TestCase
{

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

    }

    public function testShiftTimeByCycle()
    {
        $member = new Membership();
        $beneficiary = new Beneficiary();
        $member->setMainBeneficiary($beneficiary);

        $this->assertEquals(true, $this->shiftService->canBookOnCycle($beneficiary, 0));
    }
}
