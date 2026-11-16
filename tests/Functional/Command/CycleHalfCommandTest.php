<?php

namespace App\Tests\Functional\Command;

use App\Entity\Membership;
use App\Event\MemberCycleHalfEvent;
use App\Tests\Support\Builder\MembershipBuilder;

/**
 * app:user:cycle_half dispatches a MemberCycleHalfEvent at the midpoint of
 * each cycle. Without the ABCD cycle, the cycle length follows CYCLE_DURATION
 * and there must be exactly one midpoint per cycle, odd lengths included.
 *
 * @internal
 */
class CycleHalfCommandTest extends CommandTestCase
{
    /**
     * @dataProvider midpointsOfAnOddCycleProvider
     */
    public function testSelectsOneMidpointPerCycleWithAnOddCycleDuration(int $daysSinceFirstShift, bool $isMidpoint): void
    {
        $this->withEnv('CYCLE_TYPE', 'standard');
        $this->withEnv('CYCLE_DURATION', '21 days');
        $this->spyOn(MemberCycleHalfEvent::NAME);
        $date = new \DateTime('2026-06-30');
        $member = $this->membershipWithFirstShiftOn((clone $date)->modify("-{$daysSinceFirstShift} days")->format('Y-m-d'));

        $this->runCommand('app:user:cycle_half', ['--date' => $date->format('Y-m-d')]);

        $this->assertSame($isMidpoint ? [$member->getId()] : [], $this->notifiedMembershipIds());
    }

    public function midpointsOfAnOddCycleProvider(): array
    {
        return [
            'middle of the first cycle' => [10, true],
            'day 20: multiple of the old half, not a midpoint' => [20, false],
            'cycle boundary' => [21, false],
            'middle of the second cycle (was missed)' => [31, true],
            'multiple of 10 in the third cycle' => [40, false],
            'middle of the third cycle' => [52, true],
        ];
    }

    public function testSelectsTheMidpointOfAnEvenCycleDuration(): void
    {
        $this->withEnv('CYCLE_TYPE', 'standard');
        $this->withEnv('CYCLE_DURATION', '14 days');
        $this->spyOn(MemberCycleHalfEvent::NAME);
        $date = new \DateTime('2026-06-30');
        $onMidpoint = $this->membershipWithFirstShiftOn((clone $date)->modify('-7 days')->format('Y-m-d'));
        $this->membershipWithFirstShiftOn((clone $date)->modify('-14 days')->format('Y-m-d'));
        $secondMidpoint = $this->membershipWithFirstShiftOn((clone $date)->modify('-21 days')->format('Y-m-d'));

        $this->runCommand('app:user:cycle_half', ['--date' => $date->format('Y-m-d')]);

        $this->assertSame([$onMidpoint->getId(), $secondMidpoint->getId()], $this->notifiedMembershipIds());
    }

    /**
     * @return int[] ids of the memberships the dispatched events were about
     */
    private function notifiedMembershipIds(): array
    {
        $ids = array_map(static function (MemberCycleHalfEvent $event) {
            return $event->getMembership()->getId();
        }, $this->dispatched(MemberCycleHalfEvent::NAME));
        sort($ids);

        return $ids;
    }

    private function membershipWithFirstShiftOn(string $date): Membership
    {
        return static::persist(MembershipBuilder::aMembership()->withFirstShiftDate(new \DateTime($date))->build());
    }
}
