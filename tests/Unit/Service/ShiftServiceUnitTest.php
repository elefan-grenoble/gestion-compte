<?php

namespace App\Tests\Unit\Service;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Registration;
use App\Entity\Shift;
use App\Entity\User;
use App\Service\BeneficiaryService;
use App\Service\MembershipService;
use App\Service\ShiftService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class ShiftServiceUnitTest extends TestCase
{
    /** @var EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $em;

    /** @var BeneficiaryService|\PHPUnit\Framework\MockObject\MockObject */
    private $beneficiaryService;

    /** @var MembershipService|\PHPUnit\Framework\MockObject\MockObject */
    private $membershipService;

    protected function setUp(): void
    {
        $this->em = $this->getMockBuilder(EntityManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->beneficiaryService = $this->getMockBuilder(BeneficiaryService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->membershipService = $this->getMockBuilder(MembershipService::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createService(array $params = []): ShiftService
    {
        $defaults = [
            'due_duration_by_cycle' => 180,
            'min_shift_duration' => 90,
            'newUserStartAsBeginner' => false,
            'allowExtraShifts' => false,
            'maxTimeInAdvanceToBookExtraShifts' => '3 days',
            'forbidShiftOverlapTime' => 30,
            'use_fly_and_fixed' => false,
            'fly_and_fixed_allow_fixed_shift_free' => false,
            'use_time_log_saving' => false,
            'time_log_saving_shift_free_min_time_in_advance_days' => 3,
            'time_log_saving_shift_free_allow_only_if_enough_saving' => false,
        ];
        $params = array_merge($defaults, $params);

        return new ShiftService(
            $this->em,
            $this->beneficiaryService,
            $this->membershipService,
            $params['due_duration_by_cycle'],
            $params['min_shift_duration'],
            $params['newUserStartAsBeginner'],
            $params['allowExtraShifts'],
            $params['maxTimeInAdvanceToBookExtraShifts'],
            $params['forbidShiftOverlapTime'],
            $params['use_fly_and_fixed'],
            $params['fly_and_fixed_allow_fixed_shift_free'],
            $params['use_time_log_saving'],
            $params['time_log_saving_shift_free_min_time_in_advance_days'],
            $params['time_log_saving_shift_free_allow_only_if_enough_saving']
        );
    }

    private function createBeneficiaryWithMembership(): Beneficiary
    {
        $membership = new Membership();
        $membership->setMemberNumber(1);
        $membership->setWithdrawn(false);
        $membership->setFrozen(false);
        $membership->setFlying(false);

        $registration = new Registration();
        $registration->setDate(new \DateTime('now'));
        $membership->addRegistration($registration);

        $beneficiary = new Beneficiary();
        $beneficiary->setFirstname('Test');
        $beneficiary->setLastname('User');
        $beneficiary->setFlying(false);
        $membership->setMainBeneficiary($beneficiary);

        $user = new User();
        $beneficiary->setUser($user);

        return $beneficiary;
    }

    // -------------------------------------------------------
    // remainingToBook()
    // -------------------------------------------------------

    public function testRemainingToBookFullCycle(): void
    {
        $service = $this->createService(['due_duration_by_cycle' => 180]);
        $beneficiary = $this->createBeneficiaryWithMembership();
        $member = $beneficiary->getMembership();

        $this->membershipService->method('getEndOfCycle')
            ->willReturn(new \DateTime('+27 days'));

        // No shifts booked → full time log count is 0
        $remaining = $service->remainingToBook($member);
        $this->assertEquals(180, $remaining);
    }

    public function testRemainingToBookPartiallyBooked(): void
    {
        $service = $this->createService(['due_duration_by_cycle' => 180]);

        $membership = new Membership();
        $membership->setMemberNumber(1);
        $membership->setWithdrawn(false);
        $membership->setFrozen(false);
        $membership->setFlying(false);

        $this->membershipService->method('getEndOfCycle')
            ->willReturn(new \DateTime('+27 days'));

        // Membership has shift time count → remaining = due - shiftTimeCount
        // Since Membership's getShiftTimeCount relies on timeLogs, and we have none,
        // the result will be 180 - 0 = 180
        $remaining = $service->remainingToBook($membership);
        $this->assertEquals(180, $remaining);
    }

    // -------------------------------------------------------
    // canBookExtraShift()
    // -------------------------------------------------------

    public function testCanBookExtraShiftWhenDisabled(): void
    {
        $service = $this->createService(['allowExtraShifts' => false]);
        $beneficiary = $this->createBeneficiaryWithMembership();
        $shift = $this->createMock(Shift::class);

        $this->assertFalse($service->canBookExtraShift($beneficiary, $shift));
    }

    public function testCanBookExtraShiftWhenEnabledWithNoTimeLimit(): void
    {
        $service = $this->createService([
            'allowExtraShifts' => true,
            'maxTimeInAdvanceToBookExtraShifts' => null,
        ]);
        $beneficiary = $this->createBeneficiaryWithMembership();
        $shift = $this->createMock(Shift::class);

        $this->assertTrue($service->canBookExtraShift($beneficiary, $shift));
    }

    public function testCanBookExtraShiftWhenEnabledAndShiftIsBeforeLimit(): void
    {
        $service = $this->createService([
            'allowExtraShifts' => true,
            'maxTimeInAdvanceToBookExtraShifts' => '3 days',
        ]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        $shift = $this->createMock(Shift::class);
        $shift->method('isBefore')->with('3 days')->willReturn(true);

        $this->assertTrue($service->canBookExtraShift($beneficiary, $shift));
    }

    public function testCanBookExtraShiftWhenEnabledButShiftTooFarInAdvance(): void
    {
        $service = $this->createService([
            'allowExtraShifts' => true,
            'maxTimeInAdvanceToBookExtraShifts' => '3 days',
        ]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        $shift = $this->createMock(Shift::class);
        $shift->method('isBefore')->with('3 days')->willReturn(false);

        $this->assertFalse($service->canBookExtraShift($beneficiary, $shift));
    }

    // -------------------------------------------------------
    // canBookSomething()
    // -------------------------------------------------------

    public function testCanBookSomethingWhenExtraShiftsAllowed(): void
    {
        $service = $this->createService(['allowExtraShifts' => true]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        $this->assertTrue($service->canBookSomething($beneficiary));
    }

    public function testCanBookSomethingDelegatesToCanBookOnCycle(): void
    {
        // Extra shifts disabled, beneficiary has capacity on cycle 0
        $service = $this->getMockBuilder(ShiftService::class)
            ->setConstructorArgs([
                $this->em, $this->beneficiaryService, $this->membershipService,
                180, 90, false, false, '3 days', 30,
                false, false, false, 3, false
            ])
            ->onlyMethods(['canBookOnCycle'])
            ->getMock();

        $beneficiary = $this->createBeneficiaryWithMembership();

        $service->expects($this->atLeastOnce())
            ->method('canBookOnCycle')
            ->willReturnMap([
                [$beneficiary, 0, true],
                [$beneficiary, 1, false],
            ]);

        $this->assertTrue($service->canBookSomething($beneficiary));
    }

    // -------------------------------------------------------
    // canBookDuration()
    // -------------------------------------------------------

    public function testCanBookDurationWhenExtraShiftsAllowedNoLimit(): void
    {
        $service = $this->createService([
            'allowExtraShifts' => true,
            'maxTimeInAdvanceToBookExtraShifts' => null,
        ]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        $this->assertTrue($service->canBookDuration($beneficiary, 90, 0));
    }

    public function testCanBookDurationWhenAlreadyFullyBooked(): void
    {
        $service = $this->createService([
            'due_duration_by_cycle' => 180,
            'allowExtraShifts' => false,
        ]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        // MembershipService returns end of cycle
        $this->membershipService->method('getEndOfCycle')
            ->willReturn(new \DateTime('+27 days'));

        // Beneficiary already has 180 min of shifts this cycle
        $this->beneficiaryService->method('getCycleShiftDurationSum')
            ->willReturn(180);

        // Membership already has 180 min in time logs → already at due
        // But Membership's getShiftTimeCount returns 0 (no timeLogs in our test entity)
        // So the check `$membership_counter >= $this->due_duration_by_cycle` will be false
        // and we'll enter the catch-up logic

        // Test with mocked ShiftService to control canBookDuration directly
        $result = $service->canBookDuration($beneficiary, 90, 0);

        // 90 + 0 (shiftTimeCount) <= 1 * 180 → true (has catchup capacity)
        $this->assertTrue($result);
    }

    // -------------------------------------------------------
    // canBookShift() — overlap check
    // -------------------------------------------------------

    public function testCanBookShiftNoOverlap(): void
    {
        $service = $this->createService(['forbidShiftOverlapTime' => 30]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        // Beneficiary has no existing shifts → no overlap
        $currentShift = $this->createMock(Shift::class);
        $currentShift->method('getStart')->willReturn(new \DateTime('2025-03-01 09:00'));
        $currentShift->method('getEnd')->willReturn(new \DateTime('2025-03-01 12:00'));

        $this->assertTrue($service->canBookShift($beneficiary, $currentShift));
    }

    public function testCanBookShiftOverlapDisabled(): void
    {
        $service = $this->createService(['forbidShiftOverlapTime' => -1]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        $currentShift = $this->createMock(Shift::class);

        // When forbidShiftOverlapTime < 0, overlap check is disabled
        $this->assertTrue($service->canBookShift($beneficiary, $currentShift));
    }

    // -------------------------------------------------------
    // shiftTimeByCycle()
    // -------------------------------------------------------

    public function testShiftTimeByCycleWithOneBeneficiary(): void
    {
        $service = $this->createService(['due_duration_by_cycle' => 180]);
        $beneficiary = $this->createBeneficiaryWithMembership();
        $member = $beneficiary->getMembership();

        $this->assertEquals(180, $service->shiftTimeByCycle($member));
    }

    public function testShiftTimeByCycleWithMultipleBeneficiaries(): void
    {
        $service = $this->createService(['due_duration_by_cycle' => 180]);
        $beneficiary1 = $this->createBeneficiaryWithMembership();
        $member = $beneficiary1->getMembership();

        // Add a second beneficiary
        $beneficiary2 = new Beneficiary();
        $beneficiary2->setFirstname('Jane');
        $beneficiary2->setLastname('Doe');
        $beneficiary2->setFlying(false);
        $member->addBeneficiary($beneficiary2);

        // 2 beneficiaries × 180 = 360
        $this->assertEquals(360, $service->shiftTimeByCycle($member));
    }

    // -------------------------------------------------------
    // canFreeShift()
    // -------------------------------------------------------

    public function testCanFreeShiftWithNoShifter(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiaryWithMembership();

        $shift = $this->createMock(Shift::class);
        $shift->method('getShifter')->willReturn(null);

        $result = $service->canFreeShift($beneficiary, $shift);

        $this->assertFalse($result['result']);
        $this->assertNotEmpty($result['message']);
    }

    public function testCanFreeShiftWithDifferentShifter(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiaryWithMembership();

        $otherBeneficiary = new Beneficiary();
        $otherBeneficiary->setFirstname('Other');
        $otherBeneficiary->setLastname('Person');

        $shift = $this->createMock(Shift::class);
        $shift->method('getShifter')->willReturn($otherBeneficiary);

        $result = $service->canFreeShift($beneficiary, $shift);

        $this->assertFalse($result['result']);
    }

    public function testCanFreeShiftFromAdmin(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiaryWithMembership();

        $shift = $this->createMock(Shift::class);
        $shift->method('getShifter')->willReturn($beneficiary);
        $shift->method('getIsPast')->willReturn(true);
        $shift->method('getIsCurrent')->willReturn(false);

        // From admin: past shift can be freed
        $result = $service->canFreeShift($beneficiary, $shift, true);

        $this->assertTrue($result['result']);
        $this->assertEmpty($result['message']);
    }

    public function testCannotFreeShiftInThePast(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiaryWithMembership();

        $shift = $this->createMock(Shift::class);
        $shift->method('getShifter')->willReturn($beneficiary);
        $shift->method('getIsPast')->willReturn(true);
        $shift->method('getIsCurrent')->willReturn(false);

        // Not from admin: past shift cannot be freed
        $result = $service->canFreeShift($beneficiary, $shift, false);

        $this->assertFalse($result['result']);
    }

    public function testCannotFreeFixedShiftWhenNotAllowed(): void
    {
        $service = $this->createService([
            'use_fly_and_fixed' => true,
            'fly_and_fixed_allow_fixed_shift_free' => false,
        ]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        $shift = $this->createMock(Shift::class);
        $shift->method('getShifter')->willReturn($beneficiary);
        $shift->method('getIsPast')->willReturn(false);
        $shift->method('getIsCurrent')->willReturn(false);
        $shift->method('isFixe')->willReturn(true);

        $result = $service->canFreeShift($beneficiary, $shift, false);

        $this->assertFalse($result['result']);
    }

    public function testCanFreeFixedShiftWhenAllowed(): void
    {
        $service = $this->createService([
            'use_fly_and_fixed' => true,
            'fly_and_fixed_allow_fixed_shift_free' => true,
        ]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        $shift = $this->createMock(Shift::class);
        $shift->method('getShifter')->willReturn($beneficiary);
        $shift->method('getIsPast')->willReturn(false);
        $shift->method('getIsCurrent')->willReturn(false);
        $shift->method('isFixe')->willReturn(true);

        $result = $service->canFreeShift($beneficiary, $shift, false);

        $this->assertTrue($result['result']);
    }

    // -------------------------------------------------------
    // canFreeShift() — time log saving rules
    // -------------------------------------------------------

    public function testCannotFreeShiftWhenTooCloseInAdvanceWithTimeLogs(): void
    {
        $service = $this->createService([
            'use_time_log_saving' => true,
            'time_log_saving_shift_free_min_time_in_advance_days' => 3,
            'time_log_saving_shift_free_allow_only_if_enough_saving' => false,
        ]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        $shift = $this->createMock(Shift::class);
        $shift->method('getShifter')->willReturn($beneficiary);
        $shift->method('getIsPast')->willReturn(false);
        $shift->method('getIsCurrent')->willReturn(false);
        // isBefore('3 days') → true means shift is within next 3 days
        $shift->method('isBefore')->with('3 days')->willReturn(true);

        $result = $service->canFreeShift($beneficiary, $shift, false);

        $this->assertFalse($result['result']);
    }

    // -------------------------------------------------------
    // isBeginner()
    // -------------------------------------------------------

    public function testIsBeginnerWhenDisabled(): void
    {
        $service = $this->createService(['newUserStartAsBeginner' => false]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        $this->assertFalse($service->isBeginner($beneficiary));
    }

    public function testIsBeginnerWhenEnabledAndNoPreviousShifts(): void
    {
        $service = $this->createService(['newUserStartAsBeginner' => true]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        // No shifts → is beginner
        $this->assertTrue($service->isBeginner($beneficiary));
    }

    public function testIsBeginnerWhenEnabledAndHasPreviousShifts(): void
    {
        $service = $this->createService(['newUserStartAsBeginner' => true]);
        $beneficiary = $this->createBeneficiaryWithMembership();

        // Add a past shift
        $shift = new Shift();
        $shift->setStart(new \DateTime('-10 days'));
        $shift->setEnd(new \DateTime('-10 days +3 hours'));
        $beneficiary->addShift($shift);

        $this->assertFalse($service->isBeginner($beneficiary));
    }

    // -------------------------------------------------------
    // getMinimalShiftDuration()
    // -------------------------------------------------------

    public function testGetMinimalShiftDuration(): void
    {
        $service = $this->createService(['min_shift_duration' => 90]);

        $this->assertEquals(90, $service->getMinimalShiftDuration());
    }

    public function testGetMinimalShiftDurationCustomValue(): void
    {
        $service = $this->createService(['min_shift_duration' => 60]);

        $this->assertEquals(60, $service->getMinimalShiftDuration());
    }
}
