<?php

namespace App\Tests\Unit\Service;

use App\Entity\ClosingException;
use App\Entity\OpeningHour;
use App\Entity\Shift;
use App\Repository\ClosingExceptionRepository;
use App\Repository\OpeningHourRepository;
use App\Repository\ShiftRepository;
use App\Service\OpeningHourService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Status of the shop when the shifts count (OPENING_HOUR_USE_SHIFTS_IN_OPEN_CLOSED_HEADER):
 * the shop is open when the opening hours say so AND someone is on a shift.
 *
 * 2030-03-04 is a Monday.
 *
 * @internal
 */
class OpeningHourServiceShiftsTest extends TestCase
{
    /** @var MockObject|OpeningHourRepository */
    private $openingHours;

    /** @var ClosingExceptionRepository|MockObject */
    private $closingExceptions;

    /** @var MockObject|ShiftRepository */
    private $shifts;

    /** @var EntityManagerInterface|MockObject */
    private $em;

    /** @var int[] length in days of the windows the shifts were loaded for */
    private $windowsQueried = [];

    protected function setUp(): void
    {
        $this->openingHours = $this->createMock(OpeningHourRepository::class);
        $this->closingExceptions = $this->createMock(ClosingExceptionRepository::class);
        $this->shifts = $this->createMock(ShiftRepository::class);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('getRepository')->willReturnMap([
            [OpeningHour::class, $this->openingHours],
            [ClosingException::class, $this->closingExceptions],
            [Shift::class, $this->shifts],
        ]);
    }

    private function service(bool $useShifts = true): OpeningHourService
    {
        return new OpeningHourService($this->em, $useShifts);
    }

    /**
     * Opening hours of a whole week (0 = Monday) and the closing exception days
     * ('Y-m-d'), as served by the repositories.
     *
     * @param array<int, array<int, array{0: string, 1: string}>> $slotsByDay
     * @param string[]                                            $closedDays
     */
    private function givenWeek(array $slotsByDay, array $closedDays = []): void
    {
        $all = [];
        foreach ($slotsByDay as $dayOfWeek => $slots) {
            foreach ($slots as [$start, $end]) {
                $openingHour = new OpeningHour();
                $openingHour->setStart(new \DateTime($start));
                $openingHour->setEnd(new \DateTime($end));
                $openingHour->setDayOfWeek($dayOfWeek);
                $openingHour->setClosed(false);
                $all[] = $openingHour;
            }
        }
        $this->openingHours->method('findOngoing')->willReturn($all);

        $this->closingExceptions->method('findFuturesOrOngoing')->willReturn(array_map(function (string $day) {
            return (new ClosingException())->setDate(new \DateTime($day));
        }, $closedDays));
    }

    /** Monday to Friday, 9am to 7pm. */
    private function givenWorkingWeek(): void
    {
        $this->givenWeek(array_fill(0, 5, [['09:00', '19:00']]));
    }

    /**
     * The shifts with someone on them, as [start, end] ('Y-m-d H:i').
     *
     * @param array<int, array{0: string, 1: string}> $periods
     */
    private function givenStaffedShifts(array $periods): void
    {
        $this->shifts->method('findStaffedBetween')->willReturn($this->asRows($periods));
    }

    private function asRows(array $periods): array
    {
        return array_map(function (array $period) {
            return ['start' => new \DateTime($period[0]), 'end' => new \DateTime($period[1])];
        }, $periods);
    }

    /**
     * @dataProvider statusProvider
     *
     * @param array<int, array{0: string, 1: string}> $staffedShifts
     */
    public function testStatusFollowsTheOpeningHoursAndTheStaffedShifts(array $staffedShifts, string $now, bool $expectedOpen, ?string $expectedNext, ?string $expectedNextDay, bool $expectedClosesEarly = false): void
    {
        $this->givenWorkingWeek();
        $this->givenStaffedShifts($staffedShifts);

        $status = $this->service()->getStatus(new \DateTime($now));

        $this->assertSame($expectedOpen, $status['open'], 'open');
        $this->assertEquals($expectedNext ? new \DateTime($expectedNext) : null, $status['next'], 'next');
        $this->assertSame($expectedNextDay, $status['nextDay'], 'next day');
        $this->assertSame($expectedClosesEarly, $status['closesEarly'], 'closes early');
    }

