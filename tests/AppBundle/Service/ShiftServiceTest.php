<?php

namespace Tests\AppBundle\Service;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Shift;
use App\Entity\User;
use App\Repository\ShiftRepository;
use App\Service\ShiftService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class ShiftServiceTest extends TestCase
{
    /**
     * @var ShiftService
     */
    protected $shiftService;

    private $em;

    public function setUp(): void
    {
        $this->em = $this
            ->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->shiftService = new ShiftService($this->em, 180, 90, false, false);
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
    public function testIsShiftBookableWithEmptyShiftAndBeginner()
    {
        $this->assertFalse($this->doIsShiftBookableTest(true, true));
    }

    /**
     * Call to isShiftBookable for an empty shift and an user without rights (eg : a beginner)
     */
    public function testIsShiftBookableWithEmptyShiftAndNotABeginner()
    {
        $this->assertTrue($this->doIsShiftBookableTest(false, true));
    }

    /**
     * Call to isShiftBookable for a non empty shift and an user without rights to book an empty shift
     * It should return true because it's not an empty shift
     */
    public function testIsShiftBookableWithNotEmptyShiftAndBeginner()
    {
        $this->assertTrue($this->doIsShiftBookableTest(true, false));
    }

    /**
     * @param $userRoles array array of roles to be applied to the user
     * @param $emptyShift boolean
     * @return mixed
     */
    private function doIsShiftBookableTest($beginner, $emptyShift)
    {
        $beneficiary = new Beneficiary();
        $member = new Membership();
        $member->setMainBeneficiary($beneficiary);
        $user = new User();
        $beneficiary->setUser($user);

        $shift = $this
            ->getMockBuilder(Shift::class)
            ->getMock();
        $shift->expects($this->any())
            ->method('getIsPast')
            ->will($this->returnValue(false));
        $shiftService = $this
            ->getMockBuilder(ShiftService::class)
            ->setMethods(['isShiftEmpty', 'canBookDuration', 'isBeginner'])
            ->setConstructorArgs([$this->em, 180, 90, false, false])
            ->getMock()
        ;
        $shiftService->expects($this->any())
            ->method('isShiftEmpty')
            ->willReturn($emptyShift);
        $shiftService->expects($this->any())
            ->method('canBookDuration')
            ->willReturn(true);
        $shiftService->expects($this->any())
            ->method('isBeginner')
            ->willReturn($beginner);


        return $shiftService->isShiftBookable($shift, $beneficiary);
    }

    public function testIsBeginnerNewUsersBeginnerWithNotABeginner()
    {
        $this->assertFalse($this->doTestIsBeginner(false, true));
    }

    public function testIsBeginnerNewUsersBeginnerWithBeginner()
    {
        $this->assertTrue($this->doTestIsBeginner(true, true));
    }

    public function testIsBeginnerNewUsersNotBeginnerWithNotABeginner()
    {
        $this->assertFalse($this->doTestIsBeginner(false, false));
    }

    public function testIsBeginnerNewUsersNotBeginnerWithBeginner()
    {
        $this->assertFalse($this->doTestIsBeginner(true, false));
    }

    private function doTestIsBeginner($beginner, $newUserStartAsBeginner)
    {
        $beneficiary = new Beneficiary();

        $shiftService = $this
            ->getMockBuilder(ShiftService::class)
            ->setMethods(['hasPreviousValidShifts'])
            ->setConstructorArgs([$this->em, 180, 90, $newUserStartAsBeginner, false])
            ->getMock()
        ;

        $shiftService->expects($this->any())
            ->method('hasPreviousValidShifts')
            ->willReturn(!$beginner);

        return $shiftService->isBeginner($beneficiary);
    }

    public function testHasPreviousValidShiftsWithShift()
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('P10D'));
        $this->assertTrue($this->doTestHasPreviousValidShifts($date));
    }

    public function testHasPreviousValidShiftsWithShiftInTheFuture()
    {
        $date = new \DateTime();
        $date->add(new \DateInterval('P10D'));
        $this->assertFalse($this->doTestHasPreviousValidShifts($date));
    }

    public function testHasPreviousValidShiftsWithDismissedShift()
    {
        $date = new \DateTime();
        $date->add(new \DateInterval('P10D'));
        $this->assertFalse($this->doTestHasPreviousValidShifts($date, true));
    }

    public function testHasPreviousValidShiftsWithoutShift()
    {
        $this->assertFalse($this->doTestHasPreviousValidShifts(null));
    }

    public function doTestHasPreviousValidShifts($shiftDate, $dismissed = false)
    {
        $shifts = new ArrayCollection();
        if ($shiftDate)
        {
            $shift = new Shift();
            $shift->setIsDismissed($dismissed);
            $shift->setStart($shiftDate);
            $shifts->add($shift);
        }


        $beneficiary = $this->getMockBuilder(Beneficiary::class)->getMock();
        $beneficiary->expects($this->any())
            ->method('getShifts')
            ->willReturn($shifts);

        $shiftService = $this
            ->getMockBuilder(ShiftService::class)
            ->setMethodsExcept(['hasPreviousValidShifts'])
            ->disableOriginalConstructor()
            ->getMock()
        ;

        return $shiftService->hasPreviousValidShifts($beneficiary);
    }
}
