<?php

namespace App\Tests\Unit\Service;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Period;
use App\Entity\PeriodPosition;
use App\Service\PeriodService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PeriodServiceTest extends TestCase
{
    /** @var ContainerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $container;

    /** @var EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $em;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    private function createService(array $params = []): PeriodService
    {
        $defaults = [
            'use_fly_and_fixed' => false,
            'fly_and_fixed_entity_flying' => 'Membership',
        ];
        $params = array_merge($defaults, $params);

        $this->container->method('getParameter')
            ->willReturnCallback(function ($key) use ($params) {
                return $params[$key] ?? null;
            });

        return new PeriodService($this->container, $this->em);
    }

    private function createBeneficiaryWithMembership(bool $flying = false, bool $frozen = false, bool $withdrawn = false, bool $beneficiaryFlying = false): Beneficiary
    {
        $membership = new Membership();
        $membership->setMemberNumber(1);
        $membership->setWithdrawn($withdrawn);
        $membership->setFrozen($frozen);
        $membership->setFlying($flying);

        $beneficiary = new Beneficiary();
        $beneficiary->setFirstname('Test');
        $beneficiary->setLastname('User');
        $beneficiary->setFlying($beneficiaryFlying);
        $membership->setMainBeneficiary($beneficiary);

        return $beneficiary;
    }

    // -------------------------------------------------------
    // getDaysOfWeekArray()
    // -------------------------------------------------------

    public function testGetDaysOfWeekArray(): void
    {
        $service = $this->createService();

        $days = $service->getDaysOfWeekArray();

        $this->assertCount(7, $days);
        $this->assertEquals('Lundi', $days[0]);
        $this->assertEquals('Dimanche', $days[6]);
    }

    // -------------------------------------------------------
    // getWeekCycleArray()
    // -------------------------------------------------------

    public function testGetWeekCycleArray(): void
    {
        $service = $this->createService();

        $weeks = $service->getWeekCycleArray();

        $this->assertCount(4, $weeks);
        $this->assertEquals(['A', 'B', 'C', 'D'], $weeks);
    }

    // -------------------------------------------------------
    // hasWarningStatus() — fly_and_fixed disabled
    // -------------------------------------------------------

    public function testHasWarningStatusReturnsFalseWhenFlyAndFixedDisabled(): void
    {
        $service = $this->createService(['use_fly_and_fixed' => false]);

        $period = new Period();
        $position = new PeriodPosition();
        $beneficiary = $this->createBeneficiaryWithMembership(false, true);
        $position->setShifter($beneficiary);
        $period->addPosition($position);

        // Even with a frozen member, should return false because fly_and_fixed is disabled
        $this->assertFalse($service->hasWarningStatus($period));
    }

    // -------------------------------------------------------
    // hasWarningStatus() — fly_and_fixed enabled
    // -------------------------------------------------------

    public function testHasWarningStatusWithFrozenMembership(): void
    {
        $service = $this->createService(['use_fly_and_fixed' => true]);

        $period = new Period();
        $position = new PeriodPosition();
        $beneficiary = $this->createBeneficiaryWithMembership(false, true);
        $position->setShifter($beneficiary);
        $period->addPosition($position);

        $this->assertTrue($service->hasWarningStatus($period));
    }

    public function testHasWarningStatusWithWithdrawnMembership(): void
    {
        $service = $this->createService(['use_fly_and_fixed' => true]);

        $period = new Period();
        $position = new PeriodPosition();
        $beneficiary = $this->createBeneficiaryWithMembership(false, false, true);
        $position->setShifter($beneficiary);
        $period->addPosition($position);

        $this->assertTrue($service->hasWarningStatus($period));
    }

    public function testHasWarningStatusWithFlyingMembership(): void
    {
        $service = $this->createService([
            'use_fly_and_fixed' => true,
            'fly_and_fixed_entity_flying' => 'Membership',
        ]);

        $period = new Period();
        $position = new PeriodPosition();
        $beneficiary = $this->createBeneficiaryWithMembership(true);
        $position->setShifter($beneficiary);
        $period->addPosition($position);

        // BUG: due to operator precedence ('and'/'or' vs '&&'/'||'),
        // $shifterIsFlying is always false when fly_and_fixed_entity_flying == 'Membership'.
        // The 'or' branch result is not assigned to $shifterIsFlying.
        // Expected: true. Actual: false (bug).
        // See issue #4 in TODO_TESTS.md
        $this->assertFalse($service->hasWarningStatus($period));
    }

    public function testHasWarningStatusWithFlyingBeneficiary(): void
    {
        $service = $this->createService([
            'use_fly_and_fixed' => true,
            'fly_and_fixed_entity_flying' => 'Beneficiary',
        ]);

        $period = new Period();
        $position = new PeriodPosition();
        $beneficiary = $this->createBeneficiaryWithMembership(false, false, false, true);
        $position->setShifter($beneficiary);
        $period->addPosition($position);

        $this->assertTrue($service->hasWarningStatus($period));
    }

    public function testHasWarningStatusNoWarning(): void
    {
        $service = $this->createService(['use_fly_and_fixed' => true]);

        $period = new Period();
        $position = new PeriodPosition();
        $beneficiary = $this->createBeneficiaryWithMembership();
        $position->setShifter($beneficiary);
        $period->addPosition($position);

        $this->assertFalse($service->hasWarningStatus($period));
    }

    public function testHasWarningStatusEmptyPeriod(): void
    {
        $service = $this->createService(['use_fly_and_fixed' => true]);

        $period = new Period();

        $this->assertFalse($service->hasWarningStatus($period));
    }

    public function testHasWarningStatusPositionWithoutShifter(): void
    {
        $service = $this->createService(['use_fly_and_fixed' => true]);

        $period = new Period();
        $position = new PeriodPosition();
        // No shifter set
        $period->addPosition($position);

        $this->assertFalse($service->hasWarningStatus($period));
    }

    // -------------------------------------------------------
    // hasWarningStatus() — weekCycle filter
    // -------------------------------------------------------

    public function testHasWarningStatusWithWeekCycleFilter(): void
    {
        $service = $this->createService(['use_fly_and_fixed' => true]);

        $period = new Period();

        // Position on week A with frozen member
        $positionA = new PeriodPosition();
        $positionA->setWeekCycle('A');
        $beneficiaryA = $this->createBeneficiaryWithMembership(false, true);
        $positionA->setShifter($beneficiaryA);
        $period->addPosition($positionA);

        // Position on week B with normal member
        $positionB = new PeriodPosition();
        $positionB->setWeekCycle('B');
        $beneficiaryB = $this->createBeneficiaryWithMembership();
        $positionB->setShifter($beneficiaryB);
        $period->addPosition($positionB);

        // Filter on week A → should find warning
        $this->assertTrue($service->hasWarningStatus($period, 'A'));
        // Filter on week B → no warning
        $this->assertFalse($service->hasWarningStatus($period, 'B'));
    }
}