    public function statusProvider(): array
    {
        return [
            'open until the end of the shift, which is before the end of the opening hours' => [
                [['2030-03-04 10:00', '2030-03-04 16:00']], '2030-03-04 11:00', true, '2030-03-04 16:00', 'today', true,
            ],
            'open until the end of the opening hours when the shift goes on' => [
                [['2030-03-04 18:00', '2030-03-04 21:00']], '2030-03-04 18:30', true, '2030-03-04 19:00', 'today',
            ],
            'open until the end of the last of the chained shifts' => [
                [['2030-03-04 10:00', '2030-03-04 12:00'], ['2030-03-04 12:00', '2030-03-04 14:00'], ['2030-03-04 14:00', '2030-03-04 16:00']],
                '2030-03-04 11:00', true, '2030-03-04 16:00', 'today', true,
            ],
            'closed while nobody is on a shift, opens with the next shift' => [
                [['2030-03-04 14:00', '2030-03-04 17:00']], '2030-03-04 11:00', false, '2030-03-04 14:00', 'today',
            ],
            'closed when a shift started before the opening hours, opens with the opening hours' => [
                [['2030-03-04 07:00', '2030-03-04 10:00']], '2030-03-04 08:00', false, '2030-03-04 09:00', 'today',
            ],
            'closed after the last shift of the day, opens tomorrow' => [
                [['2030-03-04 10:00', '2030-03-04 12:00'], ['2030-03-05 10:00', '2030-03-05 12:00']],
                '2030-03-04 13:00', false, '2030-03-05 10:00', 'tomorrow',
            ],
            'closed for the weekend, opens later' => [
                [['2030-03-08 10:00', '2030-03-08 12:00'], ['2030-03-09 10:00', '2030-03-09 12:00'], ['2030-03-11 10:00', '2030-03-11 12:00']],
                '2030-03-08 13:00', false, '2030-03-11 10:00', 'later',
            ],
            'closed when nobody is on any shift' => [
                [], '2030-03-04 11:00', false, null, null,
            ],
        ];
    }

    public function testClosingExceptionDaysAreClosedEvenWithAStaffedShift(): void
    {
        $this->givenWeek(array_fill(0, 5, [['09:00', '19:00']]), ['2030-03-04']);
        $this->givenStaffedShifts([['2030-03-04 10:00', '2030-03-04 12:00'], ['2030-03-05 10:00', '2030-03-05 12:00']]);

        $status = $this->service()->getStatus(new \DateTime('2030-03-04 11:00'));

        $this->assertFalse($status['open']);
        $this->assertEquals(new \DateTime('2030-03-05 10:00'), $status['next']);
        $this->assertSame('tomorrow', $status['nextDay']);
    }

    public function testStatusIgnoresTheShiftsWhenTheyAreNotUsed(): void
    {
        $this->givenWorkingWeek();
        $this->closingExceptions->method('findOngoing')->willReturn(null);
        $this->openingHours->method('findByDay')->willReturn([$this->mondaySlot()]);
        $this->shifts->expects($this->never())->method('findStaffedBetween');

        // the opening hours say open, nobody is on a shift
        $status = $this->service(false)->getStatus(new \DateTime('2030-03-04 11:00'));

        $this->assertTrue($status['open']);
        $this->assertEquals(new \DateTime('2030-03-04 19:00'), $status['next']);
        $this->assertFalse($status['closesEarly']);
    }

    /** The slot of a Monday, as findByDay serves it. */
    private function mondaySlot(): OpeningHour
    {
        $openingHour = new OpeningHour();
        $openingHour->setStart(new \DateTime('09:00'));
        $openingHour->setEnd(new \DateTime('19:00'));
        $openingHour->setDayOfWeek(0);
        $openingHour->setClosed(false);

        return $openingHour;
    }

