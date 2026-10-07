<?php

namespace App\Tests\Functional\Command;

use App\Entity\Beneficiary;
use App\Entity\Shift;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;

/**
 * app:shift:free releases the shifts that app:shift:generate reserved to
 * their former shifter and that nobody accepted: they are open to everyone
 * again.
 *
 * @internal
 */
class FreeReservedShiftsCommandTest extends CommandTestCase
{
    private const DATE = '2026-03-10';

    public function testFreesTheShiftsReservedOnTheGivenDay(): void
    {
        $beneficiary = $this->aBeneficiary();
        $morning = $this->aReservedShift('2026-03-10 09:00', $beneficiary);
        $evening = $this->aReservedShift('2026-03-10 23:30', $beneficiary);

        $tester = $this->runCommand('app:shift:free', ['date' => self::DATE]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertNull($this->reload($morning)->getLastShifter());
        $this->assertNull($this->reload($evening)->getLastShifter());
        $this->assertStringContainsString('2 créneaux libérés', $tester->getDisplay());
    }

    public function testSaysSoWhenASingleShiftIsFreed(): void
    {
        $this->aReservedShift('2026-03-10 09:00', $this->aBeneficiary());

        $tester = $this->runCommand('app:shift:free', ['date' => self::DATE]);

        $this->assertStringContainsString('1 créneau libéré', $tester->getDisplay());
        $this->assertStringNotContainsString('créneaux libérés', $tester->getDisplay());
    }

    public function testLeavesTheShiftsReservedOnOtherDays(): void
    {
        $beneficiary = $this->aBeneficiary();
        $lastMinuteOfTheDayBefore = $this->aReservedShift('2026-03-09 23:59', $beneficiary);
        $firstMinuteOfTheDayAfter = $this->aReservedShift('2026-03-11 00:00', $beneficiary);
        $nextWeek = $this->aReservedShift('2026-03-17 09:00', $beneficiary);

        $tester = $this->runCommand('app:shift:free', ['date' => self::DATE]);

        $this->assertStringContainsString('0 créneau libéré', $tester->getDisplay());
        foreach ([$lastMinuteOfTheDayBefore, $firstMinuteOfTheDayAfter, $nextWeek] as $shift) {
            $this->assertSame($beneficiary->getId(), $this->reload($shift)->getLastShifter()->getId());
        }
    }

    public function testLeavesTheShiftsNobodyHadReserved(): void
    {
        $free = ShiftBuilder::aShift()->startingAt(new \DateTime('2026-03-10 09:00'))->build();
        $booked = ShiftBuilder::aShift()->startingAt(new \DateTime('2026-03-10 14:00'))->bookedBy($this->aBeneficiary())->build();
        static::persist($free->getJob(), $free, $booked->getJob(), $booked);

        $tester = $this->runCommand('app:shift:free', ['date' => self::DATE]);

        $this->assertStringContainsString('0 créneau libéré', $tester->getDisplay());
        $this->assertNull($this->reload($free)->getShifter());
        $this->assertNotNull($this->reload($booked)->getShifter(), 'a booked shift that nobody had reserved stays booked');
    }

    public function testKeepsTheShifterOfAShiftBookedByOthersInTheMeantime(): void
    {
        $former = $this->aBeneficiary();
        $other = $this->aBeneficiary();
        $shift = $this->aReservedShift('2026-03-10 09:00', $former);
        $shift->setShifter($other);
        $shift->setBooker($other->getUser());
        $shift->setBookedTime(new \DateTime());
        static::persist($shift);

        $this->runCommand('app:shift:free', ['date' => self::DATE]);

        $shift = $this->reload($shift);
        $this->assertNull($shift->getLastShifter());
        $this->assertSame($other->getId(), $shift->getShifter()->getId());
    }

    public function testRunningTwiceChangesNothing(): void
    {
        $this->aReservedShift('2026-03-10 09:00', $this->aBeneficiary());

        $this->runCommand('app:shift:free', ['date' => self::DATE]);
        $tester = $this->runCommand('app:shift:free', ['date' => self::DATE]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('0 créneau libéré', $tester->getDisplay());
    }

    public function testRefusesToRunWhenShiftsAreNotReservedToPriorShifters(): void
    {
        $this->withEnv('RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER', 'false');
        $beneficiary = $this->aBeneficiary();
        $shift = $this->aReservedShift('2026-03-10 09:00', $beneficiary);

        $tester = $this->runCommand('app:shift:free', ['date' => self::DATE]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('reserve_new_shift_to_prior_shifter parameter must be true', $tester->getDisplay());
        $this->assertSame($beneficiary->getId(), $this->reload($shift)->getLastShifter()->getId());
    }

    /**
     * @dataProvider wrongDateProvider
     */
    public function testRejectsAMalformedDate(string $date): void
    {
        $beneficiary = $this->aBeneficiary();
        $shift = $this->aReservedShift('2026-03-10 09:00', $beneficiary);

        $tester = $this->runCommand('app:shift:free', ['date' => $date]);

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertStringContainsString('wrong date format', $tester->getDisplay());
        $this->assertSame($beneficiary->getId(), $this->reload($shift)->getLastShifter()->getId());
    }

    public function wrongDateProvider(): array
    {
        return [
            'day first' => ['10-03-2026'],
            'impossible day' => ['2026-02-30'],
            'text' => ['today'],
        ];
    }

    private function aBeneficiary(): Beneficiary
    {
        return static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
    }

    private function aReservedShift(string $start, Beneficiary $lastShifter): Shift
    {
        $shift = ShiftBuilder::aShift()->startingAt(new \DateTime($start))->build();
        $shift->setLastShifter($lastShifter);
        static::persist($shift->getJob(), $shift);

        return $shift;
    }

    private function reload(Shift $shift): Shift
    {
        $id = $shift->getId();
        $em = static::entityManager();
        $em->clear();

        return $em->find(Shift::class, $id);
    }
}
