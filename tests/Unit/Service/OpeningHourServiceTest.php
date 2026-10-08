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
