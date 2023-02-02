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
        $log->setRequestRoute($request->get('_route'));

        return $log;
    }

    /**
     * Initialize a log with the shift data
     * @param Shift $shift
     * @return TimeLog
     */
    public function initShiftTimeLog(Shift $shift, \DateTime $date = null, $description = null)
    {
        $log = $this->initTimeLog($shift->getShifter()->getMembership(), $date, $description);
        $log->setType(TimeLog::TYPE_SHIFT);
        $log->setShift($shift);
        $log->setTime($shift->getDuration());

        return $log;
    }

    /**
     * Initialize a log with the member data
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
     * Initialize a log with the member data
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
     * Initialize a custom log with the member data
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
