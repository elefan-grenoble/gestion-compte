<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
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

    /**
     * Call to isShiftBookable for an empty shift and an user with correct rights
     */
    public function testIsShiftBookableWithEmptyShiftAndRights()
    {
        $roles = ['ROLE_USER', 'ROLE_SHIFT_FIRST_BOOKER'];
        $this->assertTrue($this->doIsShiftBookableTest($roles, true));
    }

    /**
     * Call to isShiftBookable for an empty shift and an user without rights (eg : a beginner)
     */
    public function testIsShiftBookableWithEmptyShiftAndNoRights()
    {
        $roles = ['ROLE_USER'];
        $this->assertFalse($this->doIsShiftBookableTest($roles, true));
    }

    /**
     * Call to isShiftBookable for a non empty shift and an user without rights to book an empty shift
     * It should return true because it's not an empty shift
     */
    public function testIsShiftBookableWithNotEmptyShiftAndNoRights()
    {
        $roles = ['ROLE_USER'];
        $this->assertTrue($this->doIsShiftBookableTest($roles, false));
    }

    /**
     * @param $userRoles array array of roles to be applied to the user
     * @param $emptyShift boolean
     * @return mixed
     */
    private function doIsShiftBookableTest($userRoles, $emptyShift)
    {
        $beneficiary = new Beneficiary();
        $member = new Membership();
        $member->setMainBeneficiary($beneficiary);

        $user = new User();
        $user->setRoles($userRoles);
        $beneficiary->setUser($user);

        $shift = $this
            ->getMockBuilder(Shift::class)
            ->getMock();
        $shift->expects($this->any())
            ->method('getIsPast')
            ->will($this->returnValue(false));

        $shiftService = $this
            ->getMockBuilder(ShiftService::class)
            ->setMethods(['isShiftEmpty', 'canBookDuration'])
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $shiftService->expects($this->any())
            ->method('isShiftEmpty')
            ->willReturn($emptyShift);
        $shiftService->expects($this->any())
            ->method('canBookDuration')
            ->willReturn(true);


        return $shiftService->isShiftBookable($shift, $beneficiary);
    }
}
