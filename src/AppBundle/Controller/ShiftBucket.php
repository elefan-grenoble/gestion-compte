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
        $this->sort();
    }

    public function sort()
    {
        usort($this->shifts, function(Shift $a, Shift $b) {
           if ($a->getIsDismissed()) {
               if ($b->getIsDismissed()) {
                   if ($a->getDismissedTime() == $b->getDismissedTime()) {
                       return 0;
                   } else {
                       return $a->getDismissedTime() < $b->getDismissedTime() ? 1 : -1;
                   }
               } else {
                   return 1;
               }
           } else {
               return $b->getIsDismissed() ? -1 : 0;
           }
        });
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

    public function isBookable(User $user)
    {
        return count($this->getBookableShifts($user)) != 0;
    }

    public function getFirstBookable(User $user)
    {
        return $this->getBookableShifts($user)[0];
    }

    public function getRemainingBookable(User $user)
    {
        return count($this->getBookableShifts($user));
    }

    public function getIntervalCode()
    {
        return $this->shifts[0]->getIntervalCode();
    }
}
