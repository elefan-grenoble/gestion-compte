<?php

namespace AppBundle\Service;

use AppBundle\Entity\Shift;
use AppBundle\Entity\TimeLog;
use Doctrine\ORM\EntityManagerInterface;

class TimeLogService
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Initialize a log with the shift data
     * @param Shift $shift
     * @return TimeLog
     */
    public function initShiftLog(Shift $shift)
    {
        $log = new TimeLog();
        $log->setMembership($shift->getShifter()->getMembership());
        $log->setTime($shift->getDuration());
        $log->setShift($shift);
        $log->setCreatedAt($shift->getStart());
        $log->setType(TimeLog::TYPE_SHIFT);
        return $log;
    }
}
