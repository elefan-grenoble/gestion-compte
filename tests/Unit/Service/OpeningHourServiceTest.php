<?php

namespace App\Tests\Unit\Service;

use App\Entity\ClosingException;
use App\Entity\OpeningHour;
use App\Repository\ClosingExceptionRepository;
use App\Repository\OpeningHourRepository;
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

    /** @var OpeningHourService */
    private $service;

    protected function setUp(): void
    {
        $this->openingHours = $this->createMock(OpeningHourRepository::class);
        $this->closingExceptions = $this->createMock(ClosingExceptionRepository::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [OpeningHour::class, $this->openingHours],
            [ClosingException::class, $this->closingExceptions],
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
     * ('Y-m-d'), consistently served by the repositories.
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
        $this->openingHours->method('findByDay')->willReturnCallback(function (\DateTime $date) use ($all) {
            return array_values(array_filter($all, function (OpeningHour $openingHour) use ($date) {
                return $openingHour->getDayOfWeek() === (int) $date->format('N') - 1;
            }));
        });

        $exceptions = array_map(function (string $day) {
            return (new ClosingException())->setDate(new \DateTime($day));
        }, $closedDays);
        $this->closingExceptions->method('findFuturesOrOngoing')->willReturn($exceptions);
        $this->closingExceptions->method('findOngoing')->willReturnCallback(function (\DateTime $date) use ($exceptions) {
            foreach ($exceptions as $exception) {
                if ($exception->getDate()->format('Y-m-d') === $date->format('Y-m-d')) {
                    return $exception;
                }
            }

            return null;
        });
    }

    public function testNextClosingIsTheEndOfTheOngoingSlot(): void
    {
        $this->givenWeek([0 => [['09:00', '12:00'], ['14:00', '18:30']]]);

        $this->assertEquals(new \DateTime('2030-03-04 12:00'), $this->service->getNextClosing(new \DateTime('2030-03-04 10:00')));
        $this->assertEquals(new \DateTime('2030-03-04 18:30'), $this->service->getNextClosing(new \DateTime('2030-03-04 14:00')));
    }

    public function testNextClosingSpansSlotsWithoutBreak(): void
    {
        $this->givenWeek([0 => [['14:00', '18:00'], ['09:00', '12:00'], ['12:00', '13:00'], ['10:00', '11:00']]]);

        $this->assertEquals(new \DateTime('2030-03-04 13:00'), $this->service->getNextClosing(new \DateTime('2030-03-04 09:30')));
        $this->assertEquals(new \DateTime('2030-03-04 18:00'), $this->service->getNextClosing(new \DateTime('2030-03-04 15:00')));
    }

    public function testNextClosingIsNullWhenTheShopIsNotOpen(): void
    {
        $this->givenWeek([0 => [['09:00', '12:00']]], ['2030-03-11']);

        $this->assertNull($this->service->getNextClosing(new \DateTime('2030-03-04 13:00')), 'outside the slots');
        $this->assertNull($this->service->getNextClosing(new \DateTime('2030-03-05 10:00')), 'no slot that day');
        $this->assertNull($this->service->getNextClosing(new \DateTime('2030-03-11 10:00')), 'closing exception');
    }

    public function testNextOpeningLaterTheSameDay(): void
    {
        $this->givenWeek([0 => [['09:00', '12:00'], ['14:00', '18:00']]]);

        $this->assertEquals(new \DateTime('2030-03-04 14:00'), $this->service->getNextOpening(new \DateTime('2030-03-04 13:00')));
    }

    public function testNextOpeningIsStrictlyAfterTheDate(): void
    {
        $this->givenWeek([0 => [['09:00', '12:00'], ['14:00', '18:00']]]);

        // open exactly at 09:00 (inclusive), so the next opening is the afternoon one
        $this->assertEquals(new \DateTime('2030-03-04 14:00'), $this->service->getNextOpening(new \DateTime('2030-03-04 09:00')));
    }

    public function testNextOpeningTheNextOpenDay(): void
    {
        $this->givenWeek([0 => [['09:00', '12:00']], 1 => [['08:30', '12:00']], 4 => [['09:00', '12:00']]]);

        $this->assertEquals(new \DateTime('2030-03-05 08:30'), $this->service->getNextOpening(new \DateTime('2030-03-04 13:00')), 'next day');
        $this->assertEquals(new \DateTime('2030-03-08 09:00'), $this->service->getNextOpening(new \DateTime('2030-03-06 13:00')), 'skips days without slots');
        $this->assertEquals(new \DateTime('2030-03-11 09:00'), $this->service->getNextOpening(new \DateTime('2030-03-08 13:00')), 'next week');
    }

    public function testNextOpeningSkipsClosingExceptionDays(): void
    {
        $this->givenWeek([0 => [['09:00', '12:00']], 1 => [['09:00', '12:00']], 2 => [['10:00', '12:00']]], ['2030-03-05', '2030-03-04']);

        // the shop is closed today (exception) even though its slot is later today
        $this->assertEquals(new \DateTime('2030-03-06 10:00'), $this->service->getNextOpening(new \DateTime('2030-03-04 07:00')));
    }

    public function testNextOpeningIgnoresClosedDays(): void
    {
        $closedMonday = $this->openFrom('09:00', '12:00');
        $closedMonday->setDayOfWeek(0);
        $closedMonday->setClosed(true);
        $tuesday = $this->openFrom('10:00', '12:00');
        $tuesday->setDayOfWeek(1);
        $tuesday->setClosed(false);
        $this->openingHours->method('findOngoing')->willReturn([$closedMonday, $tuesday]);
        $this->closingExceptions->method('findFuturesOrOngoing')->willReturn([]);

        $this->assertEquals(new \DateTime('2030-03-05 10:00'), $this->service->getNextOpening(new \DateTime('2030-03-04 07:00')));
    }

    public function testNextOpeningIsNullWithoutAnyOpeningHour(): void
    {
        $this->givenWeek([]);
        $this->closingExceptions->expects($this->never())->method('findFuturesOrOngoing');

        $this->assertNull($this->service->getNextOpening(new \DateTime('2030-03-04 07:00')));
    }

    public function testNextOpeningIsNullWhenClosedBeyondTheLookAheadHorizon(): void
    {
        $closedDays = [];
        for ($i = 0; $i < 90; ++$i) {
            $closedDays[] = (new \DateTime('2030-03-04'))->modify('+' . $i . ' day')->format('Y-m-d');
        }
        $this->givenWeek([0 => [['09:00', '12:00']], 1 => [['09:00', '12:00']], 2 => [['09:00', '12:00']], 3 => [['09:00', '12:00']], 4 => [['09:00', '12:00']]], $closedDays);

        $this->assertNull($this->service->getNextOpening(new \DateTime('2030-03-04 07:00')));
    }

    public function testNextOpeningDoesNotModifyTheGivenDate(): void
    {
        $this->givenWeek([0 => [['09:00', '12:00']], 1 => [['09:00', '12:00']]]);
        $date = new \DateTime('2030-03-04 13:00');

        $this->service->getNextOpening($date);
        $this->service->getNextClosing($date);
        $this->service->getStatus($date);

        $this->assertEquals(new \DateTime('2030-03-04 13:00'), $date);
    }

    /**
     * @dataProvider statusProvider
     */
    public function testStatus(string $now, bool $expectedOpen, ?string $expectedNext, ?string $expectedNextDay): void
    {
        // Monday and Tuesday mornings, Friday all day; closed on Tuesday 2030-03-12 and Friday 2030-03-15
        $this->givenWeek(
            [0 => [['09:00', '12:00'], ['14:00', '18:00']], 1 => [['09:00', '12:00']], 4 => [['09:00', '12:00']]],
            ['2030-03-12', '2030-03-15']
        );

        $status = $this->service->getStatus(new \DateTime($now));

        $this->assertSame($expectedOpen, $status['open']);
        $this->assertEquals($expectedNext ? new \DateTime($expectedNext) : null, $status['next']);
        $this->assertSame($expectedNextDay, $status['nextDay']);
    }

    public function statusProvider(): array
    {
        return [
            'open, closes today' => ['2030-03-04 10:00', true, '2030-03-04 12:00', 'today'],
            'closed, reopens today' => ['2030-03-04 13:00', false, '2030-03-04 14:00', 'today'],
            'closed, reopens tomorrow' => ['2030-03-04 19:00', false, '2030-03-05 09:00', 'tomorrow'],
            'closed before opening, opens today' => ['2030-03-05 07:00', false, '2030-03-05 09:00', 'today'],
            'closed for the weekend, reopens later' => ['2030-03-08 13:00', false, '2030-03-11 09:00', 'later'],
            'closed by exception, reopens later' => ['2030-03-12 10:00', false, '2030-03-18 09:00', 'later'],
            'tomorrow across a month end' => ['2030-02-28 13:00', false, '2030-03-01 09:00', 'tomorrow'],
        ];
    }

    public function testStatusWithoutOpeningHourHasNoNextChange(): void
    {
        $this->givenWeek([]);

        $this->assertSame(['open' => false, 'next' => null, 'nextDay' => null], $this->service->getStatus(new \DateTime('2030-03-04 10:00')));
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
