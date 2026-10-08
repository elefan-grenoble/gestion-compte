<?php

namespace App\Tests\Integration;

use App\Entity\Membership;
use App\Entity\MembershipShiftExemption;
use App\Entity\Shift;
use App\Entity\ShiftExemption;
use App\Entity\TimeLog;
use App\Event\MemberCycleEndEvent;
use App\Event\MemberCycleStartEvent;
use App\Event\ShiftBookedEvent;
use App\Event\ShiftDeletedEvent;
use App\Event\ShiftFreedEvent;
use App\Event\ShiftInvalidatedEvent;
use App\Event\ShiftValidatedEvent;
use App\EventListener\TimeLogEventListener;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\Builder\UniqueSequence;
use App\Tests\Support\PersistsEntities;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The time log listener is the cycle accounting of the cooperative: what it
 * writes (or deletes) decides who owes how many minutes. Each test feeds one
 * event to the listener and reads back the TimeLog rows left in the database.
 *
 * The listener reads its settings once, at construction, from the container
 * it is given. The tests build it over a small container of their own, to
 * run each accounting mode (card reader, time saving) whatever the .env says,
 * and over a dispatcher of their own, to observe the events it emits without
 * triggering the other listeners (the mails).
 *
 * @internal
 */
class TimeLogEventListenerTest extends KernelTestCase
{
    use PersistsEntities;

    private const DEFAULT_PARAMETERS = [
        'due_duration_by_cycle' => 180,
        'cycle_duration' => '28 days',
        'registration_duration' => '1 year',
        'max_time_at_end_of_shift' => 0,
        'use_card_reader_to_validate_shifts' => false,
        'use_time_log_saving' => false,
        'time_log_saving_shift_free_min_time_in_advance_days' => null,
    ];

    /** @var EventDispatcher */
    private $dispatcher;

    /** @var MemberCycleStartEvent[] */
    private $startEvents = [];

    protected function setUp(): void
    {
        static::bootKernel();

        $this->startEvents = [];
        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(MemberCycleStartEvent::NAME, function (MemberCycleStartEvent $event) {
            $this->startEvents[] = $event;
        });
    }

    // ---------------------------------------------------------------------
    // wiring
    // ---------------------------------------------------------------------

    /**
     * @dataProvider subscribedEventsProvider
     */
    public function testListenerIsSubscribedToTheAccountingEvents(string $eventName, string $method): void
    {
        $subscribed = [];
        foreach (static::$container->get('event_dispatcher')->getListeners($eventName) as $listener) {
            if (is_array($listener) && $listener[0] instanceof TimeLogEventListener) {
                $subscribed[] = $listener[1];
            }
        }

        $this->assertSame([$method], $subscribed);
    }

    public function subscribedEventsProvider(): array
    {
        return [
            ShiftBookedEvent::NAME => [ShiftBookedEvent::NAME, 'onShiftBooked'],
            ShiftFreedEvent::NAME => [ShiftFreedEvent::NAME, 'onShiftFreed'],
            ShiftDeletedEvent::NAME => [ShiftDeletedEvent::NAME, 'onShiftDeleted'],
            ShiftValidatedEvent::NAME => [ShiftValidatedEvent::NAME, 'onShiftValidated'],
            ShiftInvalidatedEvent::NAME => [ShiftInvalidatedEvent::NAME, 'onShiftInvalidated'],
            MemberCycleEndEvent::NAME => [MemberCycleEndEvent::NAME, 'onMemberCycleEnd'],
        ];
    }

    // ---------------------------------------------------------------------
    // shift booked
    // ---------------------------------------------------------------------

    public function testBookingAShiftLogsItsDurationAtItsStart(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('2030-03-04 09:30'), 90);
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener()->onShiftBooked(new ShiftBookedEvent($shift, false));

