<?php

namespace App\Tests\Integration;

use App\Entity\Membership;
use App\Entity\Shift;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\PersistsEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the test infrastructure itself: the per-test rollback of
 * DatabaseIsolationExtension and the entity builders. If this fails, every
 * other DB-backed test is suspect.
 *
 * @internal
 */
class DatabaseIsolationTest extends KernelTestCase
{
    use PersistsEntities;

    private const MEMBER_NUMBER = 999000001;

    public function testWhatATestWritesIsSharedByEveryKernelItBoots(): void
    {
        $membership = static::persist(MembershipBuilder::aMembership()->withMemberNumber(self::MEMBER_NUMBER)->build());
        $username = $membership->getMainBeneficiary()->getUser()->getUsername();

        // A new kernel opens a new DBAL connection; the static driver hands it
        // the same underlying connection, so it sees the uncommitted row.
        self::ensureKernelShutdown();

        $reloaded = static::entityManager()->getRepository(Membership::class)->findOneBy(['member_number' => self::MEMBER_NUMBER]);
        $this->assertNotNull($reloaded);
        $this->assertSame($username, $reloaded->getMainBeneficiary()->getUser()->getUsername());
        $this->assertNotEmpty($reloaded->getMainBeneficiary()->getUser()->getPassword(), 'the plain password should have been hashed on persist');
        $this->assertCount(1, $reloaded->getRegistrations());
    }

    /**
     * @depends testWhatATestWritesIsSharedByEveryKernelItBoots
     */
    public function testWhatThePreviousTestWroteWasRolledBack(): void
    {
        $this->assertNull(
            static::entityManager()->getRepository(Membership::class)->findOneBy(['member_number' => self::MEMBER_NUMBER]),
            'the membership written by the previous test should have been rolled back'
        );
    }

    public function testAShiftBuiltForABeneficiaryIsBookedByThem(): void
    {
        $beneficiary = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
        $shift = ShiftBuilder::aShift()->bookedBy($beneficiary)->lasting(90)->build();
        static::persist($shift->getJob(), $shift);
        $shiftId = $shift->getId();

        self::ensureKernelShutdown();

        /** @var Shift $reloaded */
        $reloaded = static::entityManager()->find(Shift::class, $shiftId);
        $this->assertSame($beneficiary->getId(), $reloaded->getShifter()->getId());
        $this->assertSame(90, $reloaded->getDuration());
    }
}
