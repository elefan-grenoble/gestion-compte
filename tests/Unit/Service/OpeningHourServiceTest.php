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
 * @internal
 */
class OpeningHourServiceTest extends TestCase
{
    /** @var MockObject|OpeningHourRepository */
    private $openingHours;

    /** @var ClosingExceptionRepository|MockObject */
    private $closingExceptions;

    /** @var MockObject|ShiftRepository */
    private $shifts;

    /** @var OpeningHourService */
    private $service;

    protected function setUp(): void
    {
        $this->openingHours = $this->createMock(OpeningHourRepository::class);
        $this->closingExceptions = $this->createMock(ClosingExceptionRepository::class);
        $this->shifts = $this->createMock(ShiftRepository::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [OpeningHour::class, $this->openingHours],
            [ClosingException::class, $this->closingExceptions],
            [Shift::class, $this->shifts],
        ]);

        $this->service = new OpeningHourService($em);
    }

    private function openFrom(string $start, string $end): OpeningHour
    {
        $openingHour = new OpeningHour();
        $openingHour->setStart(new \DateTime($start));
        $openingHour->setEnd(new \DateTime($end));

        return $openingHour;
    }

    /**
     * @param OpeningHour[] $openingHours the opening hours of the tested day
     */
    private function givenOpeningHours(array $openingHours): void
    {
        $this->openingHours->method('findByDay')->willReturn($openingHours);
    }

    public function testClosedWhenNoOpeningHourIsDefinedForTheDay(): void
    {
        $this->givenOpeningHours([]);
        // no need to look for an exception on a day that is closed anyway
        $this->closingExceptions->expects($this->never())->method('findOngoing');

        $this->assertFalse($this->service->isOpen(new \DateTime('2030-03-04 10:00')));
    }

    public function testClosedWhenTheTimeIsOutsideTheOpeningHours(): void
    {
        $this->givenOpeningHours([$this->openFrom('09:00', '12:00')]);
        $this->closingExceptions->expects($this->never())->method('findOngoing');

        $this->assertFalse($this->service->isOpen(new \DateTime('2030-03-04 14:00')));
    }

    public function testClosedWhenAClosingExceptionIsOngoing(): void
    {
        $this->givenOpeningHours([$this->openFrom('09:00', '12:00')]);
        $date = new \DateTime('2030-03-04 10:00');
        $this->closingExceptions->expects($this->once())->method('findOngoing')->with($date)->willReturn(new ClosingException());

        $this->assertFalse($this->service->isOpen($date));
    }

    public function testOpenWithinTheOpeningHoursAndWithoutClosingException(): void
    {
        $this->givenOpeningHours([$this->openFrom('09:00', '12:00')]);
        $this->closingExceptions->method('findOngoing')->willReturn(null);

        $this->assertTrue($this->service->isOpen(new \DateTime('2030-03-04 10:00')));
    }

    public function testOpenWhenOneOfSeveralSlotsMatches(): void
    {
        $this->givenOpeningHours([
            $this->openFrom('09:00', '12:00'),
            $this->openFrom('14:00', '18:00'),
        ]);
        $this->closingExceptions->method('findOngoing')->willReturn(null);

        $this->assertTrue($this->service->isOpen(new \DateTime('2030-03-04 15:00')));
        $this->assertFalse($this->service->isOpen(new \DateTime('2030-03-04 13:00')));
    }

    /**
     * Both ends of the slot are inclusive: the shop is open at the exact
     * second it opens and at the exact second it closes.
     *
     * @dataProvider boundsProvider
     */
    public function testBoundsOfTheOpeningHours(string $time, bool $expectedOpen): void
    {
        $this->givenOpeningHours([$this->openFrom('09:00:00', '12:00:00')]);
        $this->closingExceptions->method('findOngoing')->willReturn(null);

        $this->assertSame($expectedOpen, $this->service->isOpen(new \DateTime('2030-03-04 ' . $time)));
    }

    public function boundsProvider(): array
    {
        return [
            'one second before opening' => ['08:59:59', false],
            'exactly at opening' => ['09:00:00', true],
            'one second after opening' => ['09:00:01', true],
            'one second before closing' => ['11:59:59', true],
            'exactly at closing' => ['12:00:00', true],
            'one second after closing' => ['12:00:01', false],
        ];
    }

    public function testTheSlotIsAnchoredOnTheTestedDay(): void
    {
        // the stored time carries a 1970 date: only its time of day counts
        $this->givenOpeningHours([$this->openFrom('1970-01-01 09:00', '1970-01-01 12:00')]);
        $this->closingExceptions->method('findOngoing')->willReturn(null);

        $this->assertTrue($this->service->isOpen(new \DateTime('2031-07-15 10:00')));
    }

    public function testIsClosedIsTheNegationOfIsOpen(): void
    {
        $this->givenOpeningHours([$this->openFrom('09:00', '12:00')]);
        $this->closingExceptions->method('findOngoing')->willReturn(null);

        $this->assertFalse($this->service->isClosed(new \DateTime('2030-03-04 10:00')));
        $this->assertTrue($this->service->isClosed(new \DateTime('2030-03-04 13:00')));
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
                $openingHour = $this->openFrom($start, $end);
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

    /**
     * The shifts with someone on them, as [start, end] ('Y-m-d H:i').
     *
     * @param array<int, array{0: string, 1: string}> $periods
     */
    private function givenStaffedShifts(array $periods): void
    {
        $this->shifts->method('findStaffedBetween')->willReturn(array_map(function (array $period) {
            return ['start' => new \DateTime($period[0]), 'end' => new \DateTime($period[1])];
        }, $periods));
    }

    /** Monday to Friday, 9am to 7pm. */
    private function givenWorkingWeek(): void
    {
        $this->givenWeek(array_fill(0, 5, [['09:00', '19:00']]));
    }

    private function assertStatus(string $now, bool $expectedOpen, ?string $expectedNext, ?string $expectedNextDay, ?bool $expectedClosesEarly = null): void
    {
        $status = $this->service->getStatus(new \DateTime($now));

        $this->assertSame($expectedOpen, $status['open'], 'open');
        $this->assertEquals($expectedNext ? new \DateTime($expectedNext) : null, $status['next'], 'next');
        $this->assertSame($expectedNextDay, $status['nextDay'], 'next day');
        if (null !== $expectedClosesEarly) {
            $this->assertSame($expectedClosesEarly, $status['closesEarly'], 'closes early');
        }
    }

    /**
     * 2030-03-04 is a Monday.
     *
     * @dataProvider statusProvider
     *
     * @param array<int, array{0: string, 1: string}> $staffedShifts
     */
    public function testStatusFollowsTheOpeningHoursAndTheStaffedShifts(array $staffedShifts, string $now, bool $expectedOpen, ?string $expectedNext, ?string $expectedNextDay): void
    {
        $this->givenWorkingWeek();
        $this->givenStaffedShifts($staffedShifts);

        $this->assertStatus($now, $expectedOpen, $expectedNext, $expectedNextDay);
    }

    public function statusProvider(): array
    {
        return [
            'open until the end of the shift, before the end of the opening hours' => [
                [['2030-03-04 10:00', '2030-03-04 16:00']], '2030-03-04 11:00', true, '2030-03-04 16:00', 'today',
            ],
            'open until the end of the opening hours when the shift goes on' => [
                [['2030-03-04 18:00', '2030-03-04 21:00']], '2030-03-04 18:30', true, '2030-03-04 19:00', 'today',
            ],
            'open until the end of the last of the chained shifts' => [
                [['2030-03-04 10:00', '2030-03-04 12:00'], ['2030-03-04 12:00', '2030-03-04 14:00'], ['2030-03-04 14:00', '2030-03-04 16:00']],
                '2030-03-04 11:00', true, '2030-03-04 16:00', 'today',
            ],
            'open until the end of the latest of the overlapping shifts' => [
                [['2030-03-04 10:00', '2030-03-04 14:00'], ['2030-03-04 11:00', '2030-03-04 16:00']],
                '2030-03-04 12:00', true, '2030-03-04 16:00', 'today',
            ],
            'closed while nobody is on a shift, opens with the next shift' => [
                [['2030-03-04 14:00', '2030-03-04 17:00']], '2030-03-04 11:00', false, '2030-03-04 14:00', 'today',
            ],
            'closed between two shifts' => [
                [['2030-03-04 10:00', '2030-03-04 12:00'], ['2030-03-04 13:00', '2030-03-04 16:00']],
                '2030-03-04 12:30', false, '2030-03-04 13:00', 'today',
            ],
            'closed when a shift started before the opening hours, opens with the opening hours' => [
                [['2030-03-04 07:00', '2030-03-04 10:00']], '2030-03-04 08:00', false, '2030-03-04 09:00', 'today',
            ],
            'closed after the last shift of the day, opens tomorrow' => [
                [['2030-03-04 10:00', '2030-03-04 12:00'], ['2030-03-05 10:00', '2030-03-05 12:00']],
                '2030-03-04 13:00', false, '2030-03-05 10:00', 'tomorrow',
            ],
            'tomorrow across a month end' => [
                [['2030-03-01 10:00', '2030-03-01 12:00']], '2030-02-28 20:00', false, '2030-03-01 10:00', 'tomorrow',
            ],
            'closed for the weekend, opens later' => [
                [['2030-03-08 10:00', '2030-03-08 12:00'], ['2030-03-09 10:00', '2030-03-09 12:00'], ['2030-03-11 10:00', '2030-03-11 12:00']],
                '2030-03-08 13:00', false, '2030-03-11 10:00', 'later',
            ],
            'closed when a shift only touches the opening hours' => [
                [['2030-03-04 19:00', '2030-03-04 21:00']], '2030-03-04 18:00', false, null, null,
            ],
            'closed when the only shifts are on a day without opening hours' => [
                [['2030-03-09 10:00', '2030-03-09 12:00']], '2030-03-04 10:00', false, null, null,
            ],
            'closed when nobody is on any shift' => [
                [], '2030-03-04 11:00', false, null, null,
            ],
        ];
    }

    /**
     * "Closes early": the shop closes before the end of its opening hours because
     * nobody is on the following shift (2030-03-04 is a Monday).
     *
     * @dataProvider closesEarlyProvider
     *
     * @param array<int, array{0: string, 1: string}>             $staffedShifts
     * @param array<int, array<int, array{0: string, 1: string}>> $week
     */
    public function testClosesEarly(array $week, array $staffedShifts, string $now, bool $expectedOpen, bool $expectedClosesEarly): void
    {
        $this->givenWeek($week);
        $this->givenStaffedShifts($staffedShifts);

        $status = $this->service->getStatus(new \DateTime($now));

        $this->assertSame($expectedOpen, $status['open'], 'open');
        $this->assertSame($expectedClosesEarly, $status['closesEarly'], 'closes early');
    }

    public function closesEarlyProvider(): array
    {
        $nineToSeven = [0 => [['09:00', '19:00']]];

        return [
            'last staffed shift ends before the end of the opening hours' => [
                $nineToSeven, [['2030-03-04 10:00', '2030-03-04 16:00']], '2030-03-04 11:00', true, true,
            ],
            'staffed until the end of the opening hours' => [
                $nineToSeven, [['2030-03-04 10:00', '2030-03-04 19:00']], '2030-03-04 11:00', true, false,
            ],
            'staffed beyond the end of the opening hours' => [
                $nineToSeven, [['2030-03-04 10:00', '2030-03-04 21:00']], '2030-03-04 11:00', true, false,
            ],
            'gap between two staffed shifts: closes before the second one' => [
                $nineToSeven, [['2030-03-04 10:00', '2030-03-04 14:00'], ['2030-03-04 15:00', '2030-03-04 19:00']], '2030-03-04 11:00', true, true,
            ],
            'closing for the lunch break of the opening hours is not early' => [
                [0 => [['09:00', '12:00'], ['14:00', '19:00']]], [['2030-03-04 09:00', '2030-03-04 12:00']], '2030-03-04 10:00', true, false,
            ],
            'closed shop does not close early' => [
                $nineToSeven, [['2030-03-04 14:00', '2030-03-04 16:00']], '2030-03-04 11:00', false, false,
            ],
        ];
    }

    /**
     * Both ends of the open period are inclusive, as for the opening hours.
     *
     * @dataProvider boundsOfTheStaffedShiftProvider
     */
    public function testBoundsOfTheOpenPeriod(string $time, bool $expectedOpen): void
    {
        $this->givenWorkingWeek();
        $this->givenStaffedShifts([['2030-03-04 10:00:00', '2030-03-04 16:00:00']]);

        $this->assertSame($expectedOpen, $this->service->getStatus(new \DateTime('2030-03-04 ' . $time))['open']);
    }

    public function boundsOfTheStaffedShiftProvider(): array
    {
        return [
            'one second before the shift' => ['09:59:59', false],
            'exactly at the start of the shift' => ['10:00:00', true],
            'exactly at the end of the shift' => ['16:00:00', true],
            'one second after the shift' => ['16:00:01', false],
        ];
    }

    public function testClosingExceptionDaysAreClosedEvenWithAStaffedShift(): void
    {
        $this->givenWeek(array_fill(0, 5, [['09:00', '19:00']]), ['2030-03-04', '2030-03-05']);
        $this->givenStaffedShifts([
            ['2030-03-04 10:00', '2030-03-04 12:00'],
            ['2030-03-05 10:00', '2030-03-05 12:00'],
            ['2030-03-06 10:00', '2030-03-06 12:00'],
        ]);

        $this->assertStatus('2030-03-04 11:00', false, '2030-03-06 10:00', 'later');
        $this->assertStatus('2030-03-04 07:00', false, '2030-03-06 10:00', 'later');
    }

    public function testClosedDaysOfTheOpeningHoursAreClosedEvenWithAStaffedShift(): void
    {
        $closedMonday = $this->openFrom('09:00', '19:00');
        $closedMonday->setDayOfWeek(0);
        $closedMonday->setClosed(true);
        $tuesday = $this->openFrom('09:00', '19:00');
        $tuesday->setDayOfWeek(1);
        $tuesday->setClosed(false);
        $this->openingHours->method('findOngoing')->willReturn([$closedMonday, $tuesday]);
        $this->closingExceptions->method('findFuturesOrOngoing')->willReturn([]);
        $this->givenStaffedShifts([['2030-03-04 10:00', '2030-03-04 12:00'], ['2030-03-05 10:00', '2030-03-05 12:00']]);

        $this->assertStatus('2030-03-04 11:00', false, '2030-03-05 10:00', 'tomorrow');
    }

    public function testClosedWithoutOpeningHoursWhateverTheShifts(): void
    {
        $this->givenWeek([]);
        $this->shifts->expects($this->never())->method('findStaffedBetween');

        $this->assertStatus('2030-03-04 11:00', false, null, null);
    }

    public function testLooksForStaffedShiftsOverTheLookAheadPeriod(): void
    {
        $this->givenWorkingWeek();
        $this->shifts->expects($this->once())->method('findStaffedBetween')
            ->with(new \DateTime('2030-03-04 11:00'), new \DateTime('2030-05-03 11:00'))
            ->willReturn([])
        ;

        $this->service->getStatus(new \DateTime('2030-03-04 11:00'));
    }

    public function testDoesNotLookBeyondTheLookAheadPeriod(): void
    {
        $this->givenWorkingWeek();
        // two Mondays, 8 and 10 weeks (56 and 70 days) after the date
        $this->givenStaffedShifts([['2030-04-29 10:00', '2030-04-29 12:00'], ['2030-05-13 10:00', '2030-05-13 12:00']]);

        $this->assertStatus('2030-03-04 13:00', false, '2030-04-29 10:00', 'later');

        $this->shifts = $this->createMock(ShiftRepository::class);
        $this->openingHours = $this->createMock(OpeningHourRepository::class);
        $this->closingExceptions = $this->createMock(ClosingExceptionRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [OpeningHour::class, $this->openingHours],
            [ClosingException::class, $this->closingExceptions],
            [Shift::class, $this->shifts],
        ]);
        $this->service = new OpeningHourService($em);
        $this->givenWorkingWeek();
        $this->givenStaffedShifts([['2030-05-13 10:00', '2030-05-13 12:00']]);

        $this->assertStatus('2030-03-04 13:00', false, null, null);
    }

    public function testStatusDoesNotModifyTheGivenDate(): void
    {
        $this->givenWorkingWeek();
        $this->givenStaffedShifts([['2030-03-04 10:00', '2030-03-04 16:00'], ['2030-03-05 10:00', '2030-03-05 16:00']]);
        $date = new \DateTime('2030-03-04 11:00');

        $this->service->getStatus($date);

        $this->assertEquals(new \DateTime('2030-03-04 11:00'), $date);
    }

    public function testStatusDefaultsToNow(): void
    {
        // open all day, every day, and someone on a shift around now
        $this->givenWeek(array_fill(0, 7, [['00:00:00', '23:59:59']]));
        $this->givenStaffedShifts([[date('Y-m-d H:i:s', time() - 3600), date('Y-m-d H:i:s', time() + 3600)]]);

        $status = $this->service->getStatus();

        $this->assertTrue($status['open']);
        $this->assertEqualsWithDelta(time() + 3600, $status['next']->getTimestamp(), 5);
    }

    public function testDefaultsToNow(): void
    {
        $this->openingHours->expects($this->once())->method('findByDay')
            ->with($this->callback(function (\DateTime $date) {
                return abs($date->getTimestamp() - time()) < 5;
            }))
            ->willReturn([])
        ;

        $this->assertFalse($this->service->isOpen());
    }
}