        $logs = $this->logsOf($member);
        $this->assertCount(1, $logs);
        $this->assertSame(TimeLog::TYPE_SHIFT_VALIDATED, $logs[0]->getType());
        $this->assertSame(90, $logs[0]->getTime());
        $this->assertSame($shift->getId(), $logs[0]->getShift()->getId());
        // dated at the shift, not at the booking
        $this->assertSame('2030-03-04 09:30', $logs[0]->getCreatedAt()->format('Y-m-d H:i'));
    }

    public function testBookingAShiftLogsNothingWithTheCardReader(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('2030-03-04 09:30'));
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener(['use_card_reader_to_validate_shifts' => true])->onShiftBooked(new ShiftBookedEvent($shift, false));

        $this->assertLogs([], $member);
    }

    // ---------------------------------------------------------------------
    // shift validated
    // ---------------------------------------------------------------------

    public function testValidatingAShiftLogsNothingWithoutTheCardReader(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('2030-03-04 09:30'));
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener()->onShiftValidated(new ShiftValidatedEvent($shift));

        $this->assertLogs([], $member);
    }

    public function testValidatingAShiftWithTheCardReaderLogsItsDurationNow(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('2030-03-04 09:30'), 90);
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener(['use_card_reader_to_validate_shifts' => true])->onShiftValidated(new ShiftValidatedEvent($shift));

        $logs = $this->logsOf($member);
        $this->assertCount(1, $logs);
        $this->assertSame(TimeLog::TYPE_SHIFT_VALIDATED, $logs[0]->getType());
        $this->assertSame(90, $logs[0]->getTime());
        $this->assertSame($shift->getId(), $logs[0]->getShift()->getId());
        // dated at the validation, which may be long after the shift
        $this->assertEqualsWithDelta(time(), $logs[0]->getCreatedAt()->getTimestamp(), 10);
    }

    public function testValidatingAShiftMovesTheExtraTimeToTheSavingCounter(): void
    {
        $member = $this->aMember();
        $this->aLog($member, TimeLog::TYPE_CUSTOM, 400, new \DateTime('-2 days'));
        $shift = $this->aBookedShift($member, new \DateTime('-1 day 09:00'));
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener([
            'use_card_reader_to_validate_shifts' => true,
            'use_time_log_saving' => true,
        ])->onShiftValidated(new ShiftValidatedEvent($shift));

        // 400 + 180 minutes counted, 180 due: 400 minutes are extra
        $this->assertLogs([
            [TimeLog::TYPE_CUSTOM, 400],
            [TimeLog::TYPE_SHIFT_VALIDATED, 180],
            [TimeLog::TYPE_REGULATE_OPTIONAL_SHIFTS, -400],
            [TimeLog::TYPE_SAVING, 400],
        ], $member);
        $this->assertSame(180, $this->shiftTimeCountOf($member));
        $this->assertSame(400, $this->savingTimeCountOf($member));
    }

    public function testValidatingAShiftKeepsTheTimeWhenThereIsNoExtraTime(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('-1 day 09:00'));
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener([
            'use_card_reader_to_validate_shifts' => true,
            'use_time_log_saving' => true,
        ])->onShiftValidated(new ShiftValidatedEvent($shift));

        $this->assertLogs([[TimeLog::TYPE_SHIFT_VALIDATED, 180]], $member);
    }

    // ---------------------------------------------------------------------
    // shift invalidated
    // ---------------------------------------------------------------------

    public function testInvalidatingAValidatedShiftWithTheCardReaderLogsTheInverseTime(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('-1 day 09:00'), 120);
        $this->aLog($member, TimeLog::TYPE_SHIFT_VALIDATED, 120, new \DateTime('-1 day'), $shift);
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener(['use_card_reader_to_validate_shifts' => true])
            ->onShiftInvalidated(new ShiftInvalidatedEvent($shift, $member->getMainBeneficiary()))
        ;

        // the validated log stays: accounting history is never rewritten
        $this->assertLogs([
            [TimeLog::TYPE_SHIFT_VALIDATED, 120],
            [TimeLog::TYPE_SHIFT_INVALIDATED, -120],
        ], $member);
        $this->assertSame(0, $this->shiftTimeCountOf($member));
    }

    public function testInvalidatingAShiftThatWasNeverValidatedLogsNothing(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('-1 day 09:00'));
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener(['use_card_reader_to_validate_shifts' => true])
            ->onShiftInvalidated(new ShiftInvalidatedEvent($shift, $member->getMainBeneficiary()))
        ;

        $this->assertLogs([], $member);
    }

    public function testInvalidatingAShiftLogsNothingWithoutTheCardReader(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('-1 day 09:00'));
        $this->aLog($member, TimeLog::TYPE_SHIFT_VALIDATED, 180, new \DateTime('-1 day 09:00'), $shift);
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener()->onShiftInvalidated(new ShiftInvalidatedEvent($shift, $member->getMainBeneficiary()));

        $this->assertLogs([[TimeLog::TYPE_SHIFT_VALIDATED, 180]], $member);
    }

    // ---------------------------------------------------------------------
    // shift freed
    // ---------------------------------------------------------------------

    public function testFreeingABookedShiftDeletesItsLog(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('2030-03-04 09:30'));
        [$member, $shift] = $this->reload($member, $shift);
        $listener = $this->listener();
        $listener->onShiftBooked(new ShiftBookedEvent($shift, false));
        $this->assertCount(1, $this->logsOf($member));

        // the freeing happens in another request than the booking
        [$member, $shift] = $this->reload($member, $shift);
        $listener->onShiftFreed(new ShiftFreedEvent($shift, $member->getMainBeneficiary()));

        $this->assertLogs([], $member);
    }

    public function testFreeingAShiftKeepsTheLogsOfOtherMembers(): void
    {
        $member = $this->aMember();
        $other = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('2030-03-04 09:30'));
        $this->aLog($member, TimeLog::TYPE_SHIFT_VALIDATED, 180, new \DateTime('2030-03-04 09:30'), $shift);
        $this->aLog($other, TimeLog::TYPE_SHIFT_VALIDATED, 180, new \DateTime('2030-03-04 09:30'), $shift);
        [$member, $other, $shift] = $this->reload($member, $other, $shift);

        $this->listener()->onShiftFreed(new ShiftFreedEvent($shift, $member->getMainBeneficiary()));

        $this->assertLogs([], $member);
        $this->assertLogs([[TimeLog::TYPE_SHIFT_VALIDATED, 180]], $other);
    }

    public function testFreeingAShiftKeepsItsLogWithTheCardReader(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('-1 day 09:00'));
        $this->aLog($member, TimeLog::TYPE_SHIFT_VALIDATED, 180, new \DateTime('-1 day'), $shift);
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener(['use_card_reader_to_validate_shifts' => true])
            ->onShiftFreed(new ShiftFreedEvent($shift, $member->getMainBeneficiary()))
        ;

        $this->assertLogs([[TimeLog::TYPE_SHIFT_VALIDATED, 180]], $member);
    }

    public function testFreeingAShiftPaysItWithTheSavingTime(): void
    {
        $member = $this->aMember();
        $this->aLog($member, TimeLog::TYPE_SAVING, 300, new \DateTime('-10 days'));
        $shift = $this->aBookedShift($member, new \DateTime('+10 days 09:00'));
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener($this->savingMode(['time_log_saving_shift_free_min_time_in_advance_days' => 2]))
            ->onShiftFreed(new ShiftFreedEvent($shift, $member->getMainBeneficiary()))
        ;

        $this->assertLogs([
            [TimeLog::TYPE_SAVING, 300],
            [TimeLog::TYPE_SAVING, -180],
            [TimeLog::TYPE_SHIFT_FREED_SAVING, 180],
        ], $member);
        $this->assertSame(120, $this->savingTimeCountOf($member));
        $this->assertSame(180, $this->shiftTimeCountOf($member));
    }

    public function testFreeingAShiftDoesNotUseTheSavingTimeWhenItIsTooLate(): void
    {
        $member = $this->aMember();
        $this->aLog($member, TimeLog::TYPE_SAVING, 300, new \DateTime('-10 days'));
        $shift = $this->aBookedShift($member, new \DateTime('tomorrow 09:00'));
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener($this->savingMode(['time_log_saving_shift_free_min_time_in_advance_days' => 3]))
            ->onShiftFreed(new ShiftFreedEvent($shift, $member->getMainBeneficiary()))
        ;

        $this->assertLogs([[TimeLog::TYPE_SAVING, 300]], $member);
    }

    public function testFreeingAShiftDoesNotUseTheSavingTimeWhenThereIsNotEnough(): void
    {
        $member = $this->aMember();
        $this->aLog($member, TimeLog::TYPE_SAVING, 100, new \DateTime('-10 days'));
        $shift = $this->aBookedShift($member, new \DateTime('+10 days 09:00'));
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener($this->savingMode(['time_log_saving_shift_free_min_time_in_advance_days' => 2]))
            ->onShiftFreed(new ShiftFreedEvent($shift, $member->getMainBeneficiary()))
        ;

        $this->assertLogs([[TimeLog::TYPE_SAVING, 100]], $member);
    }

    // ---------------------------------------------------------------------
    // shift deleted
    // ---------------------------------------------------------------------

    public function testDeletingABookedShiftDeletesTheLogOfItsShifter(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('2030-03-04 09:30'));
        $this->aLog($member, TimeLog::TYPE_SHIFT_VALIDATED, 180, new \DateTime('2030-03-04 09:30'), $shift);
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener()->onShiftDeleted(new ShiftDeletedEvent($shift, $member->getMainBeneficiary()));

        $this->assertLogs([], $member);
    }

    public function testDeletingAFreeShiftLeavesTheLogsAlone(): void
    {
        $member = $this->aMember();
        $shift = $this->aBookedShift($member, new \DateTime('2030-03-04 09:30'));
        $this->aLog($member, TimeLog::TYPE_SHIFT_VALIDATED, 180, new \DateTime('2030-03-04 09:30'), $shift);
        [$member, $shift] = $this->reload($member, $shift);

        $this->listener()->onShiftDeleted(new ShiftDeletedEvent($shift));

        $this->assertLogs([[TimeLog::TYPE_SHIFT_VALIDATED, 180]], $member);
    }

    // ---------------------------------------------------------------------
    // member cycle end
    // ---------------------------------------------------------------------

    public function testCycleEndChargesTheDueTimeAndStartsTheNextCycle(): void
    {
        $member = $this->aMember();
        $date = $this->cycleStart($member)->modify('+3 days');
        [$member] = $this->reload($member);

        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        $logs = $this->logsOf($member);
        $this->assertCount(1, $logs);
        $this->assertSame(TimeLog::TYPE_CYCLE_END, $logs[0]->getType());
        $this->assertSame(-180, $logs[0]->getTime());
        $this->assertEquals($date, $logs[0]->getCreatedAt());
        $this->assertCount(1, $this->startEvents);
        $this->assertSame($member->getId(), $this->startEvents[0]->getMembership()->getId());
        $this->assertEquals($date, $this->startEvents[0]->getDate());
    }

    public function testCycleEndHandsTheShiftsOfTheCurrentCycleToTheStartEvent(): void
    {
        $member = $this->aMember();
        $cycleStart = $this->cycleStart($member);
        $inCycle = $this->aBookedShift($member, (clone $cycleStart)->modify('+2 days 09:00'));
        $nextCycle = $this->aBookedShift($member, (clone $cycleStart)->modify('+30 days 09:00'));
        [$member, $inCycle] = $this->reload($member, $inCycle);

        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, (clone $cycleStart)->modify('+3 days')));

        $this->assertCount(1, $this->startEvents);
        $ids = [];
        foreach ($this->startEvents[0]->getCurrentCycleShifts() as $shift) {
            $ids[] = $shift->getId();
        }
        $this->assertSame([$inCycle->getId()], $ids);
        $this->assertNotContains($nextCycle->getId(), $ids);
    }

    public function testCycleEndTakesTheExtraTimeOffTheCounter(): void
    {
        $member = $this->aMember();
        $date = $this->cycleStart($member)->modify('+3 days');
        $this->aLog($member, TimeLog::TYPE_CUSTOM, 400, (clone $date)->modify('-1 day'));
        [$member] = $this->reload($member);

        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        // 400 - 180 = 220 minutes of extra work, which do not carry over
        $this->assertLogs([
            [TimeLog::TYPE_CUSTOM, 400],
            [TimeLog::TYPE_CYCLE_END, -180],
            [TimeLog::TYPE_REGULATE_OPTIONAL_SHIFTS, -220],
        ], $member);
        $this->assertSame(0, $this->shiftTimeCountOf($member));
    }

    public function testCycleEndLetsTheMemberKeepTheTimeAllowedAtTheEndOfTheCycle(): void
    {
        $member = $this->aMember();
        $date = $this->cycleStart($member)->modify('+3 days');
        $this->aLog($member, TimeLog::TYPE_CUSTOM, 400, (clone $date)->modify('-1 day'));
        [$member] = $this->reload($member);

        $this->listener(['max_time_at_end_of_shift' => 60])->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        $this->assertLogs([
            [TimeLog::TYPE_CUSTOM, 400],
            [TimeLog::TYPE_CYCLE_END, -180],
            [TimeLog::TYPE_REGULATE_OPTIONAL_SHIFTS, -160],
        ], $member);
        $this->assertSame(60, $this->shiftTimeCountOf($member));
    }

    public function testCycleEndPutsTheExtraTimeInTheSavingCounterWhenSavingIsOn(): void
    {
        $member = $this->aMember();
        $date = $this->cycleStart($member)->modify('+3 days');
        $this->aLog($member, TimeLog::TYPE_CUSTOM, 400, (clone $date)->modify('-1 day'));
        [$member] = $this->reload($member);

        $this->listener($this->savingMode())->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        $this->assertLogs([
            [TimeLog::TYPE_CUSTOM, 400],
            [TimeLog::TYPE_CYCLE_END, -180],
            [TimeLog::TYPE_REGULATE_OPTIONAL_SHIFTS, -220],
            [TimeLog::TYPE_SAVING, 220],
        ], $member);
    }

    public function testCycleEndLeavesAMissingTimeOnTheCounter(): void
    {
        $member = $this->aMember();
        $date = $this->cycleStart($member)->modify('+3 days');
        [$member] = $this->reload($member);

        $this->listener($this->savingMode())->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        // nothing saved, nothing to withdraw
        $this->assertLogs([[TimeLog::TYPE_CYCLE_END, -180]], $member);
        $this->assertSame(-180, $this->shiftTimeCountOf($member));
    }

    public function testCycleEndCoversTheMissingTimeWithTheSavingTime(): void
    {
        $member = $this->aMember();
        $date = $this->cycleStart($member)->modify('+3 days');
        $this->aLog($member, TimeLog::TYPE_SAVING, 100, (clone $date)->modify('-10 days'));
        [$member] = $this->reload($member);

        $this->listener($this->savingMode())->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        $this->assertLogs([
            [TimeLog::TYPE_SAVING, 100],
            [TimeLog::TYPE_CYCLE_END, -180],
            [TimeLog::TYPE_SAVING, -100],
            [TimeLog::TYPE_CYCLE_END_SAVING, 100],
        ], $member);
        $this->assertSame(0, $this->savingTimeCountOf($member));
        $this->assertSame(-80, $this->shiftTimeCountOf($member));
    }

    public function testCycleEndNeverWithdrawsMoreThanTheMissingTime(): void
    {
        $member = $this->aMember();
        $date = $this->cycleStart($member)->modify('+3 days');
        $this->aLog($member, TimeLog::TYPE_SAVING, 500, (clone $date)->modify('-10 days'));
        [$member] = $this->reload($member);

        $this->listener($this->savingMode())->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        $this->assertSame(320, $this->savingTimeCountOf($member));
        $this->assertSame(0, $this->shiftTimeCountOf($member));
    }

    /**
     * @dataProvider missedShiftsProvider
     */
    public function testCycleEndKeepsTheSavingTimeAfterMissedShifts(int $missed, string $explanation): void
    {
        $member = $this->aMember();
        $cycleStart = $this->cycleStart($member);
        $date = (clone $cycleStart)->modify('+3 days');
        $this->aLog($member, TimeLog::TYPE_SAVING, 100, (clone $date)->modify('-10 days'));
        for ($i = 0; $i < $missed; ++$i) {
            // booked, in the cycle of the day before the cycle end, and not attended
            $this->aBookedShift($member, (clone $cycleStart)->modify('+2 days ' . (8 + 4 * $i) . ':00'));
        }
        [$member] = $this->reload($member);

        $this->listener($this->savingMode())->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        $logs = $this->logsOf($member);
        $this->assertSame(
            [[TimeLog::TYPE_SAVING, 100], [TimeLog::TYPE_CYCLE_END, -180], [TimeLog::TYPE_CYCLE_END_SAVING, 0]],
            array_map(function (TimeLog $log) {
                return [$log->getType(), $log->getTime()];
            }, $logs)
        );
        $this->assertStringStartsWith($explanation, $logs[2]->getDescription());
        $this->assertSame(100, $this->savingTimeCountOf($member));
    }

    public function missedShiftsProvider(): array
    {
        return [
            'one missed shift' => [1, '(compteur épargne (100 minutes) non utilisé car 1 créneau raté'],
            'several missed shifts' => [2, '(compteur épargne (100 minutes) non utilisé car 2 créneaux ratés'],
        ];
    }

    public function testCycleEndOfAnExpiredRegistrationOnlyMarksIt(): void
    {
        $member = $this->aMember(function (MembershipBuilder $builder) {
            $builder->registeredOn(new \DateTime('2029-01-10'));
        });
        // registration + 1 year + one cycle ends on 2030-02-07
        [$member] = $this->reload($member);

        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, new \DateTime('2030-02-08')));

        $this->assertLogs([[TimeLog::TYPE_CYCLE_END_EXPIRED_REGISTRATION, 0]], $member);
    }

    public function testCycleEndOnTheLastDayOfTheRegistrationGracePeriodStillCharges(): void
    {
        $member = $this->aMember(function (MembershipBuilder $builder) {
            $builder->registeredOn(new \DateTime('2029-01-10'));
        });
        [$member] = $this->reload($member);

        // the limit itself is not expired: 2029-01-10 + 1 year + 28 days
        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, new \DateTime('2030-02-07')));

        $this->assertLogs([[TimeLog::TYPE_CYCLE_END, -180]], $member);
    }

    public function testCycleEndUsesTheLastRegistration(): void
    {
        $member = $this->aMember(function (MembershipBuilder $builder) {
            $builder->registeredOn(new \DateTime('2027-01-10'), new \DateTime('2029-06-01'));
        });
        [$member] = $this->reload($member);

        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, new \DateTime('2030-02-08')));

        $this->assertLogs([[TimeLog::TYPE_CYCLE_END, -180]], $member);
    }

    public function testCycleEndOfAFrozenMemberChargesNothingAndDoesNotStartACycle(): void
    {
        $member = $this->aMember(function (MembershipBuilder $builder) {
            $builder->frozen();
        });
        $date = $this->cycleStart($member)->modify('+3 days');
        [$member] = $this->reload($member);

        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        $this->assertLogs([[TimeLog::TYPE_CYCLE_END_FROZEN, 0]], $member);
        $this->assertSame([], $this->startEvents);
    }

    public function testCycleEndOfAnExemptedMemberChargesNothing(): void
    {
        $member = $this->aMember();
        $date = $this->cycleStart($member)->modify('+3 days');
        $exemption = new ShiftExemption();
        $exemption->setName('lot5-exemption-' . UniqueSequence::next());
        $membershipExemption = new MembershipShiftExemption();
        $membershipExemption->setShiftExemption($exemption);
        $membershipExemption->setDescription('travelling');
        $membershipExemption->setMembership($member);
        $membershipExemption->setStart((clone $date)->modify('-1 month'));
        $membershipExemption->setEnd((clone $date)->modify('+1 month'));
        $member->getMembershipShiftExemptions()->add($membershipExemption);
        static::persist($exemption, $member);
        [$member] = $this->reload($member);

        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        $this->assertLogs([[TimeLog::TYPE_CYCLE_END_EXEMPTED, 0]], $member);
        $this->assertCount(1, $this->startEvents, 'an exempted member still gets a new cycle');
    }

    public function testCycleEndAppliesAPendingFreeze(): void
    {
        $member = $this->aMember(function (MembershipBuilder $builder) {
            $builder->frozen(false);
        });
        $member->setFrozenChange(true);
        $date = $this->cycleStart($member)->modify('+3 days');
        static::persist($member);
        [$member] = $this->reload($member);

        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        // the cycle that ends is charged, then the member is frozen for the next one
        $this->assertLogs([[TimeLog::TYPE_CYCLE_END, -180]], $member);
        $this->assertTrue($member->getFrozen());
        $this->assertFalse($member->getFrozenChange());
        $this->assertSame([], $this->startEvents, 'a member frozen for the next cycle gets no start event');
    }

    public function testCycleEndAppliesAPendingUnfreeze(): void
    {
        $member = $this->aMember(function (MembershipBuilder $builder) {
            $builder->frozen();
        });
        $member->setFrozenChange(true);
        $date = $this->cycleStart($member)->modify('+3 days');
        static::persist($member);
        [$member] = $this->reload($member);

        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, $date));

        // the cycle that ends was frozen, the next one is not
        $this->assertLogs([[TimeLog::TYPE_CYCLE_END_FROZEN, 0]], $member);
        $this->assertFalse($member->getFrozen());
        $this->assertFalse($member->getFrozenChange());
        $this->assertCount(1, $this->startEvents);
    }

    /**
     * The toggle is persisted but never flushed: the cycle start command
     * does not flush either, so the last member of a run keeps the old state
     * in the database and is toggled again at the next cycle.
     */
    public function testCycleEndSavesAPendingFreezeToTheDatabase(): void
    {
        $this->markTestIncomplete('Open (not in TODO-PRIORISEE yet): TimeLogEventListener::onMemberCycleEnd() persists the frozen toggle without flushing, and CycleStartCommand never flushes.');

        $member = $this->aMember();
        $member->setFrozenChange(true);
        static::persist($member);
        [$member] = $this->reload($member);

        $this->listener()->onMemberCycleEnd(new MemberCycleEndEvent($member, $this->cycleStart($member)->modify('+3 days')));

        [$member] = $this->reload($member);
        $this->assertTrue($member->getFrozen());
        $this->assertFalse($member->getFrozenChange());
    }

    // ---------------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------------

    /**
     * Builds the listener over a container of its own: the given parameters
     * on top of the defaults, the real accounting services, and this test's
     * dispatcher.
     */
    private function listener(array $parameters = []): TimeLogEventListener
    {
        $real = static::$kernel->getContainer();

        $container = new Container(new ParameterBag($parameters + self::DEFAULT_PARAMETERS));
        $container->set('time_log_service', $real->get('time_log_service'));
        $container->set('membership_service', $real->get('membership_service'));
        $container->set('event_dispatcher', $this->dispatcher);

        $logger = new Logger('test', [new NullHandler()]);

        return new TimeLogEventListener(static::entityManager(), $logger, $container);
    }

    private function savingMode(array $parameters = []): array
    {
        return $parameters + [
            'use_card_reader_to_validate_shifts' => true,
            'use_time_log_saving' => true,
            'time_log_saving_shift_free_min_time_in_advance_days' => null,
        ];
    }

    /**
     * @param null|callable(MembershipBuilder): void $customize
     */
    private function aMember(?callable $customize = null): Membership
    {
        $builder = MembershipBuilder::aMembership();
        if ($customize) {
            $customize($builder);
        }

        return static::persist($builder->build());
    }

    private function aBookedShift(Membership $member, \DateTime $start, int $minutes = 180): Shift
    {
        $shift = ShiftBuilder::aShift()
            ->startingAt($start)
            ->lasting($minutes)
            ->bookedBy($member->getMainBeneficiary())
            ->build()
        ;
        static::persist($shift->getJob(), $shift);

        return $shift;
    }

    private function aLog(Membership $member, int $type, int $time, \DateTime $date, ?Shift $shift = null): TimeLog
    {
        $log = new TimeLog();
        $log->setMembership($member);
        $log->setType($type);
        $log->setTime($time);
        $log->setCreatedAt($date);
        $log->setShift($shift);

        return static::persist($log);
    }

    /**
     * Start of the member's current cycle, as the application computes it.
     */
    private function cycleStart(Membership $member): \DateTime
    {
        return clone static::$kernel->getContainer()->get('membership_service')->getStartOfCycle($member, 0);
    }

    /**
     * Reloads the entities from the database in a clean entity manager, as
     * the request that handles the event would find them.
     *
     * @return object[]
     */
    private function reload(object ...$entities): array
    {
        $em = static::entityManager();
        $references = array_map(function (object $entity) {
            return [get_class($entity), $entity->getId()];
        }, $entities);

        $em->clear();

        return array_map(function (array $reference) use ($em) {
            return $em->find($reference[0], $reference[1]);
        }, $references);
    }

    private function shiftTimeCountOf(Membership $member): int
    {
        return $this->reload($member)[0]->getShiftTimeCount();
    }

    private function savingTimeCountOf(Membership $member): int
    {
        return $this->reload($member)[0]->getSavingTimeCount();
    }

    /**
     * @return TimeLog[]
     */
    private function logsOf(Membership $member): array
    {
        return static::entityManager()->getRepository(TimeLog::class)->findBy(['membership' => $member], ['id' => 'ASC']);
    }

    /**
     * @param array<array{int, int}> $expected [type, time] pairs, in creation order
     */
    private function assertLogs(array $expected, Membership $member): void
    {
        $this->assertSame($expected, array_map(function (TimeLog $log) {
            return [$log->getType(), $log->getTime()];
        }, $this->logsOf($member)));
    }
}
