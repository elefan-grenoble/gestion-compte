<?php

namespace App\Tests\Unit\Service;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Registration;
use App\Entity\Shift;
use App\Service\BeneficiaryService;
use App\Service\MembershipService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BeneficiaryServiceTest extends TestCase
{
    /** @var ContainerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $container;

    /** @var EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $em;

    /** @var MembershipService|\PHPUnit\Framework\MockObject\MockObject */
    private $membershipService;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->em = $this->getMockBuilder(EntityManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->membershipService = $this->getMockBuilder(MembershipService::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createService(array $params = []): BeneficiaryService
    {
        $defaults = [
            'use_fly_and_fixed' => false,
            'fly_and_fixed_entity_flying' => 'Membership',
            'member_withdrawn_icon' => '🚫',
            'member_frozen_icon' => '❄️',
            'beneficiary_flying_icon' => '🕊️',
            'member_flying_icon' => '🦅',
            'member_exempted_icon' => '🏖️',
            'member_registration_missing_icon' => '⚠️',
        ];
        $params = array_merge($defaults, $params);

        $this->container->method('getParameter')
            ->willReturnCallback(function ($key) use ($params) {
                return $params[$key] ?? null;
            });

        return new BeneficiaryService($this->container, $this->em, $this->membershipService);
    }

    /**
     * Create a Beneficiary with membership set up.
     */
    private function createBeneficiary(string $firstName = 'Jean', string $lastName = 'Dupont', int $memberNumber = 42): Beneficiary
    {
        $membership = new Membership();
        $membership->setMemberNumber($memberNumber);
        $membership->setWithdrawn(false);
        $membership->setFrozen(false);
        $membership->setFlying(false);

        // Add a valid registration
        $registration = new Registration();
        $registration->setDate(new \DateTime('now'));
        $membership->addRegistration($registration);

        $beneficiary = new Beneficiary();
        $beneficiary->setFirstname($firstName);
        $beneficiary->setLastname($lastName);
        $beneficiary->setFlying(false);
        $membership->setMainBeneficiary($beneficiary);

        return $beneficiary;
    }

    // -------------------------------------------------------
    // hasWarningStatus()
    // -------------------------------------------------------

    public function testHasWarningStatusDelegatesToMembershipService(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary();

        $this->membershipService->expects($this->once())
            ->method('hasWarningStatus')
            ->with($beneficiary->getMembership())
            ->willReturn(true);

        $this->assertTrue($service->hasWarningStatus($beneficiary));
    }

    public function testHasWarningStatusReturnsFalseWhenMembershipOk(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary();

        $this->membershipService->method('hasWarningStatus')->willReturn(false);

        $this->assertFalse($service->hasWarningStatus($beneficiary));
    }

    public function testHasWarningStatusWhenBeneficiaryFlyingWithFlyAndFixedOnBeneficiary(): void
    {
        $service = $this->createService([
            'use_fly_and_fixed' => true,
            'fly_and_fixed_entity_flying' => 'Beneficiary',
        ]);
        $beneficiary = $this->createBeneficiary();
        $beneficiary->setFlying(true);

        $this->membershipService->method('hasWarningStatus')->willReturn(false);

        $this->assertTrue($service->hasWarningStatus($beneficiary));
    }

    public function testHasWarningStatusWhenBeneficiaryFlyingButEntityIsMembership(): void
    {
        $service = $this->createService([
            'use_fly_and_fixed' => true,
            'fly_and_fixed_entity_flying' => 'Membership',
        ]);
        $beneficiary = $this->createBeneficiary();
        $beneficiary->setFlying(true);

        $this->membershipService->method('hasWarningStatus')->willReturn(false);

        // Flying is on Beneficiary but config says Membership → no warning from this check
        $this->assertFalse($service->hasWarningStatus($beneficiary));
    }

    // -------------------------------------------------------
    // getStatusIcon()
    // -------------------------------------------------------

    public function testGetStatusIconNoWarnings(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary();

        $this->membershipService->method('isUptodate')->willReturn(true);

        $this->assertEquals('', $service->getStatusIcon($beneficiary));
    }

    public function testGetStatusIconWithdrawn(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary();
        $beneficiary->getMembership()->setWithdrawn(true);

        $this->membershipService->method('isUptodate')->willReturn(true);

        $icon = $service->getStatusIcon($beneficiary);
        $this->assertStringContainsString('🚫', $icon);
    }

    public function testGetStatusIconFrozen(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary();
        $beneficiary->getMembership()->setFrozen(true);

        $this->membershipService->method('isUptodate')->willReturn(true);

        $icon = $service->getStatusIcon($beneficiary);
        $this->assertStringContainsString('❄️', $icon);
    }

    public function testGetStatusIconRegistrationMissing(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary();

        $this->membershipService->method('isUptodate')->willReturn(false);

        $icon = $service->getStatusIcon($beneficiary);
        $this->assertStringContainsString('⚠️', $icon);
    }

    public function testGetStatusIconMultipleStatuses(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary();
        $beneficiary->getMembership()->setWithdrawn(true);
        $beneficiary->getMembership()->setFrozen(true);

        $this->membershipService->method('isUptodate')->willReturn(false);

        $icon = $service->getStatusIcon($beneficiary);
        // Should contain all 3 icons separated by /
        $this->assertStringContainsString('🚫', $icon);
        $this->assertStringContainsString('❄️', $icon);
        $this->assertStringContainsString('⚠️', $icon);
        $this->assertStringContainsString('/', $icon);
        // Should be wrapped in brackets
        $this->assertStringStartsWith('[', $icon);
        $this->assertStringEndsWith(']', $icon);
    }

    public function testGetStatusIconBeneficiaryFlyingWithFlyAndFixed(): void
    {
        $service = $this->createService([
            'use_fly_and_fixed' => true,
            'fly_and_fixed_entity_flying' => 'Beneficiary',
        ]);
        $beneficiary = $this->createBeneficiary();
        $beneficiary->setFlying(true);

        $this->membershipService->method('isUptodate')->willReturn(true);

        $icon = $service->getStatusIcon($beneficiary);
        $this->assertStringContainsString('🕊️', $icon);
    }

    public function testGetStatusIconMembershipFlyingWithFlyAndFixed(): void
    {
        $service = $this->createService([
            'use_fly_and_fixed' => true,
            'fly_and_fixed_entity_flying' => 'Membership',
        ]);
        $beneficiary = $this->createBeneficiary();
        $beneficiary->getMembership()->setFlying(true);

        $this->membershipService->method('isUptodate')->willReturn(true);

        $icon = $service->getStatusIcon($beneficiary);
        $this->assertStringContainsString('🦅', $icon);
    }

    // -------------------------------------------------------
    // getDisplayNameWithMemberNumberAndStatusIcon()
    // -------------------------------------------------------

    public function testGetDisplayNameWithMemberNumberAndStatusIcon(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary('Jean', 'Dupont', 42);

        $this->membershipService->method('isUptodate')->willReturn(true);

        $result = $service->getDisplayNameWithMemberNumberAndStatusIcon($beneficiary);

        $this->assertStringStartsWith('#42', $result);
        $this->assertStringContainsString('Jean', $result);
        $this->assertStringContainsString('DUPONT', $result);
    }

    public function testGetDisplayNameWithMemberNumberAndStatusIconWithWarning(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary('Marie', 'Martin', 99);
        $beneficiary->getMembership()->setFrozen(true);

        $this->membershipService->method('isUptodate')->willReturn(true);

        $result = $service->getDisplayNameWithMemberNumberAndStatusIcon($beneficiary);

        $this->assertStringStartsWith('#99', $result);
        $this->assertStringContainsString('❄️', $result);
        $this->assertStringContainsString('Marie', $result);
    }

    // -------------------------------------------------------
    // getCycleShiftDurationSum()
    // -------------------------------------------------------

    public function testGetCycleShiftDurationSumWithShifts(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary();

        $cycleStart = new \DateTime('2025-01-06');
        $cycleEnd = new \DateTime('2025-02-02');

        $this->membershipService->method('getStartOfCycle')->willReturn($cycleStart);
        $this->membershipService->method('getEndOfCycle')->willReturn($cycleEnd);

        // Create mock shifts with durations
        $shift1 = $this->createMock(Shift::class);
        $shift1->method('getDuration')->willReturn(90);
        $shift2 = $this->createMock(Shift::class);
        $shift2->method('getDuration')->willReturn(180);

        $shiftRepo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->addMethods(['findShiftsForBeneficiary'])
            ->getMock();
        $shiftRepo->method('findShiftsForBeneficiary')
            ->willReturn(new ArrayCollection([$shift1, $shift2]));

        $this->em->method('getRepository')
            ->with('App:Shift')
            ->willReturn($shiftRepo);

        $sum = $service->getCycleShiftDurationSum($beneficiary, 0);

        $this->assertEquals(270, $sum);
    }

    public function testGetCycleShiftDurationSumWithNoShifts(): void
    {
        $service = $this->createService();
        $beneficiary = $this->createBeneficiary();

        $this->membershipService->method('getStartOfCycle')->willReturn(new \DateTime('2025-01-06'));
        $this->membershipService->method('getEndOfCycle')->willReturn(new \DateTime('2025-02-02'));

        $shiftRepo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->addMethods(['findShiftsForBeneficiary'])
            ->getMock();
        $shiftRepo->method('findShiftsForBeneficiary')
            ->willReturn(new ArrayCollection());

        $this->em->method('getRepository')
            ->with('App:Shift')
            ->willReturn($shiftRepo);

        $sum = $service->getCycleShiftDurationSum($beneficiary, 0);

        $this->assertEquals(0, $sum);
    }
}
