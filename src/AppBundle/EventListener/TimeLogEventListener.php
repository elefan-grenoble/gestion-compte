<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
use AppBundle\Event\MemberCycleEndEvent;
use AppBundle\Event\MemberCycleStartEvent;
use AppBundle\Event\ShiftBookedEvent;
use AppBundle\Event\ShiftDeletedEvent;
use AppBundle\Event\ShiftDismissedEvent;
use AppBundle\Event\ShiftFreedEvent;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;

class TimeLogEventListener
{
    protected $em;
    protected $logger;
    protected $container;
    protected $due_duration_by_cycle;

    public function __construct(EntityManager $entityManager, Logger $logger, Container $container)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->container = $container;
        $this->due_duration_by_cycle = $this->container->getParameter('due_duration_by_cycle');
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
        $this->deleteShiftLogs($event->getShift(), $event->getMembership());
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

        if ($member->getFrozen()) {
            $this->createFrozenLog($member,$date);
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
            $dispatcher->dispatch(MemberCycleStartEvent::NAME, new MemberCycleStartEvent($member, $date));
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
        $log->setDescription(TimeLog::DESC_BOOKING);
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
        $log->setTime(-1 * $this->due_duration_by_cycle);
        $log->setDate($date);
        $log->setDescription("Début de cycle");
        $this->em->persist($log);

        $counter_today = $membership->getTimeCount($date);
        if ($counter_today > $this->due_duration_by_cycle) { //surbook
            $log = new TimeLog();
            $log->setMembership($membership);
            $log->setTime(-1 * ($counter_today - $this->due_duration_by_cycle));
            $log->setDate($date);
            $log->setDescription("Régulation du bénévolat facultatif");
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
        $log->setDescription("Début de cycle (compte gelé)");
        $this->em->persist($log);
        $this->em->flush();
    }

}
