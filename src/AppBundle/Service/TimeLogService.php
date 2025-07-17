<?php

namespace AppBundle\Service;

use AppBundle\Entity\Shift;
use AppBundle\Entity\Membership;
use AppBundle\Entity\TimeLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class TimeLogService
{
    protected $em;
    protected $requestStack;
    private $tokenStorage;
    private $membershipService;
    private $due_duration_by_cycle;

    public function __construct(EntityManagerInterface $em, RequestStack $requestStack, TokenStorage $tokenStorage, MembershipService $membershipService, int $due_duration_by_cycle)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
        $this->membershipService = $membershipService;
        $this->due_duration_by_cycle = $due_duration_by_cycle;
    }

    /**
     * Initialize a log with the member data
     * 
     * @param Membership $member
     * @param \DateTime $date
     * @return TimeLog
     */
    public function initTimeLog(Membership $member, \DateTime $date = null, $description = null)
    {
        $current_user = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;
        $request = $this->requestStack->getCurrentRequest();

        $log = new TimeLog();
        $log->setMembership($member);
        if ($description) {
            $log->setDescription($description);
        }
        if ($date) {
            $log->setCreatedAt($date);
        } // else defaults to 'now'
        if (is_object($current_user)) {
            $log->setCreatedBy($current_user);
        }
        if ($request) {
            $log->setRequestRoute($request->get('_route'));
        }

        return $log;
    }

    /**
     * Initialize a "shift validation" log with the shift data
     * 
     * @param Shift $shift
     * @param \DateTime $date
     * @return TimeLog
     */
    public function initShiftValidatedTimeLog(Shift $shift, \DateTime $date = null, $description = null)
    {
        $member = $shift->getShifter()->getMembership();

        $log = $this->initTimeLog($member, $date, $description);
        $log->setType(TimeLog::TYPE_SHIFT_VALIDATED);
        $log->setShift($shift);
        $log->setTime($shift->getDuration());

        return $log;
    }

    /**
     * Initialize an "shift invalidation" log with the shift data
     * 
     * @param Shift $shift
     * @param \DateTime $date
     * @return TimeLog
     */
    public function initShiftInvalidatedTimeLog(Shift $shift, Membership $member, \DateTime $date = null, $description = null)
    {
        $log = $this->initTimeLog($member, $date, $description);
        $log->setType(TimeLog::TYPE_SHIFT_INVALIDATED);
        $log->setShift($shift);
        $log->setTime(-1 * $shift->getDuration());

        return $log;
    }

    /**
     * Initialize an "shift freed saving" log with the shift data
     * 
     * @param Shift $shift
     * @param \DateTime $date
     * @return TimeLog
     */
    public function initShiftFreedSavingTimeLog(Membership $member, $time, \DateTime $date = null, $shift)
    {
        $log = $this->initTimeLog($member, $date);
        $log->setType(TimeLog::TYPE_SHIFT_FREED_SAVING);
        $log->setShift($shift);
        $log->setTime($time);  // $shift->getDuration()

        return $log;
    }

    /**
     * Initialize a "cycle beginning" log with the member data
     * 
     * @param Membership $member
     * @param \DateTime $date
     * @return TimeLog
     */
    public function initCycleBeginningTimeLog(Membership $member, \DateTime $date = null)
    {
        $log = $this->initTimeLog($member, $date);
        $log->setType(TimeLog::TYPE_CYCLE_END);
        $log->setTime(-1 * $this->due_duration_by_cycle);

        return $log;
    }

    /**
     * Initialize a "current cycle beginning" log with the member data
     * 
     * @param Membership $member
     * @return TimeLog
     */
    public function initCurrentCycleBeginningTimeLog(Membership $member)
    {
        $date = $this->membershipService->getStartOfCycle($member, 0);
        $log = $this->initCycleBeginningTimeLog($member, $date);

        return $log;
    }

    /**
     * Initialize a "cycle end saving" log
     * 
     * @param Shift $shift
     * @param \DateTime $date
     * @return TimeLog
     */
    public function initCycleEndSavingTimeLog(Membership $member, $time, \DateTime $date = null, $description = null)
    {
        $log = $this->initTimeLog($member, $date, $description);
        $log->setType(TimeLog::TYPE_CYCLE_END_SAVING);
        $log->setTime($time);

        return $log;
    }

    /**
     * Initialize a "regulation optional shifts" log with the member data
     * 
     * @param Membership $member
     * @param int $time
     * @return TimeLog
     */
    public function initRegulateOptionalShiftsTimeLog(Membership $member, $time, \DateTime $date = null)
    {
        $log = $this->initTimeLog($member, $date);
        $log->setType(TimeLog::TYPE_REGULATE_OPTIONAL_SHIFTS);
        $log->setTime($time);

        return $log;
    }

    /**
     * Initialize a "saving" log with the member data
     * 
     * @param Membership $member
     * @param int $time
     * @return TimeLog
     */
    public function initSavingTimeLog(Membership $member, $time, \DateTime $date = null, $shift = null)
    {
        $log = $this->initTimeLog($member, $date);
        $log->setType(TimeLog::TYPE_SAVING);
        $log->setTime($time);
        if ($shift) {
            $log->setShift($shift);
        }

        return $log;
    }

    /**
     * Initialize a "custom" log with the member data
     * 
     * @param Membership $member
     * @return TimeLog
     */
    public function initCustomTimeLog(Membership $member, $time = null, \DateTime $date = null, $description = null)
    {
        $log = $this->initTimeLog($member, $date, $description);
        $log->setType(TimeLog::TYPE_CUSTOM);
        if ($time) {
            $log->setTime($time);
        }

        return $log;
    }
}
