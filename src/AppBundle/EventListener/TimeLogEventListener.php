<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
use AppBundle\Event\MemberCycleEndEvent;
use AppBundle\Event\MemberCycleStartEvent;
use AppBundle\Event\ShiftBookedEvent;
use AppBundle\Event\ShiftDeletedEvent;
use AppBundle\Event\ShiftFreedEvent;
use AppBundle\Event\ShiftValidatedEvent;
use AppBundle\Event\ShiftInvalidatedEvent;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;

/**
 * if the coop uses the card_reader (use_card_reader_to_validate_shifts = true)
 * - general rules:
 *  - shift time logs are created when shifts are validated (onShiftValidated)
 *  - shift time logs are never deleted (see onShiftInvalidated)
 * - more details:
 *  - booking a shift does not create a time log
 *  - the time log is created only when the shift is validated (with the card_reader) (with date = validation date)
 *  - when a shift is invalidated, we create an inverse time log (instead of deleting the existing time log)
 *
 * if the coop doesn't use the card_reader (use_card_reader_to_validate_shifts = false)
 * - general rules:
 *  - shift time logs are created when shifts are booked (onShiftBooked)
 *  - shift time logs are deleted when shifts are freed (see onShiftFreed)
 * - more details:
 *  - booking a shift creates the time log (with date = shift start_date)
 *  - a booked shift is validated by default
 *  - when a shift is freed, we delete the existing time log
 */
class TimeLogEventListener
{
    protected $em;
    protected $logger;
    protected $container;
    protected $due_duration_by_cycle;
    protected $cycle_duration;
    protected $registration_duration;
    protected $max_time_at_end_of_shift;
    protected $use_card_reader_to_validate_shifts;
    protected $use_time_log_saving;
    protected $time_log_saving_shift_free_min_time_in_advance_days;

