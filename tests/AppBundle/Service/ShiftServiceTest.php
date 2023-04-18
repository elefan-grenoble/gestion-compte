<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use AppBundle\Repository\ShiftRepository;
use AppBundle\Service\BeneficiaryService;
use AppBundle\Service\MembershipService;
use AppBundle\Service\ShiftService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use \Datetime;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ShiftServiceTest extends KernelTestCase
{
    /**
     * @var ShiftService
     */
    protected $shiftService;

    private $em;
    // Membership parameters
    private $registration_duration = '1 year';
    private $registration_every_civil_year = true;
    private $cycle_type = 'abcd';
    // Shift parameters
    private $due_duration_by_cycle = 180;
    private $min_shift_duration = 90;
    private $new_users_start_as_beginner = false;
    private $allow_extra_shifts = false;
    private $max_time_in_advance_to_book_extra_shifts = '3 days';
    private $forbid_shift_overlap_time = 30;

    public function setUp()
    {
        self::bootKernel();
        $this->em = $this
            ->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $membershipService = new MembershipService($this->em, $this->registration_duration, $this->registration_every_civil_year, $this->cycle_type);
        $beneficiaryService = new BeneficiaryService($this->em, $membershipService);
        $this->shiftService = new ShiftService($this->em, $beneficiaryService, $membershipService, $this->due_duration_by_cycle, $this->min_shift_duration, $this->new_users_start_as_beginner, $this->allow_extra_shifts, $this->max_time_in_advance_to_book_extra_shifts, $this->forbid_shift_overlap_time);
    }

    public function testShiftTimeByCycle()
    {
        $member = new Membership();
        $beneficiary = new Beneficiary();
        $beneficiary->setFlying(false);
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
        $beneficiary->setFlying(false);
        $member = new Membership();
        $member->setMainBeneficiary($beneficiary);
        $user = new User();
        $beneficiary->setUser($user);

        $shift = $this
            ->getMockBuilder(Shift::class)
            ->getMock();
        $shift->method('getStart')
             ->willReturn(new Datetime());
        $shift->expects($this->any())
            ->method('getIsPast')
            ->will($this->returnValue(false));
        $membershipService = new MembershipService($this->em, $this->registration_duration, $this->registration_every_civil_year, $this->cycle_type);
        $beneficiaryService = new BeneficiaryService($this->em, $membershipService);
        $shiftService = $this
            ->getMockBuilder(ShiftService::class)
            ->setMethods(['isShiftEmpty', 'canBookDuration', 'isBeginner'])
            ->setConstructorArgs([$this->em, $beneficiaryService, $membershipService, $this->due_duration_by_cycle, $this->min_shift_duration, $this->new_users_start_as_beginner, $this->allow_extra_shifts, $this->max_time_in_advance_to_book_extra_shifts, $this->forbid_shift_overlap_time])
            ->getMock();
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
        $beneficiary->setFlying(false);

        $membershipService = new MembershipService($this->em, $this->registration_duration, $this->registration_every_civil_year, $this->cycle_type);
        $beneficiaryService = new BeneficiaryService($this->em, $membershipService);
        $shiftService = $this
            ->getMockBuilder(ShiftService::class)
            ->setMethods(['hasPreviousValidShifts'])
            ->setConstructorArgs([$this->em, $beneficiaryService, $membershipService, $this->due_duration_by_cycle, $this->min_shift_duration, $newUserStartAsBeginner, $this->allow_extra_shifts, $this->max_time_in_advance_to_book_extra_shifts, $this->forbid_shift_overlap_time])
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
        $this->assertFalse($this->doTestHasPreviousValidShifts($date));
    }

    public function testHasPreviousValidShiftsWithoutShift()
    {
        $this->assertFalse($this->doTestHasPreviousValidShifts(null));
    }

    public function doTestHasPreviousValidShifts($shiftDate)
    {
        $shifts = new ArrayCollection();

        if ($shiftDate)
        {
            $shift = new Shift();
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
            ->getMock();

        return $shiftService->hasPreviousValidShifts($beneficiary);
    }
}
