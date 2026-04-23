<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Beneficiary;
use App\Entity\Formation;
use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\ShiftBucket;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class ShiftBucketTest extends TestCase
{
    private function createShift(
        \DateTime $start = null,
        \DateTime $end = null,
        Beneficiary $shifter = null,
        Formation $formation = null
    ): Shift {
        $shift = new Shift();
        $shift->setStart($start ?? new \DateTime('2024-06-15 09:00'));
        $shift->setEnd($end ?? new \DateTime('2024-06-15 12:00'));
        if ($shifter) {
            $shift->setShifter($shifter);
        }
        if ($formation) {
            $shift->setFormation($formation);
        }

        return $shift;
    }

    private function createBucket(array $shifts = []): ShiftBucket
    {
        $bucket = new ShiftBucket();
        foreach ($shifts as $shift) {
            $bucket->addShift($shift);
        }

        return $bucket;
    }

    // ── Constructor ──────────────────────────────────────────────────

    public function testConstructorCreatesEmptyCollection(): void
    {
        $bucket = new ShiftBucket();

        $this->assertInstanceOf(ArrayCollection::class, $bucket->getShifts());
        $this->assertCount(0, $bucket->getShifts());
    }

    // ── addShift / addShifts ─────────────────────────────────────────

    public function testAddShift(): void
    {
        $bucket = new ShiftBucket();
        $shift = $this->createShift();

        $bucket->addShift($shift);

        $this->assertCount(1, $bucket->getShifts());
        $this->assertSame($shift, $bucket->getShifts()->first());
    }

    public function testAddShifts(): void
    {
        $bucket = new ShiftBucket();
        $shift1 = $this->createShift();
        $shift2 = $this->createShift();

        $bucket->addShifts([$shift1, $shift2]);

        $this->assertCount(2, $bucket->getShifts());
    }

    public function testAddShiftsSkipsNonShiftObjects(): void
    {
        $bucket = new ShiftBucket();
        $shift = $this->createShift();

        $bucket->addShifts([$shift, 'not-a-shift', 42]);

        $this->assertCount(1, $bucket->getShifts());
    }

    // ── getFirst ─────────────────────────────────────────────────────

    public function testGetFirstReturnsFirstShift(): void
    {
        $shift1 = $this->createShift();
        $shift2 = $this->createShift();
        $bucket = $this->createBucket([$shift1, $shift2]);

        $this->assertSame($shift1, $bucket->getFirst());
    }

    // ── Delegation to first shift ────────────────────────────────────

    public function testGetJobDelegatesToFirstShift(): void
    {
        $job = $this->createMock(Job::class);
        $shift = $this->createShift();
        $shift->setJob($job);
        $bucket = $this->createBucket([$shift]);

        $this->assertSame($job, $bucket->getJob());
    }

    public function testGetStartDelegatesToFirstShift(): void
    {
        $start = new \DateTime('2024-06-15 09:00');
        $shift = $this->createShift($start);
        $bucket = $this->createBucket([$shift]);

        $this->assertSame($start, $bucket->getStart());
    }

    public function testGetEndDelegatesToFirstShift(): void
    {
        $end = new \DateTime('2024-06-15 12:00');
        $shift = $this->createShift(null, $end);
        $bucket = $this->createBucket([$shift]);

        $this->assertSame($end, $bucket->getEnd());
    }

    public function testGetDurationDelegatesToFirstShift(): void
    {
        $shift = $this->createShift(
            new \DateTime('2024-06-15 09:00'),
            new \DateTime('2024-06-15 12:00')
        );
        $bucket = $this->createBucket([$shift]);

        $this->assertSame(180, $bucket->getDuration());
    }

    public function testGetIntervalCodeDelegatesToFirstShift(): void
    {
        $shift = $this->createShift(
            new \DateTime('2024-06-15 09:30'),
            new \DateTime('2024-06-15 12:00')
        );
        $bucket = $this->createBucket([$shift]);

        $this->assertSame('09-3012-00', $bucket->getIntervalCode());
    }

    // ── getShifterCount ──────────────────────────────────────────────

    public function testGetShifterCountWithNoShifters(): void
    {
        $shift1 = $this->createShift();
        $shift2 = $this->createShift();
        $bucket = $this->createBucket([$shift1, $shift2]);

        $this->assertSame(0, $bucket->getShifterCount());
    }

    public function testGetShifterCountWithSomeShifters(): void
    {
        $beneficiary = $this->createMock(Beneficiary::class);

        $bookedShift = $this->createShift(null, null, $beneficiary);
        $freeShift = $this->createShift();
        $bucket = $this->createBucket([$bookedShift, $freeShift]);

        $this->assertSame(1, $bucket->getShifterCount());
    }

    public function testGetShifterCountAllBooked(): void
    {
        $b1 = $this->createMock(Beneficiary::class);
        $b2 = $this->createMock(Beneficiary::class);

        $shift1 = $this->createShift(null, null, $b1);
        $shift2 = $this->createShift(null, null, $b2);
        $bucket = $this->createBucket([$shift1, $shift2]);

        $this->assertSame(2, $bucket->getShifterCount());
    }

    // ── removeEmptyShift ─────────────────────────────────────────────

    public function testRemoveEmptyShiftRemovesUnbookedWhenMultiple(): void
    {
        $beneficiary = $this->createMock(Beneficiary::class);

        $bookedShift = $this->createShift(null, null, $beneficiary);
        $emptyShift = $this->createShift();
        $bucket = $this->createBucket([$bookedShift, $emptyShift]);

        $bucket->removeEmptyShift();

        $this->assertSame(1, $bucket->getShifts()->count());
        $this->assertSame($bookedShift, $bucket->getShifts()->first());
    }

    public function testRemoveEmptyShiftKeepsSingleEmptyShift(): void
    {
        $emptyShift = $this->createShift();
        $bucket = $this->createBucket([$emptyShift]);

        $bucket->removeEmptyShift();

        // Single shift should not be removed (count <= 1)
        $this->assertSame(1, $bucket->getShifts()->count());
    }

    // ── getSortedShifts ──────────────────────────────────────────────

    public function testGetSortedShiftsReturnsNullWhenEmpty(): void
    {
        $bucket = new ShiftBucket();

        $this->assertNull($bucket->getSortedShifts());
    }

    public function testGetSortedShiftsReturnsCollectionWhenNotEmpty(): void
    {
        $shift = $this->createShift();
        $bucket = $this->createBucket([$shift]);

        $sorted = $bucket->getSortedShifts();
        $this->assertInstanceOf(ArrayCollection::class, $sorted);
        $this->assertCount(1, $sorted);
    }

    // ── canBookInterval ──────────────────────────────────────────────

    public function testCanBookIntervalTrueWhenNoPriorBooking(): void
    {
        $start = new \DateTime('2024-06-15 09:00');
        $end = new \DateTime('2024-06-15 12:00');

        $shift = $this->createShift($start, $end);
        $bucket = $this->createBucket([$shift]);

        $beneficiary = $this->createMock(Beneficiary::class);
        $beneficiary->method('getShifts')->willReturn(new ArrayCollection());
        $beneficiary->method('getReservedShifts')->willReturn(new ArrayCollection());

        $this->assertTrue($bucket->canBookInterval($beneficiary));
    }

    public function testCanBookIntervalFalseWhenAlreadyBooked(): void
    {
        $start = new \DateTime('2024-06-15 09:00');
        $end = new \DateTime('2024-06-15 12:00');

        $shift = $this->createShift($start, $end);
        $bucket = $this->createBucket([$shift]);

        $existingShift = $this->createShift(clone $start, clone $end);

        $beneficiary = $this->createMock(Beneficiary::class);
        $beneficiary->method('getShifts')->willReturn(new ArrayCollection([$existingShift]));
        $beneficiary->method('getReservedShifts')->willReturn(new ArrayCollection());

        $this->assertFalse($bucket->canBookInterval($beneficiary));
    }

    public function testCanBookIntervalFalseWhenAlreadyReserved(): void
    {
        $start = new \DateTime('2024-06-15 09:00');
        $end = new \DateTime('2024-06-15 12:00');

        $shift = $this->createShift($start, $end);
        $bucket = $this->createBucket([$shift]);

        $reservedShift = $this->createShift(clone $start, clone $end);

        $beneficiary = $this->createMock(Beneficiary::class);
        $beneficiary->method('getShifts')->willReturn(new ArrayCollection());
        $beneficiary->method('getReservedShifts')->willReturn(new ArrayCollection([$reservedShift]));

        $this->assertFalse($bucket->canBookInterval($beneficiary));
    }

    public function testCanBookIntervalTrueWhenDifferentInterval(): void
    {
        $start = new \DateTime('2024-06-15 09:00');
        $end = new \DateTime('2024-06-15 12:00');

        $shift = $this->createShift($start, $end);
        $bucket = $this->createBucket([$shift]);

        $differentShift = $this->createShift(
            new \DateTime('2024-06-15 14:00'),
            new \DateTime('2024-06-15 17:00')
        );

        $beneficiary = $this->createMock(Beneficiary::class);
        $beneficiary->method('getShifts')->willReturn(new ArrayCollection([$differentShift]));
        $beneficiary->method('getReservedShifts')->willReturn(new ArrayCollection());

        $this->assertTrue($bucket->canBookInterval($beneficiary));
    }

    // ── compareShifts (static) ───────────────────────────────────────

    public function testCompareShiftsNoBeneficiaryBothFreeNoFormation(): void
    {
        $a = $this->createShift();
        $b = $this->createShift();

        // Both free, no formation -> 0
        $this->assertSame(0, ShiftBucket::compareShifts($a, $b));
    }

    public function testCompareShiftsNoBeneficiaryAFreeNotB(): void
    {
        $a = $this->createShift();
        $beneficiary = $this->createMock(Beneficiary::class);
        $b = $this->createShift(null, null, $beneficiary);
        $b->setBookedTime(new \DateTime());

        // a free, b booked -> a comes after (1)
        $result = ShiftBucket::compareShifts($a, $b);
        $this->assertSame(1, $result);
    }

    public function testCompareShiftsNoBeneficiaryABookedBFree(): void
    {
        $beneficiary = $this->createMock(Beneficiary::class);
        $a = $this->createShift(null, null, $beneficiary);
        $a->setBookedTime(new \DateTime());
        $b = $this->createShift();

        // a booked, b free -> a comes before (-1)
        $result = ShiftBucket::compareShifts($a, $b);
        $this->assertSame(-1, $result);
    }

    // ── shiftIntersectFormations (static) ────────────────────────────

    public function testShiftIntersectFormationsTrueWhenMatching(): void
    {
        $formation = $this->createMock(Formation::class);
        $formation->method('getId')->willReturn(1);

        $shift = $this->createShift(null, null, null, $formation);
        $shifts = new ArrayCollection([$shift]);

        $result = ShiftBucket::shiftIntersectFormations($shifts, [$formation]);
        $this->assertTrue($result);
    }

    public function testShiftIntersectFormationsFalseWhenNoMatch(): void
    {
        $f1 = $this->createMock(Formation::class);
        $f1->method('getId')->willReturn(1);

        $f2 = $this->createMock(Formation::class);
        $f2->method('getId')->willReturn(2);

        $shift = $this->createShift(null, null, null, $f1);
        $shifts = new ArrayCollection([$shift]);

        $result = ShiftBucket::shiftIntersectFormations($shifts, [$f2]);
        $this->assertFalse($result);
    }

    public function testShiftIntersectFormationsFalseWhenNoFormation(): void
    {
        $formation = $this->createMock(Formation::class);
        $formation->method('getId')->willReturn(1);

        $shift = $this->createShift();
        $shifts = new ArrayCollection([$shift]);

        $result = ShiftBucket::shiftIntersectFormations($shifts, [$formation]);
        $this->assertFalse($result);
    }

    // ── filterByFormations (static) ──────────────────────────────────

    public function testFilterByFormationsReturnsFormationShiftsWhenIntersection(): void
    {
        $formation = $this->createMock(Formation::class);
        $formation->method('getId')->willReturn(1);

        $withFormation = $this->createShift(null, null, null, $formation);
        $withoutFormation = $this->createShift();
        $shifts = new ArrayCollection([$withFormation, $withoutFormation]);

        $result = ShiftBucket::filterByFormations($shifts, [$formation]);

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($withFormation));
    }

    public function testFilterByFormationsReturnsNonFormationShiftsWhenNoIntersection(): void
    {
        $f1 = $this->createMock(Formation::class);
        $f1->method('getId')->willReturn(1);

        $f2 = $this->createMock(Formation::class);
        $f2->method('getId')->willReturn(2);

        $shiftWithF1 = $this->createShift(null, null, null, $f1);
        $shiftWithout = $this->createShift();
        $shifts = new ArrayCollection([$shiftWithF1, $shiftWithout]);

        // Filter by f2, which doesn't match any shift
        $result = ShiftBucket::filterByFormations($shifts, [$f2]);

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($shiftWithout));
    }

    // ── createShiftFilterCallback (static) ───────────────────────────

    public function testCreateShiftFilterCallbackWithFormations(): void
    {
        $formation = $this->createMock(Formation::class);
        $shiftWithFormation = $this->createShift(null, null, null, $formation);
        $shiftWithout = $this->createShift();

        $callback = ShiftBucket::createShiftFilterCallback(true);

        $this->assertTrue($callback($shiftWithFormation));
        $this->assertNull($callback($shiftWithout));
    }

    public function testCreateShiftFilterCallbackWithoutFormations(): void
    {
        $formation = $this->createMock(Formation::class);
        $shiftWithFormation = $this->createShift(null, null, null, $formation);
        $shiftWithout = $this->createShift();

        $callback = ShiftBucket::createShiftFilterCallback(false);

        $this->assertNull($callback($shiftWithFormation));
        $this->assertTrue($callback($shiftWithout));
    }
}
