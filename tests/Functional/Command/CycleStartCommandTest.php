<?php

namespace App\Tests\Functional\Command;

use App\Entity\Membership;
use App\Entity\TimeLog;
use App\Event\MemberCycleEndEvent;
use App\Tests\Support\Builder\MembershipBuilder;

/**
 * app:user:cycle_start dispatches a MemberCycleEndEvent, at the start of each
 * cycle, for the members whose cycle starts that day.
 *
 * With the ABCD cycle (the test configuration) cycles start on the Monday of
 * week A, i.e. of ISO weeks 1, 5, 9... Without it they start every 28 days
 * from the member's first shift date.
 *
 * @internal
 */
class CycleStartCommandTest extends CommandTestCase
{
    /** Monday of ISO week 5 of 2026: week A of the ABCD cycle. */
    private const WEEK_A_MONDAY = '2026-01-26';

    public function testDispatchesACycleEndEventForTheMembersWhoseCycleStartsThatDay(): void
    {
        $this->spyOn(MemberCycleEndEvent::NAME);
        $started = $this->membershipWithFirstShiftOn('2025-06-02');
        $startedLongAgo = $this->membershipWithFirstShiftOn('2019-01-01');

        $tester = $this->runCommand('app:user:cycle_start', ['--date' => self::WEEK_A_MONDAY]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([$started->getId(), $startedLongAgo->getId()], $this->notifiedMembershipIds(true));
        $this->assertStringContainsString('cycle start command for ' . self::WEEK_A_MONDAY, $tester->getDisplay());
        $this->assertStringContainsString('Generate member.cycle.end event for member #' . $started->getMemberNumber(), $tester->getDisplay());
        $this->assertStringContainsString('2 event(s) created', $tester->getDisplay());

        foreach ($this->dispatched(MemberCycleEndEvent::NAME) as $event) {
            $this->assertSame(self::WEEK_A_MONDAY . ' 00:00:00', $event->getDate()->format('Y-m-d H:i:s'));
        }
    }

    public function testSkipsTheMembersWhoCannotBeAtTheStartOfACycle(): void
    {
        $this->spyOn(MemberCycleEndEvent::NAME);
        $started = $this->membershipWithFirstShiftOn('2025-06-02');
        $withdrawn = MembershipBuilder::aMembership()->withdrawn()->withFirstShiftDate(new \DateTime('2025-06-02'))->build();
        $neverShifted = MembershipBuilder::aMembership()->build();
        $firstShiftThatDay = $this->membershipWithFirstShiftOn(self::WEEK_A_MONDAY);
        $firstShiftLater = $this->membershipWithFirstShiftOn('2026-02-02');
        static::persist($withdrawn, $neverShifted);

        $this->runCommand('app:user:cycle_start', ['--date' => self::WEEK_A_MONDAY]);

        $this->assertSame([$started->getId()], $this->notifiedMembershipIds());
    }

    public function testFrozenMembersAreNotifiedToo(): void
    {
        $this->spyOn(MemberCycleEndEvent::NAME);
        $frozen = MembershipBuilder::aMembership()->frozen()->withFirstShiftDate(new \DateTime('2025-06-02'))->build();
        static::persist($frozen);

        $this->runCommand('app:user:cycle_start', ['--date' => self::WEEK_A_MONDAY]);

        // The listener thaws or logs them: the command does not filter on it.
        $this->assertSame([$frozen->getId()], $this->notifiedMembershipIds());
    }

    /**
     * @dataProvider daysThatStartNoCycleProvider
     */
    public function testDispatchesNothingOutsideTheMondayOfWeekA(string $date): void
    {
        $this->spyOn(MemberCycleEndEvent::NAME);
        $this->membershipWithFirstShiftOn('2025-06-02');

        $tester = $this->runCommand('app:user:cycle_start', ['--date' => $date]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([], $this->notifiedMembershipIds());
        $this->assertStringContainsString('0 event(s) created', $tester->getDisplay());
    }

    public function daysThatStartNoCycleProvider(): array
    {
        return [
            'Tuesday of week A' => ['2026-01-27'],
            'Sunday of week A' => ['2026-02-01'],
            'Monday of week B' => ['2026-02-02'],
            'Monday of week C' => ['2026-02-09'],
            'Monday of week D' => ['2026-02-16'],
        ];
    }

    public function testAMondayOfWeekAStartsACycleInEveryFourthWeek(): void
    {
        $this->spyOn(MemberCycleEndEvent::NAME);
        $member = $this->membershipWithFirstShiftOn('2025-06-02');

        $this->runCommand('app:user:cycle_start', ['--date' => '2026-02-23']);
        $this->runCommand('app:user:cycle_start', ['--date' => '2025-12-29']);

        $this->assertSame([$member->getId(), $member->getId()], $this->notifiedMembershipIds());
    }

    public function testBooksTheCycleEndTimeLogOfTheMembersWhoseCycleStarts(): void
    {
        $started = $this->membershipWithFirstShiftOn('2025-06-02');

        $this->runCommand('app:user:cycle_start', ['--date' => self::WEEK_A_MONDAY]);

        $logs = static::entityManager()->getRepository(TimeLog::class)->findBy(['membership' => $started->getId(), 'type' => TimeLog::TYPE_CYCLE_END]);
        $this->assertCount(1, $logs);
        $this->assertSame(-180, $logs[0]->getTime(), 'one cycle of due time (DUE_DURATION_BY_CYCLE)');
    }

    /**
     * @dataProvider wrongDateProvider
     */
    public function testRejectsAMalformedDate(string $date): void
    {
        $this->spyOn(MemberCycleEndEvent::NAME);
        $this->membershipWithFirstShiftOn('2025-06-02');

        $tester = $this->runCommand('app:user:cycle_start', ['--date' => $date]);

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertStringContainsString('wrong date format', $tester->getDisplay());
        $this->assertSame([], $this->notifiedMembershipIds());
    }

    public function wrongDateProvider(): array
    {
        return [
            'day first' => ['26-01-2026'],
            'impossible day' => ['2026-02-30'],
            'text' => ['monday'],
        ];
    }

    public function testWithoutACycleTypeOfWeeksCyclesStartEvery28DaysFromTheFirstShift(): void
    {
        $this->withEnv('CYCLE_TYPE', 'standard');
        $this->spyOn(MemberCycleEndEvent::NAME);
        $date = new \DateTime('2026-03-10');
        $onCycle = $this->membershipWithFirstShiftOn((clone $date)->modify('-56 days')->format('Y-m-d'));
        $this->membershipWithFirstShiftOn((clone $date)->modify('-27 days')->format('Y-m-d'));
        $this->membershipWithFirstShiftOn((clone $date)->modify('-14 days')->format('Y-m-d'));
        $this->membershipWithFirstShiftOn((clone $date)->modify('-29 days')->format('Y-m-d'));
        $secondOnCycle = $this->membershipWithFirstShiftOn((clone $date)->modify('-28 days')->format('Y-m-d'));

        $tester = $this->runCommand('app:user:cycle_start', ['--date' => $date->format('Y-m-d')]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([$onCycle->getId(), $secondOnCycle->getId()], $this->notifiedMembershipIds(true));
    }

    public function testCycleLengthFollowsTheCycleDurationParameter(): void
    {
        $this->withEnv('CYCLE_TYPE', 'standard');
        $this->withEnv('CYCLE_DURATION', '14 days');
        $this->spyOn(MemberCycleEndEvent::NAME);
        $member = $this->membershipWithFirstShiftOn('2026-02-24');

        $this->runCommand('app:user:cycle_start', ['--date' => '2026-03-10']);

        $this->assertSame([$member->getId()], $this->notifiedMembershipIds());
    }

    /**
     * @param bool $sorted whether to sort the ids, for the tests that do not care about the order
     *
     * @return int[] ids of the memberships the dispatched events were about
     */
    private function notifiedMembershipIds(bool $sorted = false): array
    {
        $ids = array_map(static function (MemberCycleEndEvent $event) {
            return $event->getMembership()->getId();
        }, $this->dispatched(MemberCycleEndEvent::NAME));
        if ($sorted) {
            sort($ids);
        }

        return $ids;
    }

    private function membershipWithFirstShiftOn(string $date): Membership
    {
        return static::persist(MembershipBuilder::aMembership()->withFirstShiftDate(new \DateTime($date))->build());
    }
}
