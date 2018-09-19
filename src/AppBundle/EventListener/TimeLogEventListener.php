<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
use AppBundle\Entity\User;
use AppBundle\Event\ShiftBookedEvent;
use AppBundle\Event\ShiftDismissedEvent;
use AppBundle\Event\ShiftFreedEvent;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

class TimeLogEventListener
{
    protected $_em;
    protected $_logger;

    public function __construct(EntityManager $entityManager, Logger $logger)
    {
        $this->_em = $entityManager;
        $this->_logger = $logger;
    }

    /**
     * @param ShiftBookedEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onShiftBooked(ShiftBookedEvent $event)
    {
        $this->_logger->info("Time Log Listener: onShiftBooked");
        $shift = $event->getShift();
        $this->createShiftLog($shift);
    }

    /**
     * @param ShiftFreedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onShiftFreed(ShiftFreedEvent $event)
    {
        $this->_logger->info("Time Log Listener: onShiftFreed");
        $this->deleteShiftLogs($event->getShift(), $event->getUser());
    }

    /**
     * @param ShiftDismissedEvent $event
     * @throws \Doctrine\ORM\ORMException
     */
    public function onShiftDismissed(ShiftDismissedEvent $event)
    {
        $this->_logger->info("Time Log Listener: onShiftDismissed");
        $this->deleteShiftLogs($event->getShift(), $event->getUser());
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
        $this->_em->persist($log);
        $this->_em->flush();
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
                $this->_em->remove($log);
            }
        }
        $this->_em->flush();
    }

}
