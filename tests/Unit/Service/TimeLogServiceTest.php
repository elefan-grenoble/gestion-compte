<?php

namespace App\Tests\Unit\Service;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Shift;
use App\Entity\TimeLog;
use App\Service\MembershipService;
use App\Service\TimeLogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class TimeLogServiceTest extends TestCase
{
    /** @var EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $em;

    /** @var RequestStack|\PHPUnit\Framework\MockObject\MockObject */
    private $requestStack;

    /** @var TokenStorageInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $tokenStorage;

    /** @var MembershipService|\PHPUnit\Framework\MockObject\MockObject */
    private $membershipService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->membershipService = $this->getMockBuilder(MembershipService::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Default: no authenticated user, no request
        $this->tokenStorage->method('getToken')->willReturn(null);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
    }

    private function createService(int $dueDuration = 180): TimeLogService
    {
        return new TimeLogService(
            $this->em,
            $this->requestStack,
            $this->tokenStorage,
            $this->membershipService,
            $dueDuration
        );
    }

    private function createMembership(): Membership
    {
        $membership = new Membership();
        $membership->setMemberNumber(1);
        $membership->setWithdrawn(false);
        $membership->setFrozen(false);
        $membership->setFlying(false);
        return $membership;
    }

    private function createShiftWithBeneficiary(int $duration = 180): Shift
    {
        $membership = $this->createMembership();

        $beneficiary = new Beneficiary();
        $beneficiary->setFirstname('Test');
        $beneficiary->setLastname('User');
        $beneficiary->setFlying(false);
        $membership->setMainBeneficiary($beneficiary);

        $shift = new Shift();
        $shift->setStart(new \DateTime('2025-03-01 09:00'));
        $shift->setEnd(new \DateTime('2025-03-01 09:00 +' . $duration . ' minutes'));
        $shift->setShifter($beneficiary);

        return $shift;
    }

    // -------------------------------------------------------
    // initTimeLog()
    // -------------------------------------------------------

    public function testInitTimeLogBasic(): void
    {
        $service = $this->createService();
        $member = $this->createMembership();

        $log = $service->initTimeLog($member);

        $this->assertInstanceOf(TimeLog::class, $log);
        $this->assertSame($member, $log->getMembership());
    }

    public function testInitTimeLogWithDate(): void
    {
        $service = $this->createService();
        $member = $this->createMembership();
        $date = new \DateTime('2025-01-15 10:00:00');

        $log = $service->initTimeLog($member, $date);

        $this->assertEquals($date, $log->getCreatedAt());
    }

    public function testInitTimeLogWithDescription(): void
    {
        $service = $this->createService();
        $member = $this->createMembership();

        $log = $service->initTimeLog($member, null, 'Test description');

        $this->assertEquals('Test description', $log->getDescription());
    }

    public function testInitTimeLogWithAuthenticatedUser(): void
    {
        $user = new \App\Entity\User();

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage->method('getToken')->willReturn($token);

        $service = $this->createService();
        $member = $this->createMembership();

        $log = $service->initTimeLog($member);

        $this->assertSame($user, $log->getCreatedBy());
    }

    public function testInitTimeLogWithRequest(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'admin_timelog_create');

        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $service = $this->createService();
        $member = $this->createMembership();

        $log = $service->initTimeLog($member);

        $this->assertEquals('admin_timelog_create', $log->getRequestRoute());
    }

    // -------------------------------------------------------
    // initShiftValidatedTimeLog()
    // -------------------------------------------------------

    public function testInitShiftValidatedTimeLog(): void
    {
        $service = $this->createService();
        $shift = $this->createShiftWithBeneficiary(180);

        $log = $service->initShiftValidatedTimeLog($shift);

        $this->assertEquals(TimeLog::TYPE_SHIFT_VALIDATED, $log->getType());
        $this->assertSame($shift, $log->getShift());
        $this->assertEquals(180, $log->getTime());
        $this->assertSame($shift->getShifter()->getMembership(), $log->getMembership());
    }

    // -------------------------------------------------------
    // initShiftInvalidatedTimeLog()
    // -------------------------------------------------------

    public function testInitShiftInvalidatedTimeLog(): void
    {
        $service = $this->createService();
        $shift = $this->createShiftWithBeneficiary(90);
        $member = $shift->getShifter()->getMembership();

        $log = $service->initShiftInvalidatedTimeLog($shift, $member);

        $this->assertEquals(TimeLog::TYPE_SHIFT_INVALIDATED, $log->getType());
        $this->assertSame($shift, $log->getShift());
        $this->assertEquals(-90, $log->getTime(), 'Invalidated time should be negative');
    }

    // -------------------------------------------------------
    // initCycleBeginningTimeLog()
    // -------------------------------------------------------

    public function testInitCycleBeginningTimeLog(): void
    {
        $service = $this->createService(180);
        $member = $this->createMembership();

        $log = $service->initCycleBeginningTimeLog($member);

        $this->assertEquals(TimeLog::TYPE_CYCLE_END, $log->getType());
        $this->assertEquals(-180, $log->getTime(), 'Cycle beginning should deduct due_duration_by_cycle');
    }

    public function testInitCycleBeginningTimeLogWithCustomDuration(): void
    {
        $service = $this->createService(240);
        $member = $this->createMembership();

        $log = $service->initCycleBeginningTimeLog($member);

        $this->assertEquals(-240, $log->getTime());
    }

    // -------------------------------------------------------
    // initCurrentCycleBeginningTimeLog()
    // -------------------------------------------------------

    public function testInitCurrentCycleBeginningTimeLog(): void
    {
        $cycleStart = new \DateTime('2025-02-03');
        $this->membershipService->method('getStartOfCycle')
            ->with($this->anything(), 0)
            ->willReturn($cycleStart);

        $service = $this->createService(180);
        $member = $this->createMembership();

        $log = $service->initCurrentCycleBeginningTimeLog($member);

        $this->assertEquals(TimeLog::TYPE_CYCLE_END, $log->getType());
        $this->assertEquals(-180, $log->getTime());
        $this->assertEquals($cycleStart, $log->getCreatedAt());
    }

    // -------------------------------------------------------
    // initSavingTimeLog()
    // -------------------------------------------------------

    public function testInitSavingTimeLog(): void
    {
        $service = $this->createService();
        $member = $this->createMembership();

        $log = $service->initSavingTimeLog($member, 60);

        $this->assertEquals(TimeLog::TYPE_SAVING, $log->getType());
        $this->assertEquals(60, $log->getTime());
        $this->assertNull($log->getShift());
    }

    public function testInitSavingTimeLogWithShift(): void
    {
        $service = $this->createService();
        $member = $this->createMembership();
        $shift = $this->createShiftWithBeneficiary(90);

        $log = $service->initSavingTimeLog($member, 90, null, $shift);

        $this->assertEquals(TimeLog::TYPE_SAVING, $log->getType());
        $this->assertSame($shift, $log->getShift());
    }

    // -------------------------------------------------------
    // initCustomTimeLog()
    // -------------------------------------------------------

    public function testInitCustomTimeLog(): void
    {
        $service = $this->createService();
        $member = $this->createMembership();

        $log = $service->initCustomTimeLog($member, 45, null, 'Manual adjustment');

        $this->assertEquals(TimeLog::TYPE_CUSTOM, $log->getType());
        $this->assertEquals(45, $log->getTime());
        $this->assertEquals('Manual adjustment', $log->getDescription());
    }

    public function testInitCustomTimeLogWithoutTime(): void
    {
        $service = $this->createService();
        $member = $this->createMembership();

        $log = $service->initCustomTimeLog($member);

        $this->assertEquals(TimeLog::TYPE_CUSTOM, $log->getType());
    }

    // -------------------------------------------------------
    // initRegulateOptionalShiftsTimeLog()
    // -------------------------------------------------------

    public function testInitRegulateOptionalShiftsTimeLog(): void
    {
        $service = $this->createService();
        $member = $this->createMembership();

        $log = $service->initRegulateOptionalShiftsTimeLog($member, -30);

        $this->assertEquals(TimeLog::TYPE_REGULATE_OPTIONAL_SHIFTS, $log->getType());
        $this->assertEquals(-30, $log->getTime());
    }

    // -------------------------------------------------------
    // initShiftFreedSavingTimeLog()
    // -------------------------------------------------------

    public function testInitShiftFreedSavingTimeLog(): void
    {
        $service = $this->createService();
        $member = $this->createMembership();
        $shift = $this->createShiftWithBeneficiary(90);

        $log = $service->initShiftFreedSavingTimeLog($member, -90, null, $shift);

        $this->assertEquals(TimeLog::TYPE_SHIFT_FREED_SAVING, $log->getType());
        $this->assertEquals(-90, $log->getTime());
        $this->assertSame($shift, $log->getShift());
    }

    // -------------------------------------------------------
    // initCycleEndSavingTimeLog()
    // -------------------------------------------------------

    public function testInitCycleEndSavingTimeLog(): void
    {
        $service = $this->createService();
        $member = $this->createMembership();

        $log = $service->initCycleEndSavingTimeLog($member, 60, null, 'End of cycle saving');

        $this->assertEquals(TimeLog::TYPE_CYCLE_END_SAVING, $log->getType());
        $this->assertEquals(60, $log->getTime());
        $this->assertEquals('End of cycle saving', $log->getDescription());
    }
}
