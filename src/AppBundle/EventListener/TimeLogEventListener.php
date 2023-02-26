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
    protected $use_card_reader_to_validate_shifts;
    protected $max_time_at_end_of_shift;

    public function __construct(EntityManager $entityManager, Logger $logger, Container $container)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->container = $container;
        $this->due_duration_by_cycle = $this->container->getParameter('due_duration_by_cycle');
        $this->cycle_duration = $this->container->getParameter('cycle_duration');
        $this->registration_duration = $this->container->getParameter('registration_duration');
        $this->use_card_reader_to_validate_shifts = $this->container->getParameter('use_card_reader_to_validate_shifts');
        $this->max_time_at_end_of_shift = $this->container->getParameter('max_time_at_end_of_shift');
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
            // time log will be created in onShiftValidated
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
        } else {
            // do nothing!
            // time log already created in onShiftBooked
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
        $member = $event->getMember();

        if ($this->use_card_reader_to_validate_shifts) {
            // check that a TimeLog::TYPE_SHIFT_VALIDATED already exists
            // if true, create an inverse timelog
            $shiftValidatedTimeLog = $shift->getTimeLogs()->filter(function (TimeLog $log) use ($member) {
                return (($log->getType() == TimeLog::TYPE_SHIFT_VALIDATED) && ($log->getMembership() == $member));
            });
            if ($shiftValidatedTimeLog->count() > 0) {
                $this->createShiftInvalidatedTimeLog($shift);
            } else {
                // do nothing!
            }
        } else {
            // do nothing! shouldn't happen
            // for coops without card_reader, only onShiftFreed should be called
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
            // do nothing!
            // time logs are created in onShiftValidated & onShiftInvalidated (should already be managed there)
        } else {
            $this->deleteShiftLogs($shift, $member);
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

        $dispatcher = $this->container->get('event_dispatcher');
        if (!$member->getFrozen()) {
            $current_cycle_start = $this->container->get('membership_service')->getStartOfCycle($member, 0);
            $current_cycle_end = $this->container->get('membership_service')->getEndOfCycle($member, 0);
            $currentCycleShifts = $this->em->getRepository('AppBundle:Shift')->findShiftsForMembership($member, $current_cycle_start, $current_cycle_end);
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
    private function createShiftInvalidatedTimeLog(Shift $shift, \DateTime $date = null, $description = null)
    {
        $log = $this->container->get('time_log_service')->initShiftInvalidatedTimeLog($shift, $date, $description);
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
        $log = $this->container->get('time_log_service')->initCycleBeginningTimeLog($member);
        $this->em->persist($log);

        $counter_today = $member->getTimeCount($date);

        $allowed_cumul = $this->max_time_at_end_of_shift;

        if ($counter_today > ($this->due_duration_by_cycle + $allowed_cumul)) { //surbook
            $log = new TimeLog();
            $log->setMembership($member);
            $log->setTime(-1 * ($counter_today - ($this->due_duration_by_cycle + $allowed_cumul)));
            $log->setType(TimeLog::TYPE_CYCLE_END_REGULATE_OPTIONAL_SHIFTS);
            $this->em->persist($log);
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
