<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Shift;
use AppBundle\Entity\User;

/**
 * Shift Bucket
 * List of shift sharing the same start, end and job
 *
 */
class ShiftBucket
{

    var $shifts;

    public function __construct()
    {
        $this->shifts = [];
    }

    public function addShift(Shift $shift)
    {
        $this->shifts[] = $shift;
    }

    public function getShifts()
    {
        return $this->shifts;
    }

    public function getStart()
    {
        return $this->shifts[0]->getStart();
    }

    public function getEnd()
    {
        return $this->shifts[0]->getEnd();
    }

    public function getDuration()
    {
        return $this->shifts[0]->getDuration();
    }

    public function getBookableShifts(User $user)
    {
        return array_filter($this->shifts, function (Shift $shift) use ($user) {
            return
                ($this->getStart() > $user->endOfCycle(1) || $this->getDuration() <= $user->remainingToBook(1))
                && ($this->getStart() < $user->startOfCycle(2) || $this->getDuration() <= $user->remainingToBook(2))
                && (($shift->getIsDismissed() && $shift->getBooker()->getId() == $user->getId())
                    || !$shift->getShifter());

        });
    }

    public function getFirstBookable(User $user)
    {
        $this->getBookableShifts($user)[0];
    }

    public function getRemainingBookable(User $user)
    {
        return count($this->getBookableShifts($user));
    }

    public function isBookable(User $user)
    {
        return count($this->getBookableShifts($user)) != 0;
    }

    public function getIntervalCode()
    {
        return $this->shifts[0]->getIntervalCode();
    }
}
