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

    /**
     * 2030-03-04 is a Monday.
     *
     * @dataProvider statusProvider
     *
     * @param array<int, array{0: string, 1: string}> $staffedShifts
     */
    public function testStatusFollowsTheOpeningHoursAndTheStaffedShifts(array $staffedShifts, string $now, bool $expectedOpen, ?string $expectedNext, ?string $expectedNextDay, bool $expectedClosesEarly = false): void
    {
        $this->givenWorkingWeek();
        $this->givenStaffedShifts($staffedShifts);

        $status = $this->service->getStatus(new \DateTime($now));

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

        $status = $this->service->getStatus(new \DateTime('2030-03-04 11:00'));

        $this->assertFalse($status['open']);
        $this->assertEquals(new \DateTime('2030-03-05 10:00'), $status['next']);
        $this->assertSame('tomorrow', $status['nextDay']);
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
