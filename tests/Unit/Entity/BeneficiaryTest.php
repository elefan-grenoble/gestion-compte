<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Beneficiary;
use App\Entity\Commission;
use App\Entity\Formation;
use App\Entity\Membership;
use App\Entity\Shift;
use App\Entity\SwipeCard;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class BeneficiaryTest extends TestCase
{
    private function createBeneficiary(
        string $firstname = 'alice',
        string $lastname = 'dupont'
    ): Beneficiary {
        $beneficiary = new Beneficiary();
        $beneficiary->setFirstname($firstname);
        $beneficiary->setLastname($lastname);

        return $beneficiary;
    }

    // ── Constructor ──────────────────────────────────────────────────

    public function testConstructorInitializesCollections(): void
    {
        $beneficiary = new Beneficiary();

        $this->assertInstanceOf(ArrayCollection::class, $beneficiary->getCommissions());
        $this->assertInstanceOf(ArrayCollection::class, $beneficiary->getFormations());
        $this->assertInstanceOf(ArrayCollection::class, $beneficiary->getShifts());
    }

    // ── Firstname / Lastname formatting ──────────────────────────────

    public function testGetFirstnameIsUcfirstStrtolower(): void
    {
        $b = $this->createBeneficiary('ALICE', 'dupont');

        $this->assertSame('Alice', $b->getFirstname());
    }

    public function testGetFirstnameHandlesMixedCase(): void
    {
        $b = $this->createBeneficiary('aLiCe', 'dupont');

        $this->assertSame('Alice', $b->getFirstname());
    }

    public function testGetLastnameIsStrtoupper(): void
    {
        $b = $this->createBeneficiary('alice', 'dupont');

        $this->assertSame('DUPONT', $b->getLastname());
    }

    public function testGetLastnameAlreadyUpper(): void
    {
        $b = $this->createBeneficiary('alice', 'MARTIN');

        $this->assertSame('MARTIN', $b->getLastname());
    }

    // ── Display names ────────────────────────────────────────────────

    public function testGetDisplayName(): void
    {
        $b = $this->createBeneficiary('alice', 'dupont');

        $this->assertSame('Alice DUPONT', $b->getDisplayName());
    }

    public function testGetDisplayNameWithMemberNumber(): void
    {
        $b = $this->createBeneficiary('alice', 'dupont');
        $membership = new Membership();
        $membership->setMemberNumber(42);
        $b->setMembership($membership);
        $membership->setMainBeneficiary($b);

        $this->assertSame('#42 Alice DUPONT', $b->getDisplayNameWithMemberNumber());
    }

    public function testGetPublicDisplayName(): void
    {
        $b = $this->createBeneficiary('alice', 'dupont');

        $this->assertSame('Alice D', $b->getPublicDisplayName());
    }

    public function testGetPublicDisplayNameWithMemberNumber(): void
    {
        $b = $this->createBeneficiary('alice', 'dupont');
        $membership = new Membership();
        $membership->setMemberNumber(42);
        $b->setMembership($membership);
        $membership->setMainBeneficiary($b);

        $this->assertSame('#42 Alice D', $b->getPublicDisplayNameWithMemberNumber());
    }

    public function testToStringReturnsDisplayNameWithMemberNumber(): void
    {
        $b = $this->createBeneficiary('alice', 'dupont');
        $membership = new Membership();
        $membership->setMemberNumber(42);
        $b->setMembership($membership);
        $membership->setMainBeneficiary($b);

        $this->assertSame('#42 Alice DUPONT', (string) $b);
    }

    // ── Member number ────────────────────────────────────────────────

    public function testGetMemberNumberDelegatesToMembership(): void
    {
        $b = $this->createBeneficiary();
        $membership = new Membership();
        $membership->setMemberNumber(99);
        $b->setMembership($membership);

        $this->assertSame(99, $b->getMemberNumber());
    }

    public function testGetMemberNumberReturnsNullWithoutMembership(): void
    {
        $b = $this->createBeneficiary();

        $this->assertNull($b->getMemberNumber());
    }

    // ── isMain ───────────────────────────────────────────────────────

    public function testIsMainTrue(): void
    {
        $b = $this->createBeneficiary();
        $membership = new Membership();
        $membership->setMainBeneficiary($b);

        $this->assertTrue($b->isMain());
    }

    public function testIsMainFalse(): void
    {
        $main = $this->createBeneficiary('Alice', 'Dupont');
        $other = $this->createBeneficiary('Bob', 'Martin');
        $membership = new Membership();
        $membership->setMainBeneficiary($main);
        $membership->addBeneficiary($other);
        $other->setMembership($membership);

        $this->assertFalse($other->isMain());
    }

    // ── Flying ───────────────────────────────────────────────────────

    public function testSetAndGetFlying(): void
    {
        $b = $this->createBeneficiary();

        $b->setFlying(true);
        $this->assertTrue($b->isFlying());

        $b->setFlying(false);
        $this->assertFalse($b->isFlying());
    }

    // ── isNew ────────────────────────────────────────────────────────

    public function testIsNewTrueWhenFewShifts(): void
    {
        $b = $this->createBeneficiary();

        // 0 shifts -> new
        $this->assertTrue($b->isNew());
    }

    public function testIsNewTrueWhenExactlyThresholdShifts(): void
    {
        $b = $this->createBeneficiary();

        for ($i = 0; $i < 3; $i++) {
            $shift = $this->createMock(Shift::class);
            $b->addShift($shift);
        }

        // exactly 3 shifts -> still new (<= 3)
        $this->assertTrue($b->isNew());
    }

    public function testIsNewFalseWhenAboveThreshold(): void
    {
        $b = $this->createBeneficiary();

        for ($i = 0; $i < 4; $i++) {
            $shift = $this->createMock(Shift::class);
            $b->addShift($shift);
        }

        // 4 shifts -> not new
        $this->assertFalse($b->isNew());
    }

    // ── Commissions ──────────────────────────────────────────────────

    public function testAddAndRemoveCommission(): void
    {
        $b = $this->createBeneficiary();
        $commission = $this->createMock(Commission::class);

        $b->addCommission($commission);
        $this->assertCount(1, $b->getCommissions());

        $b->removeCommission($commission);
        $this->assertCount(0, $b->getCommissions());
    }

    public function testGetOwnedCommissions(): void
    {
        $b = $this->createBeneficiary();

        $ownedCommission = $this->createMock(Commission::class);
        $ownedCommission->method('getOwners')
            ->willReturn(new ArrayCollection([$b]));

        $otherCommission = $this->createMock(Commission::class);
        $otherCommission->method('getOwners')
            ->willReturn(new ArrayCollection());

        $b->addCommission($ownedCommission);
        $b->addCommission($otherCommission);

        $owned = $b->getOwnedCommissions();
        $this->assertCount(1, $owned);
    }

    // ── Formations ───────────────────────────────────────────────────

    public function testAddAndRemoveFormation(): void
    {
        $b = $this->createBeneficiary();
        $formation = $this->createMock(Formation::class);

        $b->addFormation($formation);
        $this->assertCount(1, $b->getFormations());

        $b->removeFormation($formation);
        $this->assertCount(0, $b->getFormations());
    }

    // ── Shifts ───────────────────────────────────────────────────────

    public function testAddAndRemoveShift(): void
    {
        $b = $this->createBeneficiary();
        $shift = $this->createMock(Shift::class);

        $b->addShift($shift);
        $this->assertCount(1, $b->getShifts());

        $b->removeShift($shift);
        $this->assertCount(0, $b->getShifts());
    }

    // ── SwipeCards ───────────────────────────────────────────────────

    public function testAddAndRemoveSwipeCard(): void
    {
        $b = $this->createBeneficiary();

        // swipe_cards is not initialized in constructor, init via reflection
        $reflection = new \ReflectionClass($b);
        $prop = $reflection->getProperty('swipe_cards');
        $prop->setAccessible(true);
        $prop->setValue($b, new ArrayCollection());

        $card = $this->createMock(SwipeCard::class);

        $b->addSwipeCard($card);
        $this->assertCount(1, $b->getSwipeCards());

        $b->removeSwipeCard($card);
        $this->assertCount(0, $b->getSwipeCards());
    }

    public function testGetEnabledSwipeCards(): void
    {
        $b = $this->createBeneficiary();

        $enabledCard = $this->createMock(SwipeCard::class);
        $enabledCard->method('getEnable')->willReturn(true);

        $disabledCard = $this->createMock(SwipeCard::class);
        $disabledCard->method('getEnable')->willReturn(false);

        $reflection = new \ReflectionClass($b);
        $prop = $reflection->getProperty('swipe_cards');
        $prop->setAccessible(true);
        $prop->setValue($b, new ArrayCollection([$enabledCard, $disabledCard]));

        $enabled = $b->getEnabledSwipeCards();
        $this->assertCount(1, $enabled);
    }

    // ── User / Email ─────────────────────────────────────────────────

    public function testSetAndGetUser(): void
    {
        $b = $this->createBeneficiary();
        $user = $this->createMock(User::class);

        $b->setUser($user);
        $this->assertSame($user, $b->getUser());
    }

    public function testGetEmailDelegatesToUser(): void
    {
        $b = $this->createBeneficiary();
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('alice@example.com');
        $b->setUser($user);

        $this->assertSame('alice@example.com', $b->getEmail());
    }

    public function testGetEmailReturnsNullWithoutUser(): void
    {
        $b = $this->createBeneficiary();

        $this->assertNull($b->getEmail());
    }

    // ── Phone ────────────────────────────────────────────────────────

    public function testSetAndGetPhone(): void
    {
        $b = $this->createBeneficiary();

        $b->setPhone('0612345678');
        $this->assertSame('0612345678', $b->getPhone());
    }

    // ── OpenId ───────────────────────────────────────────────────────

    public function testSetAndGetOpenId(): void
    {
        $b = $this->createBeneficiary();

        $result = $b->setOpenId('oidc-id-123');
        $this->assertSame('oidc-id-123', $b->getOpenId());
        $this->assertSame($b, $result);

        $b->setOpenId(null);
        $this->assertNull($b->getOpenId());
    }

    public function testSetAndGetOpenIdMemberNumber(): void
    {
        $b = $this->createBeneficiary();

        $result = $b->setOpenIdMemberNumber('42');
        $this->assertSame('42', $b->getOpenIdMemberNumber());
        $this->assertSame($b, $result);
    }

    // ── Membership ───────────────────────────────────────────────────

    public function testSetAndGetMembership(): void
    {
        $b = $this->createBeneficiary();
        $membership = new Membership();

        $b->setMembership($membership);
        $this->assertSame($membership, $b->getMembership());
    }

    // ── CreatedAt lifecycle ──────────────────────────────────────────

    public function testSetCreatedAtValueOnlyOnce(): void
    {
        $b = $this->createBeneficiary();

        $b->setCreatedAtValue();
        $first = $b->getCreatedAt();
        $this->assertInstanceOf(\DateTime::class, $first);

        $b->setCreatedAtValue();
        $this->assertSame($first, $b->getCreatedAt());
    }
}
