<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Beneficiary;
use App\Entity\Formation;
use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\TimeLog;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class ShiftTest extends TestCase
{
    private function createShift(\DateTime $start = null, \DateTime $end = null): Shift
    {
        $shift = new Shift();
        $shift->setStart($start ?? new \DateTime('2024-06-15 09:00'));
        $shift->setEnd($end ?? new \DateTime('2024-06-15 12:00'));

        return $shift;
    }

    // ── Constructor ──────────────────────────────────────────────────

    public function testConstructorDefaultsWasCarriedOutToFalse(): void
    {
        $shift = new Shift();

        $this->assertFalse($shift->getWasCarriedOut());
    }

    // ── Start / End ──────────────────────────────────────────────────

    public function testSetAndGetStart(): void
    {
        $shift = new Shift();
        $start = new \DateTime('2024-06-15 09:00');

        $result = $shift->setStart($start);
        $this->assertSame($start, $shift->getStart());
        $this->assertSame($shift, $result);
    }

    public function testSetAndGetEnd(): void
    {
        $shift = new Shift();
        $end = new \DateTime('2024-06-15 12:00');

        $result = $shift->setEnd($end);
        $this->assertSame($end, $shift->getEnd());
        $this->assertSame($shift, $result);
    }

    // ── Duration ─────────────────────────────────────────────────────

    public function testGetDuration3Hours(): void
    {
        $shift = $this->createShift(
            new \DateTime('2024-06-15 09:00'),
            new \DateTime('2024-06-15 12:00')
        );

        $this->assertSame(180, $shift->getDuration());
    }

    public function testGetDuration90Minutes(): void
    {
        $shift = $this->createShift(
            new \DateTime('2024-06-15 09:00'),
            new \DateTime('2024-06-15 10:30')
        );

        $this->assertSame(90, $shift->getDuration());
    }

    // ── Interval Code ────────────────────────────────────────────────

    public function testGetIntervalCode(): void
    {
        $shift = $this->createShift(
            new \DateTime('2024-06-15 09:30'),
            new \DateTime('2024-06-15 12:00')
        );

        $this->assertSame('09-3012-00', $shift->getIntervalCode());
    }

    // ── Temporal checks ──────────────────────────────────────────────

    public function testGetIsPastTrue(): void
    {
        $shift = $this->createShift(
            new \DateTime('-3 days'),
            new \DateTime('-2 days')
        );

        $this->assertTrue($shift->getIsPast());
    }

    public function testGetIsPastFalse(): void
    {
        $shift = $this->createShift(
            new \DateTime('+1 day'),
            new \DateTime('+2 days')
        );

        $this->assertFalse($shift->getIsPast());
    }

    public function testGetIsCurrentTrue(): void
    {
        $shift = $this->createShift(
            new \DateTime('-1 hour'),
            new \DateTime('+1 hour')
        );

        $this->assertTrue($shift->getIsCurrent());
    }

    public function testGetIsCurrentFalseWhenPast(): void
    {
        $shift = $this->createShift(
            new \DateTime('-3 hours'),
            new \DateTime('-1 hour')
        );

        $this->assertFalse($shift->getIsCurrent());
    }

    public function testGetIsCurrentFalseWhenFuture(): void
    {
        $shift = $this->createShift(
            new \DateTime('+1 hour'),
            new \DateTime('+3 hours')
        );

        $this->assertFalse($shift->getIsCurrent());
    }

    public function testGetIsPastOrCurrentTrueWhenPast(): void
    {
        $shift = $this->createShift(
            new \DateTime('-3 days'),
            new \DateTime('-2 days')
        );

        $this->assertTrue($shift->getIsPastOrCurrent());
    }

    public function testGetIsPastOrCurrentTrueWhenCurrent(): void
    {
        $shift = $this->createShift(
            new \DateTime('-1 hour'),
            new \DateTime('+1 hour')
        );

        $this->assertTrue($shift->getIsPastOrCurrent());
    }

    public function testGetIsPastOrCurrentFalseWhenFuture(): void
    {
        $shift = $this->createShift(
            new \DateTime('+1 day'),
            new \DateTime('+2 days')
        );

        $this->assertFalse($shift->getIsPastOrCurrent());
    }

    public function testGetIsFutureTrue(): void
    {
        $shift = $this->createShift(
            new \DateTime('+1 day'),
            new \DateTime('+2 days')
        );

        $this->assertTrue($shift->getIsFuture());
    }

    public function testGetIsFutureFalseWhenPast(): void
    {
        $shift = $this->createShift(
            new \DateTime('-3 days'),
            new \DateTime('-2 days')
        );

        $this->assertFalse($shift->getIsFuture());
    }

    public function testGetIsUpcomingTrue(): void
    {
        $shift = $this->createShift(
            new \DateTime('+1 day'),
            new \DateTime('+1 day 3 hours')
        );

        $this->assertTrue($shift->getIsUpcoming());
    }

    public function testGetIsUpcomingFalseWhenTooFar(): void
    {
        $shift = $this->createShift(
            new \DateTime('+10 days'),
            new \DateTime('+10 days 3 hours')
        );

        $this->assertFalse($shift->getIsUpcoming());
    }

    public function testGetIsUpcomingFalseWhenPast(): void
    {
        $shift = $this->createShift(
            new \DateTime('-3 days'),
            new \DateTime('-2 days')
        );

        $this->assertFalse($shift->getIsUpcoming());
    }

    public function testIsBeforeTrue(): void
    {
        $shift = $this->createShift(
            new \DateTime('+1 day'),
            new \DateTime('+1 day 3 hours')
        );

        $this->assertTrue($shift->isBefore('5 days'));
    }

    public function testIsBeforeFalse(): void
    {
        $shift = $this->createShift(
            new \DateTime('+10 days'),
            new \DateTime('+10 days 3 hours')
        );

        $this->assertFalse($shift->isBefore('5 days'));
    }

    public function testIsBeforeFalseWhenPast(): void
    {
        $shift = $this->createShift(
            new \DateTime('-3 days'),
            new \DateTime('-2 days')
        );

        $this->assertFalse($shift->isBefore('5 days'));
    }

    // ── Booking ──────────────────────────────────────────────────────

    public function testSetAndGetBooker(): void
    {
        $shift = $this->createShift();
        $user = $this->createMock(User::class);

        $shift->setBooker($user);
        $this->assertSame($user, $shift->getBooker());
    }

    public function testSetAndGetBookedTime(): void
    {
        $shift = $this->createShift();
        $time = new \DateTime('2024-06-10 14:00');

        $shift->setBookedTime($time);
        $this->assertSame($time, $shift->getBookedTime());
    }

    public function testSetAndGetShifter(): void
    {
        $shift = $this->createShift();
        $beneficiary = $this->createMock(Beneficiary::class);

        $shift->setShifter($beneficiary);
        $this->assertSame($beneficiary, $shift->getShifter());
    }

    // ── Free ─────────────────────────────────────────────────────────

    public function testFreeClearsBookingData(): void
    {
        $shift = $this->createShift();
        $user = $this->createMock(User::class);
        $beneficiary = $this->createMock(Beneficiary::class);

        $shift->setBooker($user);
        $shift->setBookedTime(new \DateTime());
        $shift->setShifter($beneficiary);
        $shift->setFixe(true);

        $result = $shift->free();

        $this->assertNull($shift->getBooker());
        $this->assertNull($shift->getBookedTime());
        $this->assertNull($shift->getShifter());
        $this->assertFalse($shift->isFixe());
        $this->assertSame($shift, $result);
    }

    // ── Validate / Invalidate participation ──────────────────────────

    public function testValidateShiftParticipation(): void
    {
        $shift = $this->createShift();

        $result = $shift->validateShiftParticipation();

        $this->assertTrue($shift->getWasCarriedOut());
        $this->assertSame($shift, $result);
    }

    public function testInvalidateShiftParticipation(): void
    {
        $shift = $this->createShift();
        $shift->setWasCarriedOut(true);

        $result = $shift->invalidateShiftParticipation();

        $this->assertFalse($shift->getWasCarriedOut());
        $this->assertSame($shift, $result);
    }

    // ── Formation / Job ──────────────────────────────────────────────

    public function testSetAndGetFormation(): void
    {
        $shift = $this->createShift();
        $formation = $this->createMock(Formation::class);

        $shift->setFormation($formation);
        $this->assertSame($formation, $shift->getFormation());

        $shift->setFormation(null);
        $this->assertNull($shift->getFormation());
    }

    public function testSetAndGetJob(): void
    {
        $shift = $this->createShift();
        $job = $this->createMock(Job::class);

        $shift->setJob($job);
        $this->assertSame($job, $shift->getJob());
    }

    // ── Locked / Fixe ────────────────────────────────────────────────

    public function testSetAndGetLocked(): void
    {
        $shift = $this->createShift();

        $shift->setLocked(true);
        $this->assertTrue($shift->isLocked());

        $shift->setLocked(false);
        $this->assertFalse($shift->isLocked());
    }

    public function testSetAndGetFixe(): void
    {
        $shift = $this->createShift();

        $shift->setFixe(true);
        $this->assertTrue($shift->isFixe());

        $shift->setFixe(false);
        $this->assertFalse($shift->isFixe());
    }

    // ── LastShifter ──────────────────────────────────────────────────

    public function testSetAndGetLastShifter(): void
    {
        $shift = $this->createShift();
        $beneficiary = $this->createMock(Beneficiary::class);

        $shift->setLastShifter($beneficiary);
        $this->assertSame($beneficiary, $shift->getLastShifter());

        $shift->setLastShifter(null);
        $this->assertNull($shift->getLastShifter());
    }

    // ── TimeLogs ─────────────────────────────────────────────────────

    public function testAddAndRemoveTimeLog(): void
    {
        $shift = $this->createShift();

        // Use reflection to init timeLogs (normally handled by Doctrine)
        $reflection = new \ReflectionClass($shift);
        $prop = $reflection->getProperty('timeLogs');
        $prop->setAccessible(true);
        $prop->setValue($shift, new ArrayCollection());

        $timeLog = $this->createMock(TimeLog::class);

        $shift->addTimeLog($timeLog);
        $this->assertCount(1, $shift->getTimeLogs());

        $shift->removeTimeLog($timeLog);
        $this->assertCount(0, $shift->getTimeLogs());
    }

    // ── isFirstByShifter ─────────────────────────────────────────────

    public function testIsFirstByShifterWhenNoShifter(): void
    {
        $shift = $this->createShift();

        $this->assertFalse($shift->isFirstByShifter());
    }

    public function testIsFirstByShifterTrue(): void
    {
        $shift = $this->createShift();
        $beneficiary = $this->createMock(Beneficiary::class);

        $shifts = new ArrayCollection([$shift]);
        $beneficiary->method('getShifts')->willReturn($shifts);

        $shift->setShifter($beneficiary);

        // The shift is the last (and only) in the collection -> first ever
        $this->assertTrue($shift->isFirstByShifter());
    }

    public function testIsFirstByShifterFalse(): void
    {
        $shift1 = $this->createShift(new \DateTime('+1 day'), new \DateTime('+1 day 3 hours'));
        $shift2 = $this->createShift(new \DateTime('+2 days'), new \DateTime('+2 days 3 hours'));
        $beneficiary = $this->createMock(Beneficiary::class);

        // shifts ordered by start DESC: shift2 first, shift1 last
        $shifts = new ArrayCollection([$shift2, $shift1]);
        $beneficiary->method('getShifts')->willReturn($shifts);

        $shift1->setShifter($beneficiary);
        $shift2->setShifter($beneficiary);

        // shift2 is NOT the last in the collection (shift1 is) -> not first
        $this->assertFalse($shift2->isFirstByShifter());
    }

    // ── TmpToken ─────────────────────────────────────────────────────

    public function testGetTmpTokenIsDeterministic(): void
    {
        $shift = $this->createShift(
            new \DateTime('2024-06-15 09:00'),
            new \DateTime('2024-06-15 12:00')
        );

        $reflection = new \ReflectionClass($shift);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($shift, 1);

        $token1 = $shift->getTmpToken('secret');
        $token2 = $shift->getTmpToken('secret');

        $this->assertSame($token1, $token2);
        $this->assertSame(32, strlen($token1));
    }

    public function testGetTmpTokenDifferentKeysProduceDifferentTokens(): void
    {
        $shift = $this->createShift(
            new \DateTime('2024-06-15 09:00'),
            new \DateTime('2024-06-15 12:00')
        );

        $reflection = new \ReflectionClass($shift);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($shift, 1);

        $this->assertNotSame($shift->getTmpToken('key1'), $shift->getTmpToken('key2'));
    }

    // ── Display methods ──────────────────────────────────────────────

    public function testGetDisplayDateSeperateTime(): void
    {
        $shift = $this->createShift(
            new \DateTime('2024-07-22 09:30'),
            new \DateTime('2024-07-22 12:30')
        );

        $expected = '22/07/2024 - 9h30 à 12h30';
        $this->assertSame($expected, $shift->getDisplayDateSeperateTime());
    }

    public function testGetDisplayDateWithTime(): void
    {
        $shift = $this->createShift(
            new \DateTime('2024-07-22 09:30'),
            new \DateTime('2024-07-22 12:30')
        );

        $expected = '22/07/2024 de 9h30 à 12h30';
        $this->assertSame($expected, $shift->getDisplayDateWithTime());
    }

    // ── createdAt lifecycle ──────────────────────────────────────────

    public function testSetCreatedAtValueOnlyOnce(): void
    {
        $shift = $this->createShift();

        $shift->setCreatedAtValue();
        $first = $shift->getCreatedAt();
        $this->assertInstanceOf(\DateTime::class, $first);

        $shift->setCreatedAtValue();
        $this->assertSame($first, $shift->getCreatedAt());
    }
}
