<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
use AppBundle\Event\ShiftBookedEvent;
use Doctrine\ORM\EntityManager;

class TimeLogEventListener
{
    protected $_em;

    public function __construct(EntityManager $entityManager)
    {
        $this->_em = $entityManager;
    }

    /**
     * @param ShiftBookedEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onShiftBooked(ShiftBookedEvent $event)
    {
        $shift = $event->getShift();
        $this->createShiftLog($shift);
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

}
