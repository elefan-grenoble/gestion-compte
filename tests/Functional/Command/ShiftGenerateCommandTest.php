<?php

namespace App\Tests\Functional\Command;

use App\Entity\Beneficiary;
use App\Entity\ClosingException;
use App\Entity\Job;
use App\Entity\Period;
use App\Entity\PeriodPosition;
use App\Entity\Shift;
use App\Event\ShiftReservedEvent;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\Builder\UniqueSequence;
use App\Tests\Support\Builder\UserBuilder;

/**
 * app:shift:generate creates the shifts of a day (or of a range of days) from
 * the periods and their positions.
 *
 * With the ABCD cycle (the test configuration) a position is generated only on
 * the days of its week of the cycle: week A for ISO weeks 1, 5, 9..., B for 2,
 * 6, 10... The Mondays used below:
 *
 *   2026-01-05 week B   2026-01-12 week C   2026-01-19 week D
 *   2026-01-26 week A   2026-02-02 week B
 *
 * @internal
 */
class ShiftGenerateCommandTest extends CommandTestCase
{
    private const MONDAY_OF_WEEK_B = '2026-01-05';
    private const MONDAY = 0;
    private const TUESDAY = 1;

    public function testGeneratesTheShiftOfAPeriodForTheGivenDay(): void
    {
        $job = $this->aJob();
        $this->aPeriod($job, self::MONDAY, '09:00', '12:00', 'B');

        $tester = $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $this->assertSame(0, $tester->getStatusCode());
        $shifts = $this->allShifts();
        $this->assertCount(1, $shifts);
        $shift = $shifts[0];
        $this->assertSame('2026-01-05 09:00', $shift->getStart()->format('Y-m-d H:i'));
        $this->assertSame('2026-01-05 12:00', $shift->getEnd()->format('Y-m-d H:i'));
        $this->assertSame($job->getId(), $shift->getJob()->getId());
        $this->assertSame('B', self::positionOf($shift)->getWeekCycle());
        $this->assertNull($shift->getShifter());
        $this->assertNull($shift->getLastShifter());
        $this->assertNull($shift->getBooker());
        $this->assertStringContainsString('1 créneau généré', $tester->getDisplay());
        $this->assertStringContainsString('0 créneau existe déjà', $tester->getDisplay());
    }

    /**
     * @dataProvider weekCycleProvider
     */
    public function testGeneratesOnlyThePositionsOfTheWeekOfTheCycle(string $monday, string $weekCycle): void
    {
        $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'A', 'B', 'C', 'D');

        $this->runCommand('app:shift:generate', ['date' => $monday]);

