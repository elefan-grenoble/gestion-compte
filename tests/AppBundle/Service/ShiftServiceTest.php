<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Service\ShiftService;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class ShiftServiceTest extends TestCase
{
    /**
     * @var ShiftService
     */
    protected $shiftService;

    public function setUp()
    {
        $em = $this
            ->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->shiftService = new ShiftService($em, 180, 90);
    }

    public function testShiftTimeByCycle()
    {
        $member = new Membership();
        $beneficiary = new Beneficiary();
        $member->setMainBeneficiary($beneficiary);

        $this->assertEquals(true, $this->shiftService->canBookOnCycle($beneficiary, 0));
    }
}