    public function __construct(EntityManager $entityManager, Logger $logger, Container $container)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->container = $container;
        $this->due_duration_by_cycle = $this->container->getParameter('due_duration_by_cycle');
        $this->cycle_duration = $this->container->getParameter('cycle_duration');
        $this->registration_duration = $this->container->getParameter('registration_duration');
        $this->max_time_at_end_of_shift = $this->container->getParameter('max_time_at_end_of_shift');
        $this->use_card_reader_to_validate_shifts = $this->container->getParameter('use_card_reader_to_validate_shifts');
        $this->use_time_log_saving = $this->container->getParameter('use_time_log_saving');
        $this->time_log_saving_shift_free_min_time_in_advance_days = $this->container->getParameter('time_log_saving_shift_free_min_time_in_advance_days');
    }

    /**
     * @param ShiftBookedEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onShiftBooked(ShiftBookedEvent $event)
    {
        $this->logger->info("Time Log Listener: onShiftBooked");

        $shift = $event->getShift();

        if ($this->use_card_reader_to_validate_shifts) {
            // do nothing!
            // shiftTime log will be created in onShiftValidated
        } else {
            $this->createShiftValidatedTimeLog($shift, $shift->getStart());
        }
    }

    /**
     * @param ShiftValidatedEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onShiftValidated(ShiftValidatedEvent $event)
    {
        $this->logger->info("Time Log Listener: onShiftValidated");

        $shift = $event->getShift();

        if ($this->use_card_reader_to_validate_shifts) {
            $now = new \DateTime('now');
            // why $now? to avoid edge cases
            // example: if the shift is validated manually later, we might need to take it into account in the next cycle
            $this->createShiftValidatedTimeLog($shift, $now);

            // Saving account mode
            // extra time will go in the member's savingTime
            // if and only if:
            // - there is extra time
            if ($this->use_time_log_saving) {
                $this->em->refresh($shift);  // added to prevent from returning cached (old) data
                $member = $shift->getShifter()->getMembership();

                $now_plus_one_second = (clone $now)->modify("+1 second");
                $member_counter_time = $member->getShiftTimeCount($now_plus_one_second);  // $now_plus_one_second? to be sure we take the above log into account
                $member_counter_extra_time = $member_counter_time - ($this->due_duration_by_cycle + $this->max_time_at_end_of_shift);

                if ($member_counter_extra_time > 0) {
                    // first decrement the shiftTimeCount
                    $log = $this->container->get('time_log_service')->initRegulateOptionalShiftsTimeLog($member, -1 * $member_counter_extra_time, $now_plus_one_second);
                    $this->em->persist($log);
                    // then increment the savingTimeCount
                    // we don't pass de $shift info because the extra time may not correspond to the shift time
                    $log = $this->container->get('time_log_service')->initSavingTimeLog($member, $member_counter_extra_time, $now_plus_one_second);  # $shift
                    $this->em->persist($log);
                    $this->em->flush();
                }
            }
        } else {
            // do nothing! shouldn't happen (only onShiftBooked should be called)
            // shiftTime log already created in onShiftBooked

            // what about saving account mode?
            // see onMemberCycleEnd (createCycleBeginningLog)
        }
    }

    /**
     * @param ShiftInvalidatedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onShiftInvalidated(ShiftInvalidatedEvent $event)
    {
        $this->logger->info("Time Log Listener: onShiftInvalidated");

        $shift = $event->getShift();
        $member = $event->getBeneficiary()->getMembership();

        if ($this->use_card_reader_to_validate_shifts) {
            // the shift should have happened in the past
            // check that a TimeLog::TYPE_SHIFT_VALIDATED already exists
            // if true, create an inverse timelog
            $shiftValidatedTimeLog = $shift->getTimeLogs()->filter(function (TimeLog $log) use ($member) {
                return (($log->getType() == TimeLog::TYPE_SHIFT_VALIDATED) && ($log->getMembership() == $member));
            });
            if ($shiftValidatedTimeLog->count() > 0) {
                $this->createShiftInvalidatedTimeLog($shift, $member);
            }
        } else {
            // do nothing! shouldn't happen (only onShiftFreed should be called)
        }
    }

    /**
     * @param ShiftFreedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onShiftFreed(ShiftFreedEvent $event)
    {
        $this->logger->info("Time Log Listener: onShiftFreed");

        $shift = $event->getShift();
        $member = $event->getMember();

        if ($this->use_card_reader_to_validate_shifts) {
            // shiftTime logs?
            // no, they are created in onShiftValidated & onShiftInvalidated (should already be managed there)

            // Saving account mode
            // the shift's time can be "validated" and taken from the member's savingTime
            // if and only if:
            // - there is a min time in advance rule
            // - the shifter has enough time in its savingTime
            if ($this->use_time_log_saving) {
                $member_saving_now = $member->getSavingTimeCount();
                if ($this->time_log_saving_shift_free_min_time_in_advance_days && $shift->isBefore($this->time_log_saving_shift_free_min_time_in_advance_days . ' days')) {
                    // do nothing!
                    // too late to use the member's savingTime
                } elseif ($shift->getDuration() > $member_saving_now) {
                    // do nothing!
                    // the member's savingTime does not have enough time
                } else {
                    // decrement the savingTimeCount
                    $log = $this->container->get('time_log_service')->initSavingTimeLog($member, -1 * $shift->getDuration(), null, $shift);
                    $this->em->persist($log);
                    // increment the shiftTimeCount
                    $log = $this->container->get('time_log_service')->initShiftFreedSavingTimeLog($member, $shift->getDuration(), null, $shift);
                    $this->em->persist($log);
                    $this->em->flush();
                }
            }
        } else {
            $this->deleteShiftLogs($shift, $member);

            // what about saving account mode?
            // see onMemberCycleEnd (createCycleBeginningLog)
        }
    }

    /**
     * @param ShiftDeletedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onShiftDeleted(ShiftDeletedEvent $event)
    {
        $this->logger->info("Time Log Listener: onShiftDeleted");

        $shift = $event->getShift();
        $member = $event->getMember();

        if ($member) {
            $this->deleteShiftLogs($shift, $member);
        }
    }

    /**
     * @param MemberCycleEndEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    public function onMemberCycleEnd(MemberCycleEndEvent $event)
    {
        $this->logger->info("Time Log Listener: onMemberCycleEnd");

        $member = $event->getMembership();
        $date = $event->getDate();

        $registrationEnd = clone $member->getLastRegistration()->getDate();
        $registrationEnd->modify('+'.$this->registration_duration);
        $registrationEnd->modify('+'.$this->cycle_duration);

        if ($date > $registrationEnd) {
            $this->createRegistrationExpiredLog($member);
        } else if ($member->getFrozen()) {
            $this->createFrozenLog($member);
        } else if ($member->isCurrentlyExemptedFromShifts($date)) {
            $this->createExemptedLog($member);
        } else {
            $this->createCycleBeginningLog($member, $date);
        }

        if ($member->getFrozenChange()) {
            $member->setFrozen(!$member->getFrozen());
            $member->setFrozenChange(false);
            $this->em->persist($member);
        }

        if (!$member->getFrozen()) {
            $current_cycle_start = $this->container->get('membership_service')->getStartOfCycle($member, 0);
            $current_cycle_end = $this->container->get('membership_service')->getEndOfCycle($member, 0);
            $currentCycleShifts = $this->em->getRepository('AppBundle:Shift')->findShiftsForMembership($member, $current_cycle_start, $current_cycle_end);

            $dispatcher = $this->container->get('event_dispatcher');
            $dispatcher->dispatch(MemberCycleStartEvent::NAME, new MemberCycleStartEvent($member, $date, $currentCycleShifts));
        }
    }

    /**
     * @param Shift $shift
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createShiftValidatedTimeLog(Shift $shift, \DateTime $date = null, $description = null)
    {
        $log = $this->container->get('time_log_service')->initShiftValidatedTimeLog($shift, $date, $description);
        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * @param Shift $shift
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createShiftInvalidatedTimeLog(Shift $shift, Membership $member, \DateTime $date = null, $description = null)
    {
        $log = $this->container->get('time_log_service')->initShiftInvalidatedTimeLog($shift, $member, $date, $description);
        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * @param Shift $shift
     * @param Membership $member
     * @throws \Doctrine\ORM\ORMException
     */
    private function deleteShiftLogs(Shift $shift, Membership $member)
    {
        $logs = $shift->getTimeLogs();
        foreach ($logs as $log) {
            if ($log->getMembership() == $member) {
                $this->em->remove($log);
            }
        }
        $this->em->flush();
    }

    /**
     * @param Membership $member
     * @param \DateTime $date
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createCycleBeginningLog(Membership $member, \DateTime $date)
    {
        // decrease member shiftTime by due_duration_by_cycle
        $log = $this->container->get('time_log_service')->initCycleBeginningTimeLog($member, $date);
        $this->em->persist($log);
        $this->em->flush();

        $this->em->refresh($member);  // added to prevent from returning cached (old) data

        $date_plus_one_second = (clone $date)->modify("+1 second");
        $member_counter_time = $member->getShiftTimeCount($date_plus_one_second);  // $date_plus_one_second? to be sure we take the above log into account
        $member_counter_extra_time = $member_counter_time - $this->max_time_at_end_of_shift;  // not $this->due_duration_by_cycle? already substracted in the above log

        // member did extra work
        if ($member_counter_time > 0 && $member_counter_extra_time > 0) {
            // remove the extra_time from the shiftTime
            $log = $this->container->get('time_log_service')->initRegulateOptionalShiftsTimeLog($member, -1 * $member_counter_extra_time, $date_plus_one_second);
            $this->em->persist($log);
            if ($this->use_time_log_saving) {
                // add the extra_time to the savingTime
                $log = $this->container->get('time_log_service')->initSavingTimeLog($member, 1 * $member_counter_extra_time, $date_plus_one_second);
                $this->em->persist($log);
            }
        // member has a negative shiftTimeCount...
        } elseif ($member_counter_time < 0) {
            // we can *maybe* use the member's savingTime to bring his shiftTime back to 0
            if ($this->use_time_log_saving) {
                $member_saving_time = $member->getSavingTimeCount($date_plus_one_second);  // $date_plus_one_second? to be sure we take the above log into account
                if ($member_saving_time > 0) {
                    $date_minus_one_day = (clone $date)->modify("-1 days");
                    // count missed shifts in the previous cycle
                    $previous_cycle_missed_shifts_count = $this->container->get('membership_service')->getCycleShiftMissedCount($member, $date_minus_one_day);
                    // count freed shifts within the min_time_in_advance in the previous cycle
                    $previous_cycle_freed_shifts_less_than_min_time_in_advance_count = $this->container->get('membership_service')->getCycleShiftFreedCount($member, $date_minus_one_day, $this->time_log_saving_shift_free_min_time_in_advance_days);
                    // we can use the member's savings only if:
                    // - the member has no missed shifts in the previous cycle
                    // - the member has no freed shifts within the min_time_in_advance 
                    if ($previous_cycle_missed_shifts_count == 0 && $previous_cycle_freed_shifts_less_than_min_time_in_advance_count == 0) {
                        $missing_due_time = -1 * $member_counter_time;
                        $withdraw_from_saving = min($member_saving_time, $missing_due_time);
                        // first decrement the savingTime
                        $log = $this->container->get('time_log_service')->initSavingTimeLog($member, -1 * $withdraw_from_saving, $date_plus_one_second);
                        $this->em->persist($log);
                        // then increment the shiftTime
                        $log = $this->container->get('time_log_service')->initCycleEndSavingTimeLog($member, 1 * $withdraw_from_saving, $date_plus_one_second);
                        $this->em->persist($log);
                    } else {
                        // not allowed to use member's saving
                        // give explanation
                        $description = "(compteur épargne (" . $member_saving_time . " minutes) non utilisé car ";
                        if ($previous_cycle_missed_shifts_count) {
                            $description = $description . $previous_cycle_missed_shifts_count . " créneau" . (($previous_cycle_missed_shifts_count > 1) ? 'x' : '') . " raté" . (($previous_cycle_missed_shifts_count > 1) ? 's' : '');
                        }
                        if ($previous_cycle_freed_shifts_less_than_min_time_in_advance_count) {
                            $description = $description . (($previous_cycle_missed_shifts_count > 0) ? ' & ' : '') . $previous_cycle_freed_shifts_less_than_min_time_in_advance_count . " créneau" . (($previous_cycle_freed_shifts_less_than_min_time_in_advance_count > 1) ? 'x' : '') . " annulé" . (($previous_cycle_freed_shifts_less_than_min_time_in_advance_count > 1) ? 's' : '') . " sous les " . $this->time_log_saving_shift_free_min_time_in_advance_days . " jours)";
                        }
                        $log = $this->container->get('time_log_service')->initCycleEndSavingTimeLog($member, 0, $date_plus_one_second, $description);
                        $this->em->persist($log);
                    }
                }
            }
        }

        $this->em->flush();
    }

    /**
     * @param Membership $member
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createFrozenLog(Membership $member)
    {
        $log = new TimeLog();
        $log->setMembership($member);
        $log->setTime(0);
        $log->setType(TimeLog::TYPE_CYCLE_END_FROZEN);
        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * @param Membership $member
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createExemptedLog(Membership $member)
    {
        $log = new TimeLog();
        $log->setMembership($member);
        $log->setTime(0);
        $log->setType(TimeLog::TYPE_CYCLE_END_EXEMPTED);
        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * @param Membership $member
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createRegistrationExpiredLog(Membership $member)
    {
        $log = new TimeLog();
        $log->setMembership($member);
        $log->setTime(0);
        $log->setType(TimeLog::TYPE_CYCLE_END_EXPIRED_REGISTRATION);
        $this->em->persist($log);
        $this->em->flush();
    }
}
