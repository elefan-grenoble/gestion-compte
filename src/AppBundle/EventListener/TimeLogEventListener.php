<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
use AppBundle\Entity\User;
use AppBundle\Event\MemberCycleEndEvent;
use AppBundle\Event\ShiftBookedEvent;
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
     * @param ShiftDismissedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onShiftDismissed(ShiftDismissedEvent $event)
    {
        $this->logger->info("Time Log Listener: onShiftDismissed");
        $this->deleteShiftLogs($event->getShift(), $event->getUser());
    }

    /**
     * @param MemberCycleEndEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onMemberCycleEnd(MemberCycleEndEvent $event)
    {
        $this->logger->info("Time Log Listener: onMemberCycleStart");
        $this->createCycleBeginningLog($event->getMembership(), $event->getDate());
    }

    /**
     * @param Shift $shift
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createShiftLog(Shift $shift)
    {
        $log = new TimeLog();
        $log->setUser($shift->getShifter()->getUser());
        $log->setTime($shift->getDuration());
        $log->setShift($shift);
        $log->setDate($shift->getStart());
        $log->setDescription(TimeLog::DESC_BOOKING);
        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * @param Shift $shift
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     */
    private function deleteShiftLogs(Shift $shift, User $user)
    {
        $logs = $shift->getTimeLogs();
        foreach ($logs as $log) {
            if ($log->getUser()->getId() == $user->getId()) {
                $this->em->remove($log);
            }
        }
        $this->em->flush();
    }

    /**
     * @param User $user
     * @param \DateTime $date
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createCycleBeginningLog(User $user, \DateTime $date)
    {
        $log = new TimeLog();
        $log->setUser($user);
        $log->setTime(-1 * $this->due_duration_by_cycle);
        $log->setDate($date);
        $log->setDescription("Début de cycle");
        $this->em->persist($log);

        $counter_today = $user->getTimeCount($date);
        if ($counter_today > $this->due_duration_by_cycle) { //surbook
            $log = new TimeLog();
            $log->setUser($user);
            $log->setTime(-1 * ($counter_today - $this->due_duration_by_cycle));
            $log->setDate($date);
            $log->setDescription("Régulation du bénévolat facultatif");
            $this->em->persist($log);
        }
        $this->em->flush();
    }

}
