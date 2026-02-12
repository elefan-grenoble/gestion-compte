<?php

namespace App\Tests\Unit\Service;

use App\Entity\Membership;
use App\Entity\MembershipShiftExemption;
use App\Entity\Registration;
use App\Service\MembershipService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MembershipServiceTest extends TestCase
{
    /** @var ContainerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $container;

    /** @var EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $em;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->em = $this->getMockBuilder(EntityManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Create a MembershipService with the given parameters.
     */
    private function createService(array $params = []): MembershipService
    {
        $defaults = [
            'registration_duration' => '1 year',
            'registration_every_civil_year' => true,
            'cycle_type' => 'abcd',
            'use_fly_and_fixed' => false,
            'fly_and_fixed_entity_flying' => 'Membership',
        ];
        $params = array_merge($defaults, $params);

        $this->container->method('getParameter')
            ->willReturnCallback(function ($key) use ($params) {
                return $params[$key] ?? null;
            });

        return new MembershipService($this->container, $this->em);
    }

    /**
     * Create a Membership with an optional last registration date.
     */
    private function createMembershipWithRegistration(\DateTime $registrationDate = null): Membership
    {
        $membership = new Membership();
        $membership->setWithdrawn(false);
        $membership->setFrozen(false);
        $membership->setFlying(false);

        if ($registrationDate) {
            $registration = new Registration();
            $registration->setDate($registrationDate);
            $membership->addRegistration($registration);
        }

        return $membership;
    }

    // -------------------------------------------------------
    // getExpire()
    // -------------------------------------------------------

    public function testGetExpireCivilYearWithRegistration(): void
    {
        $service = $this->createService(['registration_every_civil_year' => true]);

        $membership = $this->createMembershipWithRegistration(new \DateTime('2025-06-15'));
        $expire = $service->getExpire($membership);

        $this->assertEquals('2025-12-31', $expire->format('Y-m-d'));
        $this->assertEquals('23:59:59', $expire->format('H:i:s'));
    }

    public function testGetExpireCivilYearWithoutRegistration(): void
    {
        $service = $this->createService(['registration_every_civil_year' => true]);

        $membership = $this->createMembershipWithRegistration(null);
        $expire = $service->getExpire($membership);

        // Without registration → expire = last day of December of (now - 1 year)
        $expectedYear = (int)(new \DateTime('-1 year'))->format('Y');
        $this->assertEquals($expectedYear . '-12-31', $expire->format('Y-m-d'));
    }

    public function testGetExpireFixedDurationWithRegistration(): void
    {
        $service = $this->createService([
            'registration_every_civil_year' => false,
            'registration_duration' => '1 year',
        ]);

        $membership = $this->createMembershipWithRegistration(new \DateTime('2025-03-01'));
        $expire = $service->getExpire($membership);

        // 2025-03-01 + 1 year - 1 day = 2026-02-28
        $this->assertEquals('2026-02-28', $expire->format('Y-m-d'));
        $this->assertEquals('23:59:59', $expire->format('H:i:s'));
    }

    public function testGetExpireFixedDuration6MonthsWithRegistration(): void
    {
        $service = $this->createService([
            'registration_every_civil_year' => false,
            'registration_duration' => '6 months',
        ]);

        $membership = $this->createMembershipWithRegistration(new \DateTime('2025-07-01'));
        $expire = $service->getExpire($membership);

        // 2025-07-01 + 6 months - 1 day = 2025-12-31
        $this->assertEquals('2025-12-31', $expire->format('Y-m-d'));
    }

    public function testGetExpireFixedDurationWithoutRegistration(): void
    {
        $service = $this->createService([
            'registration_every_civil_year' => false,
            'registration_duration' => '1 year',
        ]);

        $membership = $this->createMembershipWithRegistration(null);
        $expire = $service->getExpire($membership);

        // Without registration → expire = yesterday
        $expected = new \DateTime('-1 day');
        $this->assertEquals($expected->format('Y-m-d'), $expire->format('Y-m-d'));
    }

    // -------------------------------------------------------
    // isUptodate()
    // -------------------------------------------------------

    public function testIsUptodateWithValidRegistration(): void
    {
        $service = $this->createService(['registration_every_civil_year' => true]);

        // Registration this year → expire end of this year → up to date
        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $this->assertTrue($service->isUptodate($membership));
    }

    public function testIsUptodateWithExpiredRegistration(): void
    {
        $service = $this->createService(['registration_every_civil_year' => true]);

        // Registration 2 years ago → expire end of that year → expired
        $membership = $this->createMembershipWithRegistration(new \DateTime('-2 years'));
        $this->assertFalse($service->isUptodate($membership));
    }

    public function testIsUptodateWithoutRegistration(): void
    {
        $service = $this->createService(['registration_every_civil_year' => true]);

        $membership = $this->createMembershipWithRegistration(null);
        $this->assertFalse($service->isUptodate($membership));
    }

    // -------------------------------------------------------
    // canRegister()
    // -------------------------------------------------------

    public function testCanRegisterWhenExpired(): void
    {
        $service = $this->createService(['registration_every_civil_year' => true]);

        // Registration 2 years ago → expired → can re-register
        $membership = $this->createMembershipWithRegistration(new \DateTime('-2 years'));
        $this->assertTrue($service->canRegister($membership));
    }

    public function testCanRegisterWhenExpiringSoon(): void
    {
        $service = $this->createService([
            'registration_every_civil_year' => false,
            'registration_duration' => '1 year',
        ]);

        // Registered about 11.5 months ago → expires in ~15 days (< 28 days) → can register
        $registrationDate = new \DateTime('-11 months -15 days');
        $membership = $this->createMembershipWithRegistration($registrationDate);
        $this->assertTrue($service->canRegister($membership));
    }

    public function testCannotRegisterWhenFreshlyRegistered(): void
    {
        $service = $this->createService([
            'registration_every_civil_year' => false,
            'registration_duration' => '1 year',
        ]);

        // Just registered → expires in ~1 year → cannot register yet
        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $this->assertFalse($service->canRegister($membership));
    }

    // -------------------------------------------------------
    // getRemainder()
    // -------------------------------------------------------

    public function testGetRemainderReturnsDateInterval(): void
    {
        $service = $this->createService(['registration_every_civil_year' => true]);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $remainder = $service->getRemainder($membership);

        $this->assertInstanceOf(\DateInterval::class, $remainder);
    }

    // -------------------------------------------------------
    // getStartOfCycle() / getEndOfCycle() — ABCD mode
    // -------------------------------------------------------

    public function testGetStartOfCycleAbcdReturnsMonday(): void
    {
        $service = $this->createService(['cycle_type' => 'abcd']);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $start = $service->getStartOfCycle($membership);

        // Must be a Monday
        $this->assertEquals('1', $start->format('N'), 'Start of cycle should be a Monday');
        // Time should be 00:00:00
        $this->assertEquals('00:00:00', $start->format('H:i:s'));
    }

    public function testGetEndOfCycleAbcdIs27DaysAfterStart(): void
    {
        $service = $this->createService(['cycle_type' => 'abcd']);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $start = $service->getStartOfCycle($membership);
        $end = $service->getEndOfCycle($membership);

        $diff = $start->diff($end)->days;
        $this->assertEquals(27, $diff, 'Cycle should be 28 days (0..27)');
        $this->assertEquals('23:59:59', $end->format('H:i:s'));
    }

    public function testGetStartOfCycleWithOffset(): void
    {
        $service = $this->createService(['cycle_type' => 'abcd']);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $start0 = $service->getStartOfCycle($membership, 0);
        $start1 = $service->getStartOfCycle($membership, 1);

        $diff = $start0->diff($start1)->days;
        $this->assertEquals(28, $diff, 'Next cycle should start 28 days later');
    }

    public function testGetStartOfCycleWithNegativeOffset(): void
    {
        $service = $this->createService(['cycle_type' => 'abcd']);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $start0 = $service->getStartOfCycle($membership, 0);
        $startMinus1 = $service->getStartOfCycle($membership, -1);

        $diff = $startMinus1->diff($start0)->days;
        $this->assertEquals(28, $diff, 'Previous cycle should be 28 days before');
    }

    // -------------------------------------------------------
    // getStartOfCycle() — non-ABCD mode (firstShiftDate based)
    // -------------------------------------------------------

    public function testGetStartOfCycleNonAbcdWithFirstShiftDate(): void
    {
        $service = $this->createService(['cycle_type' => 'custom']);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        // Set firstShiftDate to exactly 56 days ago at midnight (2 full cycles)
        $firstShiftDate = new \DateTime('today -56 days');
        $membership->setFirstShiftDate($firstShiftDate);

        $start = $service->getStartOfCycle($membership);

        // 56 days / 28 = 2 cycles → start should be firstShiftDate + 56 days = today
        $expected = new \DateTime('today');
        $this->assertEquals($expected->format('Y-m-d'), $start->format('Y-m-d'));
        $this->assertEquals('00:00:00', $start->format('H:i:s'));
    }

    // -------------------------------------------------------
    // hasWarningStatus()
    // -------------------------------------------------------

    public function testHasWarningStatusWhenWithdrawn(): void
    {
        $service = $this->createService();

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $membership->setWithdrawn(true);

        $this->assertTrue($service->hasWarningStatus($membership));
    }

    public function testHasWarningStatusWhenFrozen(): void
    {
        $service = $this->createService();

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $membership->setFrozen(true);

        $this->assertTrue($service->hasWarningStatus($membership));
    }

    public function testHasWarningStatusWhenNotUptodate(): void
    {
        $service = $this->createService(['registration_every_civil_year' => true]);

        // Registration 2 years ago → expired
        $membership = $this->createMembershipWithRegistration(new \DateTime('-2 years'));

        $this->assertTrue($service->hasWarningStatus($membership));
    }

    public function testHasWarningStatusWhenFlyingWithFlyAndFixedOnMembership(): void
    {
        $service = $this->createService([
            'use_fly_and_fixed' => true,
            'fly_and_fixed_entity_flying' => 'Membership',
        ]);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $membership->setFlying(true);

        $this->assertTrue($service->hasWarningStatus($membership));
    }

    public function testHasWarningStatusWhenFlyingButFlyAndFixedDisabled(): void
    {
        $service = $this->createService([
            'use_fly_and_fixed' => false,
        ]);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $membership->setFlying(true);

        // Flying but fly_and_fixed disabled → not a warning
        $this->assertFalse($service->hasWarningStatus($membership));
    }

    public function testNoWarningStatusWhenEverythingIsOk(): void
    {
        $service = $this->createService();

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));

        $this->assertFalse($service->hasWarningStatus($membership));
    }

    // -------------------------------------------------------
    // getEndOfCycle()
    // -------------------------------------------------------

    public function testGetEndOfCycleTimestamp(): void
    {
        $service = $this->createService(['cycle_type' => 'abcd']);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $end = $service->getEndOfCycle($membership);

        $this->assertEquals('23:59:59', $end->format('H:i:s'));
    }

    // -------------------------------------------------------
    // getCycleNumber()
    // -------------------------------------------------------

    public function testGetCycleNumberCurrentCycle(): void
    {
        $service = $this->createService(['cycle_type' => 'abcd']);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $cycleNumber = $service->getCycleNumber($membership, new \DateTime('now'));

        $this->assertEquals(0, $cycleNumber, 'Current date should be in cycle 0');
    }

    public function testGetCycleNumberNextCycle(): void
    {
        $service = $this->createService(['cycle_type' => 'abcd']);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $endOfCycle = $service->getEndOfCycle($membership, 0);
        $nextCycleDate = clone $endOfCycle;
        $nextCycleDate->modify('+1 day');

        $cycleNumber = $service->getCycleNumber($membership, $nextCycleDate);

        $this->assertEquals(1, $cycleNumber, 'Date after current cycle should be in cycle 1');
    }

    public function testGetCycleNumberPreviousCycle(): void
    {
        $service = $this->createService(['cycle_type' => 'abcd']);

        $membership = $this->createMembershipWithRegistration(new \DateTime('now'));
        $startOfCycle = $service->getStartOfCycle($membership, 0);
        $prevCycleDate = clone $startOfCycle;
        $prevCycleDate->modify('-1 day');

        $cycleNumber = $service->getCycleNumber($membership, $prevCycleDate);

        $this->assertEquals(-1, $cycleNumber, 'Date before current cycle should be in cycle -1');
    }
}
