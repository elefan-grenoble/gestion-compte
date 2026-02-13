<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\MembershipShiftExemption;
use App\Entity\Note;
use App\Entity\Proxy;
use App\Entity\Registration;
use App\Entity\TimeLog;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class MembershipTest extends TestCase
{
    private function createMembership(): Membership
    {
        $membership = new Membership();
        $membership->setMemberNumber(42);
        $membership->setWithdrawn(false);
        $membership->setFrozen(false);
        $membership->setFrozenChange(false);
        $membership->setFlying(false);

        return $membership;
    }

    private function createBeneficiary(string $firstname, string $lastname): Beneficiary
    {
        $beneficiary = new Beneficiary();
        $beneficiary->setFirstname($firstname);
        $beneficiary->setLastname($lastname);

        return $beneficiary;
    }

    // ── Constructor & defaults ───────────────────────────────────────

    public function testConstructorInitializesEmptyCollections(): void
    {
        $membership = new Membership();

        $this->assertInstanceOf(ArrayCollection::class, $membership->getRegistrations());
        $this->assertInstanceOf(ArrayCollection::class, $membership->getBeneficiaries());
        $this->assertInstanceOf(ArrayCollection::class, $membership->getTimeLogs());
        $this->assertCount(0, $membership->getRegistrations());
        $this->assertCount(0, $membership->getBeneficiaries());
        $this->assertCount(0, $membership->getTimeLogs());
    }

    // ── Member number ────────────────────────────────────────────────

    public function testSetAndGetMemberNumber(): void
    {
        $membership = $this->createMembership();

        $this->assertSame(42, $membership->getMemberNumber());

        $membership->setMemberNumber(999);
        $this->assertSame(999, $membership->getMemberNumber());
    }

    public function testGetDisplayMemberNumber(): void
    {
        $membership = $this->createMembership();

        $this->assertSame('#42', $membership->getDisplayMemberNumber());
    }

    public function testToStringReturnsDisplayMemberNumber(): void
    {
        $membership = $this->createMembership();

        $this->assertSame('#42', (string) $membership);
    }

    // ── Beneficiaries ────────────────────────────────────────────────

    public function testAddAndRemoveBeneficiary(): void
    {
        $membership = $this->createMembership();
        $beneficiary = $this->createBeneficiary('Alice', 'Dupont');

        $membership->addBeneficiary($beneficiary);
        $this->assertCount(1, $membership->getBeneficiaries());
        $this->assertTrue($membership->getBeneficiaries()->contains($beneficiary));

        $membership->removeBeneficiary($beneficiary);
        $this->assertCount(0, $membership->getBeneficiaries());
    }

    public function testSetMainBeneficiaryAlsoAddsToBeneficiaries(): void
    {
        $membership = $this->createMembership();
        $beneficiary = $this->createBeneficiary('Alice', 'Dupont');

        $membership->setMainBeneficiary($beneficiary);

        $this->assertSame($beneficiary, $membership->getMainBeneficiary());
        $this->assertTrue($membership->getBeneficiaries()->contains($beneficiary));
        $this->assertSame($membership, $beneficiary->getMembership());
    }

    public function testSetMainBeneficiaryNullFallsBackToFirst(): void
    {
        $membership = $this->createMembership();
        $beneficiary = $this->createBeneficiary('Alice', 'Dupont');

        $membership->setMainBeneficiary($beneficiary);
        $membership->setMainBeneficiary(null);

        // getMainBeneficiary() auto-assigns from beneficiaries when null
        $this->assertSame($beneficiary, $membership->getMainBeneficiary());
    }

    public function testGetMainBeneficiaryFallsBackToFirst(): void
    {
        $membership = $this->createMembership();
        $beneficiary = $this->createBeneficiary('Bob', 'Martin');

        $membership->addBeneficiary($beneficiary);

        // mainBeneficiary is not explicitly set, should fallback to first
        $this->assertSame($beneficiary, $membership->getMainBeneficiary());
    }

    public function testGetBeneficiariesWithMainInFirstPosition(): void
    {
        $membership = $this->createMembership();
        $main = $this->createBeneficiary('Alice', 'Dupont');
        $other = $this->createBeneficiary('Bob', 'Martin');

        $membership->addBeneficiary($other);
        $membership->setMainBeneficiary($main);

        $result = $membership->getBeneficiariesWithMainInFirstPosition();

        $this->assertSame($main, $result[0]);
        $this->assertSame($other, $result[1]);
        $this->assertCount(2, $result);
    }

    public function testGetMemberNumberWithBeneficiaryListString(): void
    {
        $membership = $this->createMembership();
        $main = $this->createBeneficiary('Alice', 'Dupont');

        $membership->setMainBeneficiary($main);

        $result = $membership->getMemberNumberWithBeneficiaryListString();

        $this->assertStringContainsString('#42', $result);
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('DUPONT', $result);
    }

    public function testGetMemberNumberWithBeneficiaryListStringMultiple(): void
    {
        $membership = $this->createMembership();
        $main = $this->createBeneficiary('Alice', 'Dupont');
        $other = $this->createBeneficiary('Bob', 'Martin');

        $membership->addBeneficiary($other);
        $other->setMembership($membership);
        $membership->setMainBeneficiary($main);

        $result = $membership->getMemberNumberWithBeneficiaryListString();

        $this->assertStringContainsString('#42', $result);
        $this->assertStringContainsString('&', $result);
    }

    // ── Withdrawn ────────────────────────────────────────────────────

    public function testSetWithdrawnTrue(): void
    {
        $membership = $this->createMembership();
        $user = $this->createMock(User::class);
        $date = new \DateTime('2024-01-15');

        $membership->setWithdrawnDate($date);
        $membership->setWithdrawnBy($user);
        $membership->setWithdrawn(true);

        $this->assertTrue($membership->isWithdrawn());
        $this->assertTrue($membership->getWithdrawn());
        $this->assertSame($date, $membership->getWithdrawnDate());
        $this->assertSame($user, $membership->getWithdrawnBy());
    }

    public function testSetWithdrawnFalseClearsDateAndBy(): void
    {
        $membership = $this->createMembership();
        $user = $this->createMock(User::class);

        $membership->setWithdrawnDate(new \DateTime());
        $membership->setWithdrawnBy($user);
        $membership->setWithdrawn(true);

        $membership->setWithdrawn(false);

        $this->assertFalse($membership->isWithdrawn());
        $this->assertNull($membership->getWithdrawnDate());
        $this->assertNull($membership->getWithdrawnBy());
    }

    // ── Frozen ───────────────────────────────────────────────────────

    public function testSetAndGetFrozen(): void
    {
        $membership = $this->createMembership();

        $membership->setFrozen(true);
        $this->assertTrue($membership->getFrozen());
        $this->assertTrue($membership->isFrozen());

        $membership->setFrozen(false);
        $this->assertFalse($membership->getFrozen());
        $this->assertFalse($membership->isFrozen());
    }

    public function testSetAndGetFrozenChange(): void
    {
        $membership = $this->createMembership();

        $membership->setFrozenChange(true);
        $this->assertTrue($membership->getFrozenChange());

        $membership->setFrozenChange(false);
        $this->assertFalse($membership->getFrozenChange());
    }

    // ── Flying ───────────────────────────────────────────────────────

    public function testSetAndGetFlying(): void
    {
        $membership = $this->createMembership();

        $membership->setFlying(true);
        $this->assertTrue($membership->isFlying());

        $membership->setFlying(false);
        $this->assertFalse($membership->isFlying());
    }

    // ── Registrations ────────────────────────────────────────────────

    public function testAddAndRemoveRegistration(): void
    {
        $membership = $this->createMembership();
        $registration = $this->createMock(Registration::class);

        $membership->addRegistration($registration);
        $this->assertCount(1, $membership->getRegistrations());

        $membership->removeRegistration($registration);
        $this->assertCount(0, $membership->getRegistrations());
    }

    public function testGetLastRegistrationReturnsFirst(): void
    {
        $membership = $this->createMembership();
        $reg1 = $this->createMock(Registration::class);
        $reg2 = $this->createMock(Registration::class);

        $membership->addRegistration($reg1);
        $membership->addRegistration($reg2);

        // registrations are ordered by date DESC in the mapping,
        // so first() in the collection should be the "last" registration
        $this->assertSame($reg1, $membership->getLastRegistration());
    }

    public function testHasValidRegistrationBeforeTrue(): void
    {
        $membership = $this->createMembership();
        $registration = $this->createMock(Registration::class);
        $registration->method('getDate')->willReturn(new \DateTime('2024-01-01'));

        $membership->addRegistration($registration);

        $this->assertTrue($membership->hasValidRegistrationBefore(new \DateTime('2024-06-01')));
    }

    public function testHasValidRegistrationBeforeFalse(): void
    {
        $membership = $this->createMembership();
        $registration = $this->createMock(Registration::class);
        $registration->method('getDate')->willReturn(new \DateTime('2024-06-01'));

        $membership->addRegistration($registration);

        $this->assertFalse($membership->hasValidRegistrationBefore(new \DateTime('2024-01-01')));
    }

    public function testHasValidRegistrationBeforeDefaultsToNow(): void
    {
        $membership = $this->createMembership();
        $registration = $this->createMock(Registration::class);
        $registration->method('getDate')->willReturn(new \DateTime('2020-01-01'));

        $membership->addRegistration($registration);

        $this->assertTrue($membership->hasValidRegistrationBefore(null));
    }

    // ── Time logs ────────────────────────────────────────────────────

    public function testAddAndRemoveTimeLog(): void
    {
        $membership = $this->createMembership();
        $timeLog = $this->createMock(TimeLog::class);

        $membership->addTimeLog($timeLog);
        $this->assertCount(1, $membership->getTimeLogs());

        $membership->removeTimeLog($timeLog);
        $this->assertCount(0, $membership->getTimeLogs());
    }

    public function testGetShiftTimeLogsExcludesSaving(): void
    {
        $membership = $this->createMembership();

        $shiftLog = $this->createMock(TimeLog::class);
        $shiftLog->method('getType')->willReturn(TimeLog::TYPE_SHIFT_VALIDATED);

        $savingLog = $this->createMock(TimeLog::class);
        $savingLog->method('getType')->willReturn(TimeLog::TYPE_SAVING);

        $membership->addTimeLog($shiftLog);
        $membership->addTimeLog($savingLog);

        $shiftLogs = $membership->getShiftTimeLogs();
        $this->assertCount(1, $shiftLogs);
    }

    public function testGetSavingTimeLogsOnlyIncludesSaving(): void
    {
        $membership = $this->createMembership();

        $shiftLog = $this->createMock(TimeLog::class);
        $shiftLog->method('getType')->willReturn(TimeLog::TYPE_SHIFT_VALIDATED);

        $savingLog = $this->createMock(TimeLog::class);
        $savingLog->method('getType')->willReturn(TimeLog::TYPE_SAVING);

        $membership->addTimeLog($shiftLog);
        $membership->addTimeLog($savingLog);

        $savingLogs = $membership->getSavingTimeLogs();
        $this->assertCount(1, $savingLogs);
    }

    public function testGetShiftTimeCount(): void
    {
        $membership = $this->createMembership();

        $log1 = $this->createMock(TimeLog::class);
        $log1->method('getType')->willReturn(TimeLog::TYPE_SHIFT_VALIDATED);
        $log1->method('getTime')->willReturn(90);

        $log2 = $this->createMock(TimeLog::class);
        $log2->method('getType')->willReturn(TimeLog::TYPE_SHIFT_VALIDATED);
        $log2->method('getTime')->willReturn(60);

        $membership->addTimeLog($log1);
        $membership->addTimeLog($log2);

        $this->assertSame(150, $membership->getShiftTimeCount());
    }

    public function testGetShiftTimeCountWithBeforeFilter(): void
    {
        $membership = $this->createMembership();

        $log1 = $this->createMock(TimeLog::class);
        $log1->method('getType')->willReturn(TimeLog::TYPE_SHIFT_VALIDATED);
        $log1->method('getTime')->willReturn(90);
        $log1->method('getCreatedAt')->willReturn(new \DateTime('2024-01-01'));

        $log2 = $this->createMock(TimeLog::class);
        $log2->method('getType')->willReturn(TimeLog::TYPE_SHIFT_VALIDATED);
        $log2->method('getTime')->willReturn(60);
        $log2->method('getCreatedAt')->willReturn(new \DateTime('2024-06-01'));

        $membership->addTimeLog($log1);
        $membership->addTimeLog($log2);

        $this->assertSame(90, $membership->getShiftTimeCount(new \DateTime('2024-03-01')));
    }

    public function testGetSavingTimeCount(): void
    {
        $membership = $this->createMembership();

        $log = $this->createMock(TimeLog::class);
        $log->method('getType')->willReturn(TimeLog::TYPE_SAVING);
        $log->method('getTime')->willReturn(45);

        $membership->addTimeLog($log);

        $this->assertSame(45, $membership->getSavingTimeCount());
    }

    // ── Notes ────────────────────────────────────────────────────────

    public function testAddAndRemoveNote(): void
    {
        $membership = $this->createMembership();

        // notes is not initialized in the constructor (relies on Doctrine)
        $reflection = new \ReflectionClass($membership);
        $prop = $reflection->getProperty('notes');
        $prop->setAccessible(true);
        $prop->setValue($membership, new ArrayCollection());

        $note = $this->createMock(Note::class);

        $membership->addNote($note);
        $this->assertCount(1, $membership->getNotes());

        $membership->removeNote($note);
        $this->assertCount(0, $membership->getNotes());
    }

    // ── Proxies ──────────────────────────────────────────────────────

    public function testAddAndRemoveGivenProxy(): void
    {
        $membership = $this->createMembership();

        // given_proxies is not initialized in the constructor (relies on Doctrine)
        $reflection = new \ReflectionClass($membership);
        $prop = $reflection->getProperty('given_proxies');
        $prop->setAccessible(true);
        $prop->setValue($membership, new ArrayCollection());

        $proxy = $this->createMock(Proxy::class);

        $membership->addGivenProxy($proxy);
        $this->assertCount(1, $membership->getGivenProxies());

        $membership->removeGivenProxy($proxy);
        $this->assertCount(0, $membership->getGivenProxies());
    }

    // ── First shift date ─────────────────────────────────────────────

    public function testSetAndGetFirstShiftDate(): void
    {
        $membership = $this->createMembership();
        $date = new \DateTime('2024-03-15');

        $membership->setFirstShiftDate($date);
        $this->assertSame($date, $membership->getFirstShiftDate());
    }

    // ── CreatedAt lifecycle ──────────────────────────────────────────

    public function testSetCreatedAtValueOnlyOnce(): void
    {
        $membership = $this->createMembership();

        $membership->setCreatedAtValue();
        $first = $membership->getCreatedAt();
        $this->assertInstanceOf(\DateTime::class, $first);

        $membership->setCreatedAtValue();
        $this->assertSame($first, $membership->getCreatedAt());
    }

    // ── TmpToken ─────────────────────────────────────────────────────

    public function testGetTmpTokenIsDeterministic(): void
    {
        $membership = $this->createMembership();

        // Use reflection to set the id
        $reflection = new \ReflectionClass($membership);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($membership, 1);

        $token1 = $membership->getTmpToken('secret');
        $token2 = $membership->getTmpToken('secret');

        $this->assertSame($token1, $token2);
        $this->assertSame(32, strlen($token1)); // md5 hash length
    }

    public function testGetTmpTokenDifferentKeysProduceDifferentTokens(): void
    {
        $membership = $this->createMembership();

        $reflection = new \ReflectionClass($membership);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($membership, 1);

        $token1 = $membership->getTmpToken('key1');
        $token2 = $membership->getTmpToken('key2');

        $this->assertNotSame($token1, $token2);
    }

    // ── Shift exemptions ─────────────────────────────────────────────

    public function testIsCurrentlyExemptedFromShiftsTrue(): void
    {
        $membership = $this->createMembership();

        $exemption = $this->createMock(MembershipShiftExemption::class);
        $exemption->method('isCurrent')->willReturn(true);

        // Use reflection to add the exemption to the collection
        $reflection = new \ReflectionClass($membership);
        $prop = $reflection->getProperty('membershipShiftExemptions');
        $prop->setAccessible(true);
        $prop->setValue($membership, new ArrayCollection([$exemption]));

        $this->assertTrue($membership->isCurrentlyExemptedFromShifts());
    }

    public function testIsCurrentlyExemptedFromShiftsFalse(): void
    {
        $membership = $this->createMembership();

        $exemption = $this->createMock(MembershipShiftExemption::class);
        $exemption->method('isCurrent')->willReturn(false);

        $reflection = new \ReflectionClass($membership);
        $prop = $reflection->getProperty('membershipShiftExemptions');
        $prop->setAccessible(true);
        $prop->setValue($membership, new ArrayCollection([$exemption]));

        $this->assertFalse($membership->isCurrentlyExemptedFromShifts());
    }

    public function testGetCurrentMembershipShiftExemptions(): void
    {
        $membership = $this->createMembership();

        $current = $this->createMock(MembershipShiftExemption::class);
        $current->method('isCurrent')->willReturn(true);

        $expired = $this->createMock(MembershipShiftExemption::class);
        $expired->method('isCurrent')->willReturn(false);

        $reflection = new \ReflectionClass($membership);
        $prop = $reflection->getProperty('membershipShiftExemptions');
        $prop->setAccessible(true);
        $prop->setValue($membership, new ArrayCollection([$current, $expired]));

        $result = $membership->getCurrentMembershipShiftExemptions();
        $this->assertCount(1, $result);
    }
}
