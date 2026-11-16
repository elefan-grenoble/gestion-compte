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
 * open when the opening hours say so AND someone is registered on a shift.
 * The opening hours are Monday to Friday, 9am to 7pm. 2030-03-04 is a Monday.
 *
 * @internal
 */
class OpeningHourServiceShiftsTest extends TestCase
{
    /** @var MockObject|ShiftRepository */
    private $shifts;

    /** @var EntityManagerInterface|MockObject */
    private $em;

    /** @var int[] length in days of the windows the shifts were loaded for */
    private $windows = [];

    protected function setUp(): void
    {
        $this->shifts = $this->createMock(ShiftRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    /**
     * @param array<int, array{0: string, 1: string}> $shifts     [start, end] of the shifts with someone on them
     * @param string[]                                $closedDays closing exception days ('Y-m-d')
     */
    private function service(array $shifts, bool $useShifts = true, array $closedDays = []): OpeningHourService
    {
        $slots = [];
        for ($day = 0; $day < 5; ++$day) {
            $slot = (new OpeningHour())->setDayOfWeek($day)->setClosed(false);
            $slot->setStart(new \DateTime('09:00'));
            $slot->setEnd(new \DateTime('19:00'));
            $slots[] = $slot;
        }
        $openingHours = $this->createMock(OpeningHourRepository::class);
        $openingHours->method('findOngoing')->willReturn($slots);
        $closingExceptions = $this->createMock(ClosingExceptionRepository::class);
        $closingExceptions->method('findFuturesOrOngoing')->willReturn(array_map(function (string $day) {
            return (new ClosingException())->setDate(new \DateTime($day));
        }, $closedDays));

        // like the repository: the shifts overlapping the window
        $this->shifts->method('findStaffedBetween')->willReturnCallback(function (\DateTime $from, \DateTime $until) use ($shifts) {
            $this->windows[] = (int) round(($until->getTimestamp() - $from->getTimestamp()) / 86400);
            $rows = array_map(function (array $shift) {
                return ['start' => new \DateTime($shift[0]), 'end' => new \DateTime($shift[1])];
            }, $shifts);

            return array_values(array_filter($rows, function (array $row) use ($from, $until) {
                return $row['end'] >= $from && $row['start'] <= $until;
            }));
        });

        $this->em->method('getRepository')->willReturnMap([
            [OpeningHour::class, $openingHours],
            [ClosingException::class, $closingExceptions],
            [Shift::class, $this->shifts],
        ]);

        return new OpeningHourService($this->em, $useShifts);
    }

    /**
     * @dataProvider statusProvider
     */
    public function testStatusFollowsTheOpeningHoursAndTheShifts(array $shifts, string $now, bool $open, ?string $next, bool $closesEarly = false): void
    {
        $status = $this->service($shifts)->getStatus(new \DateTime($now));

        $this->assertSame($open, $status['open'], 'open');
        $this->assertEquals($next ? new \DateTime($next) : null, $status['next'], 'next');
        $this->assertSame($closesEarly, $status['closesEarly'], 'closes early');
    }

    public function statusProvider(): array
    {
        return [
            'open until the end of the shift, before the end of the opening hours' => [[['2030-03-04 10:00', '2030-03-04 16:00']], '2030-03-04 11:00', true, '2030-03-04 16:00', true],
            'chained shifts count as one' => [[['2030-03-04 10:00', '2030-03-04 12:00'], ['2030-03-04 12:00', '2030-03-04 16:00']], '2030-03-04 11:00', true, '2030-03-04 16:00', true],
            'a shift going on after the opening hours does not keep the shop open' => [[['2030-03-04 18:00', '2030-03-04 21:00']], '2030-03-04 18:30', true, '2030-03-04 19:00'],
            'closed while nobody is on a shift, opens with the next one' => [[['2030-03-04 14:00', '2030-03-04 17:00']], '2030-03-04 11:00', false, '2030-03-04 14:00'],
            'a shift before the opening hours does not open the shop' => [[['2030-03-04 07:00', '2030-03-04 10:00']], '2030-03-04 08:00', false, '2030-03-04 09:00'],
            'closed when nobody is on any shift' => [[], '2030-03-04 11:00', false, null],
        ];
    }

    public function testClosingExceptionDaysAreClosedEvenWithAShift(): void
    {
        $status = $this->service([['2030-03-04 10:00', '2030-03-04 12:00'], ['2030-03-05 10:00', '2030-03-05 12:00']], true, ['2030-03-04'])
            ->getStatus(new \DateTime('2030-03-04 11:00'))
        ;

        $this->assertFalse($status['open']);
        $this->assertEquals(new \DateTime('2030-03-05 10:00'), $status['next']);
    }

    public function testShiftsAreIgnoredWhenTheyAreNotUsed(): void
    {
        $this->shifts->expects($this->never())->method('findStaffedBetween');
        $openingHours = $this->createMock(OpeningHourRepository::class);
        $slot = (new OpeningHour())->setDayOfWeek(0)->setClosed(false);
        $slot->setStart(new \DateTime('09:00'));
        $slot->setEnd(new \DateTime('19:00'));
        $openingHours->method('findByDay')->willReturn([$slot]);
        $openingHours->method('findOngoing')->willReturn([$slot]);
        $closingExceptions = $this->createMock(ClosingExceptionRepository::class);
        $this->em->method('getRepository')->willReturnMap([[OpeningHour::class, $openingHours], [ClosingException::class, $closingExceptions]]);

        $status = (new OpeningHourService($this->em, false))->getStatus(new \DateTime('2030-03-04 11:00'));

        $this->assertTrue($status['open']);
        $this->assertFalse($status['closesEarly']);
    }

    /**
     * The shifts are loaded for windows of 1, 3, 7, 30 then 60 days, and the search stops at
     * the first window with an open period. With no opening hours in a window (the weekend),
     * no shift is loaded for it.
     *
     * @dataProvider windowsProvider
     */
    public function testTheWindowGrowsUntilTheNextChangeIsFound(string $now, array $shifts, array $expectedWindows, ?string $expectedNext): void
    {
        $status = $this->service($shifts)->getStatus(new \DateTime($now));

        $this->assertEquals($expectedNext ? new \DateTime($expectedNext) : null, $status['next']);
        $this->assertSame($expectedWindows, $this->windows);
    }

    public function windowsProvider(): array
    {
        return [
            'open: the first window is enough' => ['2030-03-04 11:00', [['2030-03-04 10:00', '2030-03-04 16:00']], [1], '2030-03-04 16:00'],
            'opens tomorrow: the first window is enough' => ['2030-03-04 20:00', [['2030-03-05 10:00', '2030-03-05 12:00']], [1], '2030-03-05 10:00'],
            'opens in 10 days, from a Friday evening' => ['2030-03-08 20:00', [['2030-03-18 10:00', '2030-03-18 12:00']], [3, 7, 30], '2030-03-18 10:00'],
            'no shift at all: up to the last window' => ['2030-03-04 11:00', [], [1, 3, 7, 30, 60], null],
        ];
    }
}
