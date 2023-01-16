<?php

namespace AppBundle\Service;

use AppBundle\Entity\Shift;
use AppBundle\Entity\Membership;
use AppBundle\Entity\TimeLog;
use Doctrine\ORM\EntityManagerInterface;

class TimeLogService
{
    private $em;
    private $membershipService;
    private $due_duration_by_cycle;

    public function __construct(EntityManagerInterface $em, MembershipService $membershipService, $due_duration_by_cycle)
    {
        $this->em = $em;
        $this->membershipService = $membershipService;
        $this->due_duration_by_cycle = $due_duration_by_cycle;
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
        $log->setType(TimeLog::TYPE_SHIFT);
        $log->setCreatedAt($shift->getStart());
        return $log;
    }

    /**
     * Initialize a log with the member data
     * @param Membership $member
     * @param \DateTime $date
     * @return TimeLog
     */
    public function initCycleBeginningLog(Membership $member, \DateTime $date = null)
    {
        $date = $this->membershipService->getStartOfCycle($member, 0);
        $log = new TimeLog();
        $log->setMembership($member);
        $log->setTime(-1 * $this->due_duration_by_cycle);
        $log->setType(TimeLog::TYPE_CYCLE_END);
        if ($date) {
            $log->setCreatedAt($date);
        }
        return $log;
    }

    /**
     * Initialize a log with the member data
     * @param Membership $member
     * @return TimeLog
     */
    public function initCurrentCycleBeginningLog(Membership $member)
    {
        $date = $this->membershipService->getStartOfCycle($member, 0);
        $log = $this->initCycleBeginningLog($member, $date);
        return $log;
    }
}
