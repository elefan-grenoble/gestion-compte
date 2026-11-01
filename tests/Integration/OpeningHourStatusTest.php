<?php

namespace App\Tests\Integration;

use App\Entity\OpeningHour;
use App\Entity\OpeningHourKind;
use App\Entity\Shift;
use App\Repository\ShiftRepository;
use App\Service\OpeningHourService;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\PersistsEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The shop is open when the opening hours say so AND someone is on a shift. Runs
 * the real queries: the staffed shifts, the opening hours, the closing exceptions.
 *
 * Dates are far in the future (2031-03-03 is a Monday) so that the rows other
 * tests or the fixtures may have left do not interfere.
 *
 * @internal
 */
class OpeningHourStatusTest extends KernelTestCase
{
    use PersistsEntities;

    protected function setUp(): void
    {
        static::bootKernel();
    }

    // ---------------------------------------------------------------------
    // ShiftRepository::findStaffedBetween
    // ---------------------------------------------------------------------

    public function testFindStaffedBetweenReturnsOnlyTheShiftsWithSomeoneOnThem(): void
    {
        $member = static::persist(MembershipBuilder::aMembership()->build());
        $this->aShift('2031-03-03 10:00', 120, $member);
        $this->aShift('2031-03-03 14:00', 120, null);

        $staffed = $this->shiftRepository()->findStaffedBetween(new \DateTime('2031-03-03 00:00'), new \DateTime('2031-03-03 23:59'));

        $this->assertCount(1, $staffed);
        $this->assertEquals(new \DateTime('2031-03-03 10:00'), $staffed[0]['start']);
        $this->assertEquals(new \DateTime('2031-03-03 12:00'), $staffed[0]['end']);
    }

    public function testFindStaffedBetweenIgnoresTheShiftsOfDisabledJobs(): void
    {
        $member = static::persist(MembershipBuilder::aMembership()->build());
        $disabledShift = $this->aShift('2031-03-03 10:00', 120, $member);
        $disabledShift->getJob()->setEnabled(false);
        static::persist($disabledShift->getJob());
        $this->aShift('2031-03-03 14:00', 120, $member);

        $staffed = $this->shiftRepository()->findStaffedBetween(new \DateTime('2031-03-03 00:00'), new \DateTime('2031-03-03 23:59'));

        $this->assertEquals([new \DateTime('2031-03-03 14:00')], array_column($staffed, 'start'));
    }

    public function testFindStaffedBetweenKeepsTheShiftsOverlappingThePeriodSortedByStart(): void
    {
        $member = static::persist(MembershipBuilder::aMembership()->build());
        $this->aShift('2031-03-03 15:00', 60, $member);
        $this->aShift('2031-03-03 09:00', 60, $member); // ends before the period
        $this->aShift('2031-03-03 13:30', 60, $member); // overlaps the start of the period
        $this->aShift('2031-03-03 16:30', 60, $member); // overlaps the end of the period
        $this->aShift('2031-03-03 18:00', 60, $member); // starts after the period

        $staffed = $this->shiftRepository()->findStaffedBetween(new \DateTime('2031-03-03 14:00'), new \DateTime('2031-03-03 17:00'));

        $this->assertEquals(
            [new \DateTime('2031-03-03 13:30'), new \DateTime('2031-03-03 15:00'), new \DateTime('2031-03-03 16:30')],
            array_column($staffed, 'start')
        );
    }

    // ---------------------------------------------------------------------
    // OpeningHourService::getStatus, from the database
    // ---------------------------------------------------------------------

    public function testStatusCombinesTheOpeningHoursAndTheStaffedShifts(): void
    {
        $this->openingHours(0, '09:00', '19:00');
        $member = static::persist(MembershipBuilder::aMembership()->build());
        $this->aShift('2031-03-03 10:00', 360, $member); // until 4pm

        $open = $this->service()->getStatus(new \DateTime('2031-03-03 11:00'));
        $this->assertTrue($open['open']);
        $this->assertEquals(new \DateTime('2031-03-03 16:00'), $open['next']);
        $this->assertSame('today', $open['nextDay']);

        $closed = $this->service()->getStatus(new \DateTime('2031-03-03 17:00'));
        $this->assertFalse($closed['open']);
    }

    public function testStatusIsClosedWhenTheOpeningHoursAreOpenButNobodyIsOnAShift(): void
    {
        $this->openingHours(0, '09:00', '19:00');
        $this->aShift('2031-03-03 10:00', 360, null);

        $status = $this->service()->getStatus(new \DateTime('2031-03-03 11:00'));

        $this->assertFalse($status['open']);
        $this->assertNull($status['next']);
    }

    public function testStatusIsClosedWhenAShiftIsStaffedOutsideTheOpeningHours(): void
    {
        $this->openingHours(0, '09:00', '19:00');
        $member = static::persist(MembershipBuilder::aMembership()->build());
        $this->aShift('2031-03-03 06:00', 120, $member);

        $status = $this->service()->getStatus(new \DateTime('2031-03-03 07:00'));

        $this->assertFalse($status['open']);
        $this->assertNull($status['next']);
    }

    private function shiftRepository(): ShiftRepository
    {
        return static::entityManager()->getRepository(Shift::class);
    }

    private function service(): OpeningHourService
    {
        return new OpeningHourService(static::entityManager());
    }

    private function aShift(string $start, int $minutes, $member): Shift
    {
        $builder = ShiftBuilder::aShift()->startingAt(new \DateTime($start))->lasting($minutes);
        if ($member) {
            $builder->bookedBy($member->getMainBeneficiary());
        }
        $shift = $builder->build();
        static::persist($shift->getJob(), $shift);

        return $shift;
    }

    /**
     * An enabled kind of opening hours with one slot on the given day (0 = Monday).
     * The fixtures' opening hours are not loaded here, see the class comment.
     */
    private function openingHours(int $dayOfWeek, string $start, string $end): void
    {
        $em = static::entityManager();
        // only this kind counts: the other kinds the database may hold are disabled
        foreach ($em->getRepository(OpeningHourKind::class)->findAll() as $other) {
            $other->setEnabled(false);
        }

        $kind = new OpeningHourKind();
        $kind->setName('test-kind');
        $kind->setEnabled(true);
        $openingHour = new OpeningHour();
        $openingHour->setKind($kind);
        $openingHour->setDayOfWeek($dayOfWeek);
        $openingHour->setStart(new \DateTime($start));
        $openingHour->setEnd(new \DateTime($end));
        $openingHour->setClosed(false);

        static::persist($kind, $openingHour);
    }
}
