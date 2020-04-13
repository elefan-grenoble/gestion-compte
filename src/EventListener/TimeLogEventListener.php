<?php

namespace App\EventListener;

use App\Entity\Membership;
use App\Entity\Shift;
use App\Entity\TimeLog;
use App\Event\MemberCycleEndEvent;
use App\Event\MemberCycleStartEvent;
use App\Event\ShiftBookedEvent;
use App\Event\ShiftDeletedEvent;
use App\Event\ShiftDismissedEvent;
use App\Event\ShiftFreedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TimeLogEventListener
{
    protected $em;
    protected $logger;
    protected $container;
    /**
     * @var string
     */
    private $dueDurationByCycle;
    /**
     * @var string
     */
    private $registrationDuration;
    /**
     * @var string
     */
    private $cycleDuration;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger, EventDispatcherInterface $eventDispatcher, string $dueDurationByCycle, string $registrationDuration, string $cycleDuration)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->dueDurationByCycle = $dueDurationByCycle;
        $this->registrationDuration = $registrationDuration;
        $this->cycleDuration = $cycleDuration;
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
        $this->createShiftLog($shift);
    }

    /**
     * @param ShiftFreedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onShiftFreed(ShiftFreedEvent $event)
    {
        $this->logger->info("Time Log Listener: onShiftFreed");
        $this->deleteShiftLogs($event->getShift(), $event->getMembership());
    }

    /**
     * @param ShiftDeletedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onShiftDeleted(ShiftDeletedEvent $event)
    {
        $this->logger->info("Time Log Listener: onShiftDeleted");
        $shift = $event->getShift();
        if ($shift->getShifter()) {
            $this->deleteShiftLogs($shift, $shift->getShifter()->getMembership());
        }
    }

    /**
     * @param ShiftDismissedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onShiftDismissed(ShiftDismissedEvent $event)
    {
        $this->logger->info("Time Log Listener: onShiftDismissed");
        $this->deleteShiftLogs($event->getShift(), $event->getBeneficiary()->getMembership());
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
        $registrationEnd->modify('+'.$this->registrationDuration);
        $registrationEnd->modify('+'.$this->cycleDuration);
        
        if ($date > $registrationEnd) {
            $this->createRegistrationExpiredLog($member,$date);
        } else if ($member->getFrozen()) {
            $this->createFrozenLog($member,$date);
        } else {
            $this->createCycleBeginningLog($member, $date);
        }

        if ($member->getFrozenChange()) {
            $member->setFrozen(!$member->getFrozen());
            $member->setFrozenChange(false);
            $this->em->persist($member);
        }

        if (!$member->getFrozen()) {
            $this->eventDispatcher->dispatch(MemberCycleStartEvent::NAME, new MemberCycleStartEvent($member, $date));
        }
    }

    /**
     * @param Shift $shift
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createShiftLog(Shift $shift)
    {
        $log = new TimeLog();
        $log->setMembership($shift->getShifter()->getMembership());
        $log->setTime($shift->getDuration());
        $log->setShift($shift);
        $log->setDate($shift->getStart());
        $log->setType(TimeLog::TYPE_SHIFT);
        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * @param Shift $shift
     * @param Membership $membership
     * @throws \Doctrine\ORM\ORMException
     */
    private function deleteShiftLogs(Shift $shift, Membership $membership)
    {
        $logs = $shift->getTimeLogs();
        foreach ($logs as $log) {
            if ($log->getMembership()->getId() == $membership->getId()) {
                $this->em->remove($log);
            }
        }
        $this->em->flush();
    }

    /**
     * @param Membership $membership
     * @param \DateTime $date
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createCycleBeginningLog(Membership $membership, \DateTime $date)
    {
        $log = new TimeLog();
        $log->setMembership($membership);
        $log->setTime(-1 * $this->dueDurationByCycle);
        $log->setDate($date);
        $log->setType(TimeLog::TYPE_CYCLE_END);
        $this->em->persist($log);

        $counter_today = $membership->getTimeCount($date);
        if ($counter_today > $this->dueDurationByCycle) { //surbook
            $log = new TimeLog();
            $log->setMembership($membership);
            $log->setTime(-1 * ($counter_today - $this->dueDurationByCycle));
            $log->setDate($date);
            $log->setType(TimeLog::TYPE_CYCLE_END_REGULATE_OPTIONAL_SHIFTS);
            $this->em->persist($log);
        }
        $this->em->flush();
    }

    /**
     * @param Membership $membership
     * @param \DateTime $date
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createFrozenLog(Membership $membership, \DateTime $date)
    {
        $log = new TimeLog();
        $log->setMembership($membership);
        $log->setTime(0);
        $log->setDate($date);
        $log->setType(TimeLog::TYPE_CYCLE_END_FROZEN);
        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * @param Membership $membership
     * @param \DateTime $date
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createRegistrationExpiredLog(Membership $membership, \DateTime $date)
    {
        $log = new TimeLog();
        $log->setMembership($membership);
        $log->setTime(0);
        $log->setDate($date);
        $log->setType(TimeLog::TYPE_CYCLE_END_EXPIRED_REGISTRATION);
        $this->em->persist($log);
        $this->em->flush();
    }

}