    // ---------------------------------------------------------------------
    // Progressive search: the shifts are loaded for a window of 1, 3, 7, 30
    // then 60 days, and the search stops as soon as the window is enough.
    // ---------------------------------------------------------------------

    /**
     * Serves the shifts overlapping each window the service asks for, and records the
     * length (in days) of these windows in $this->windowsQueried.
     *
     * @param array<int, array{0: string, 1: string}> $periods
     */
    private function givenStaffedShiftsServedByWindow(array $periods): void
    {
        $this->shifts->method('findStaffedBetween')
            ->willReturnCallback(function (\DateTime $from, \DateTime $until) use ($periods) {
                $this->windowsQueried[] = (int) round(($until->getTimestamp() - $from->getTimestamp()) / 86400);

                return array_values(array_filter($this->asRows($periods), function (array $row) use ($from, $until) {
                    return $row['end'] >= $from && $row['start'] <= $until;
                }));
            })
        ;
    }

    public function testTheSearchStopsAtTheFirstWindowWhenTheShopIsOpen(): void
    {
        $this->givenWorkingWeek();
        $this->givenStaffedShiftsServedByWindow([['2030-03-04 10:00', '2030-03-04 16:00']]);

        $status = $this->service()->getStatus(new \DateTime('2030-03-04 11:00'));

        $this->assertTrue($status['open']);
        $this->assertEquals(new \DateTime('2030-03-04 16:00'), $status['next']);
        $this->assertSame([1], $this->windowsQueried);
    }

    public function testTheSearchStopsAtTheFirstWindowWhenTheNextOpeningIsInIt(): void
    {
        $this->givenWorkingWeek();
        $this->givenStaffedShiftsServedByWindow([['2030-03-05 10:00', '2030-03-05 12:00']]);

        $status = $this->service()->getStatus(new \DateTime('2030-03-04 20:00'));

        $this->assertFalse($status['open']);
        $this->assertEquals(new \DateTime('2030-03-05 10:00'), $status['next']);
        $this->assertSame([1], $this->windowsQueried);
    }

    public function testTheWindowGrowsWhileNoOpeningIsFound(): void
    {
        $this->givenWorkingWeek();
        // From Friday evening the next shift is 10 days away, on Monday 2030-03-18. The window of
        // 1 day only holds the weekend: with no opening hours in it, no shift is loaded at all.
        // The windows of 3 and 7 days hold the opening hours but no shift, the one of 30 days has it.
        $this->givenStaffedShiftsServedByWindow([['2030-03-18 10:00', '2030-03-18 12:00']]);

        $status = $this->service()->getStatus(new \DateTime('2030-03-08 20:00'));

        $this->assertFalse($status['open']);
        $this->assertEquals(new \DateTime('2030-03-18 10:00'), $status['next']);
        $this->assertSame('later', $status['nextDay']);
        $this->assertSame([3, 7, 30], $this->windowsQueried);
    }

    public function testTheLastWindowIsTheLimitWhenNothingIsFound(): void
    {
        $this->givenWorkingWeek();
        $this->givenStaffedShiftsServedByWindow([]);

        $status = $this->service()->getStatus(new \DateTime('2030-03-04 11:00'));

        $this->assertFalse($status['open']);
        $this->assertNull($status['next']);
        $this->assertNull($status['nextDay']);
        $this->assertSame([1, 3, 7, 30, 60], $this->windowsQueried);
    }

    public function testNoShiftIsLoadedWithoutOpeningHour(): void
    {
        $this->givenWeek([]);
        $this->shifts->expects($this->never())->method('findStaffedBetween');

        $status = $this->service()->getStatus(new \DateTime('2030-03-04 11:00'));

        $this->assertSame(['open' => false, 'next' => null, 'nextDay' => null, 'closesEarly' => false], $status);
    }
}