        $shifts = $this->allShifts();
        $this->assertCount(1, $shifts);
        $this->assertSame($weekCycle, self::positionOf($shifts[0])->getWeekCycle());
    }

    public function weekCycleProvider(): array
    {
        return [
            ['2025-12-29', 'A'],
            ['2026-01-05', 'B'],
            ['2026-01-12', 'C'],
            ['2026-01-19', 'D'],
            ['2026-01-26', 'A'],
            ['2026-02-02', 'B'],
        ];
    }

    public function testGeneratesOneShiftPerPositionAndPerPeriodOfTheDay(): void
    {
        $job = $this->aJob();
        $this->aPeriod($job, self::MONDAY, '09:00', '12:00', 'B', 'B');
        $this->aPeriod($job, self::MONDAY, '14:00', '17:00', 'B');
        $this->aPeriod($job, self::TUESDAY, '09:00', '12:00', 'B');

        $tester = $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $starts = array_map(static function (Shift $shift) {
            return $shift->getStart()->format('H:i');
        }, $this->allShifts());
        $this->assertSame(['09:00', '09:00', '14:00'], $starts, 'nothing for the period of Tuesday');
        $this->assertStringContainsString('3 créneaux générés', $tester->getDisplay());
    }

    public function testGeneratesNothingWhenNoPeriodFallsOnTheDay(): void
    {
        $this->aPeriod($this->aJob(), self::TUESDAY, '09:00', '12:00', 'B');

        $tester = $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([], $this->allShifts());
        $this->assertStringContainsString('0 créneau généré', $tester->getDisplay());
    }

    public function testRunningTwiceDoesNotDuplicateTheShifts(): void
    {
        $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B', 'B');

        $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);
        $firstRun = array_map(static function (Shift $shift) {
            return $shift->getId();
        }, $this->allShifts());
        $tester = $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $secondRun = array_map(static function (Shift $shift) {
            return $shift->getId();
        }, $this->allShifts());
        $this->assertCount(2, $firstRun);
        $this->assertSame($firstRun, $secondRun);
        $this->assertStringContainsString('0 créneau généré', $tester->getDisplay());
        $this->assertStringContainsString('2 créneaux existent déjà', $tester->getDisplay());
    }

    public function testCompletesTheShiftsAlreadyGeneratedWithoutTouchingThem(): void
    {
        $job = $this->aJob();
        $period = $this->aPeriod($job, self::MONDAY, '09:00', '12:00', 'B');
        $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);
        $existing = $this->allShifts()[0];

        // A position added since the first run (allShifts() detached the entities).
        $newPosition = new PeriodPosition();
        $newPosition->setWeekCycle('B');
        $period = static::entityManager()->find(Period::class, $period->getId());
        $period->addPosition($newPosition);
        static::persist($period);

        $tester = $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $this->assertCount(2, $this->allShifts());
        $this->assertContains($existing->getId(), array_map(static function (Shift $shift) {
            return $shift->getId();
        }, $this->allShifts()));
        $this->assertStringContainsString('1 créneau généré', $tester->getDisplay());
        $this->assertStringContainsString('1 créneau existe déjà', $tester->getDisplay());
    }

    public function testGeneratesEveryDayUpToButNotIncludingTheEndDate(): void
    {
        $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B', 'C');

        // Mondays 2026-01-05 (week B) and 2026-01-12 (week C) are in the range.
        $this->runCommand('app:shift:generate', ['date' => '2026-01-05', '--to' => '2026-01-13']);
        $this->assertSame(
            ['2026-01-05 B', '2026-01-12 C'],
            $this->generated()
        );

        // The end date is excluded: 2026-01-19, a Monday, is not generated.
        $this->runCommand('app:shift:generate', ['date' => '2026-01-12', '--to' => '2026-01-19']);
        $this->assertCount(2, $this->allShifts());
    }

    public function testGeneratesNothingOnAClosingException(): void
    {
        $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B');
        $closing = new ClosingException();
        $closing->setDate(new \DateTime(self::MONDAY_OF_WEEK_B));
        $closing->setReason('Inventory');
        static::persist($closing);

        $tester = $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([], $this->allShifts());
        $this->assertStringContainsString('FERMETURE EXCEPTIONNELLE', $tester->getDisplay());
    }

    public function testASingleClosingExceptionOnlySkipsItsOwnDay(): void
    {
        $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B', 'C');
        $closing = new ClosingException();
        $closing->setDate(new \DateTime('2026-01-05'));
        static::persist($closing);

        $this->runCommand('app:shift:generate', ['date' => '2026-01-05', '--to' => '2026-01-13']);

        $this->assertSame(['2026-01-12 C'], $this->generated());
    }

    /**
     * @dataProvider wrongDateProvider
     */
    public function testRejectsAMalformedDate(string $date): void
    {
        $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B');

        $tester = $this->runCommand('app:shift:generate', ['date' => $date]);

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertStringContainsString('wrong date format', $tester->getDisplay());
        $this->assertSame([], $this->allShifts());
    }

    public function wrongDateProvider(): array
    {
        return [
            'day first' => ['05-01-2026'],
            'impossible day' => ['2026-02-30'],
            'text' => ['tomorrow'],
        ];
    }

    public function testRejectsAMalformedEndDate(): void
    {
        $this->markTestIncomplete('NEW open: a malformed --to makes ShiftGenerateCommand call format() on false (fatal Error) instead of failing with status 2 like a malformed date does.');

        $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B');

        $tester = $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B, '--to' => 'not-a-date']);

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertSame([], $this->allShifts());
    }

    public function testReservesTheShiftToWhoHadItTheCycleBefore(): void
    {
        $this->spyOn(ShiftReservedEvent::NAME);
        $position = $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B')->getPositions()->first();
        $beneficiary = $this->aBeneficiary();
        $former = $this->aBookedShift($position, '2025-12-08 09:00', '2025-12-08 12:00', $beneficiary);

        $tester = $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $new = $this->shiftStartingOn('2026-01-05 09:00');
        $this->assertSame($beneficiary->getId(), $new->getLastShifter()->getId(), 'reserved to the former shifter');
        $this->assertNull($new->getShifter(), 'but not booked: they have to accept it');
        $this->assertStringContainsString('1 créneau généré', $tester->getDisplay());

        $events = $this->dispatched(ShiftReservedEvent::NAME);
        $this->assertCount(1, $events);
        $this->assertSame($new->getId(), $events[0]->getShift()->getId());
        $this->assertSame($former->getId(), $events[0]->getFormerShift()->getId());
    }

    public function testTellsTheFormerShifterForHowLongTheShiftIsReserved(): void
    {
        $position = $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B')->getPositions()->first();
        $beneficiary = $this->aBeneficiary();
        $this->aBookedShift($position, '2025-12-08 09:00', '2025-12-08 12:00', $beneficiary);

        $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $emails = $this->sentEmails();
        $this->assertCount(1, $emails);
        $this->assertSame($beneficiary->getEmail(), $emails[0]->getTo()[0]->getAddress());
        // C-BUG-2: the delay was cast to a boolean, hence "1 jours".
        $this->assertStringContainsString('pendant 7 jours', $emails[0]->getHtmlBody());
    }

    public function testDoesNotReserveWhenTheShiftOfTheCycleBeforeWasFree(): void
    {
        $this->spyOn(ShiftReservedEvent::NAME);
        $position = $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B')->getPositions()->first();
        $this->aBookedShift($position, '2025-12-08 09:00', '2025-12-08 12:00', null);

        $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $this->assertNull($this->shiftStartingOn('2026-01-05 09:00')->getLastShifter());
        $this->assertSame([], $this->dispatched(ShiftReservedEvent::NAME));
    }

    public function testDoesNotReserveFromAShiftOfAnotherCycleLength(): void
    {
        $this->spyOn(ShiftReservedEvent::NAME);
        $position = $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B')->getPositions()->first();
        // 21 days before, not 28.
        $this->aBookedShift($position, '2025-12-15 09:00', '2025-12-15 12:00', $this->aBeneficiary());

        $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $this->assertNull($this->shiftStartingOn('2026-01-05 09:00')->getLastShifter());
        $this->assertSame([], $this->dispatched(ShiftReservedEvent::NAME));
    }

    public function testDoesNotReserveWhenTheCoopDoesNotReserveToPriorShifters(): void
    {
        $this->withEnv('RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER', 'false');
        $this->spyOn(ShiftReservedEvent::NAME);
        $position = $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B')->getPositions()->first();
        $this->aBookedShift($position, '2025-12-08 09:00', '2025-12-08 12:00', $this->aBeneficiary());

        $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $new = $this->shiftStartingOn('2026-01-05 09:00');
        $this->assertNull($new->getLastShifter());
        $this->assertNull($new->getShifter());
        $this->assertSame([], $this->dispatched(ShiftReservedEvent::NAME));
        $this->assertSame([], $this->sentEmails());
    }

    public function testBooksTheFixedShiftsToTheirShifterWhenFlyAndFixedIsOn(): void
    {
        $this->withEnv('USE_FLY_AND_FIXED', 'true');
        $this->spyOn(ShiftReservedEvent::NAME);
        $admin = static::persist(UserBuilder::aUser()->withRoles('ROLE_SUPER_ADMIN')->build());
        $beneficiary = $this->aBeneficiary();
        $period = $this->aPeriod($this->aJob(), self::MONDAY, '09:00', '12:00', 'B', 'B');
        $fixedPosition = $period->getPositions()->first();
        $fixedPosition->setShifter($beneficiary);
        static::persist($period);

        $this->runCommand('app:shift:generate', ['date' => self::MONDAY_OF_WEEK_B]);

        $fixed = $free = null;
        foreach ($this->allShifts() as $shift) {
            if (self::positionOf($shift)->getId() === $fixedPosition->getId()) {
                $fixed = $shift;
            } else {
                $free = $shift;
            }
        }
        $this->assertTrue($fixed->isFixe());
        $this->assertSame($beneficiary->getId(), $fixed->getShifter()->getId());
        $this->assertSame($admin->getId(), $fixed->getBooker()->getId(), 'booked by the super admin');
        $this->assertNotNull($fixed->getBookedTime());
        $this->assertFalse($free->isFixe());
        $this->assertNull($free->getShifter());
        $this->assertSame([], $this->dispatched(ShiftReservedEvent::NAME));
    }

    /**
     * @return Shift[] every shift in the database, by start then id
     */
    private function allShifts(): array
    {
        $em = static::entityManager();
        $em->clear();

        return $em->getRepository(Shift::class)->findBy([], ['start' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @return string[] "date week" of the shifts in the database
     */
    private function generated(): array
    {
        return array_map(static function (Shift $shift) {
            return $shift->getStart()->format('Y-m-d') . ' ' . self::positionOf($shift)->getWeekCycle();
        }, $this->allShifts());
    }

    /**
     * Shift has a setter for its position but no getter.
     */
    private static function positionOf(Shift $shift): PeriodPosition
    {
        $property = new \ReflectionProperty(Shift::class, 'position');
        $property->setAccessible(true);

        return $property->getValue($shift);
    }

    private function shiftStartingOn(string $start): Shift
    {
        foreach ($this->allShifts() as $shift) {
            if ($shift->getStart()->format('Y-m-d H:i') === $start) {
                return $shift;
            }
        }

        $this->fail('No shift starting on ' . $start);
    }

    private function aJob(): Job
    {
        $job = new Job();
        $job->setName('job-' . UniqueSequence::next());
        $job->setColor('#cccccc');
        $job->setMinShifterAlert(1);
        $job->setEnabled(true);

        return static::persist($job);
    }

    /**
     * @param string $weekCycles the week of the cycle of each position of the period
     */
    private function aPeriod(Job $job, int $dayOfWeek, string $start, string $end, string ...$weekCycles): Period
    {
        $period = new Period();
        $period->setDayOfWeek($dayOfWeek);
        $period->setStart(new \DateTime($start));
        $period->setEnd(new \DateTime($end));
        $period->setJob($job);
        foreach ($weekCycles as $weekCycle) {
            $position = new PeriodPosition();
            $position->setWeekCycle($weekCycle);
            $period->addPosition($position);
        }

        return static::persist($period);
    }

    private function aBeneficiary(): Beneficiary
    {
        return static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
    }

    private function aBookedShift(PeriodPosition $position, string $start, string $end, ?Beneficiary $shifter): Shift
    {
        $shift = ShiftBuilder::aShift()->startingAt(new \DateTime($start))->lasting(180)->forJob($position->getPeriod()->getJob());
        if ($shifter) {
            $shift->bookedBy($shifter);
        }
        $shift = $shift->build();
        $shift->setPosition($position);

        return static::persist($shift);
    }
}
