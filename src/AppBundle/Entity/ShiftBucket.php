<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;

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
        $this->shifts = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function addShift(Shift $shift)
    {
        $this->shifts[] = $shift;
    }

    function compareShifts(Shift $a, Shift $b)
    {
        if ($a->getRole()) {
            return $b->getRole() ? 0 : 1;
        } else if ($b->getRole()) {
            return -1;
        } else {
            if ($a->getIsDismissed()) {
                if ($b->getIsDismissed()) {
                    if ($a->getDismissedTime() == $b->getDismissedTime()) {
                        return 0;
                    } else {
                        return $a->getDismissedTime() < $b->getDismissedTime() ? -1 : 1;
                    }
                } else {
                    return -1;
                }
            } else {
                return $b->getIsDismissed() ? 1 : 0;
            }
        }
    }

    public function getShifts()
    {
        return $this->shifts;
    }

    public function getFirst()
    {
        return $this->shifts->first();
    }

    public function getStart()
    {
        return $this->shifts->first()->getStart();
    }

    public function getEnd()
    {
        return $this->shifts->first()->getEnd();
    }

    public function getDuration()
    {
        return $this->shifts->first()->getDuration();
    }

    public function canBookInterval(User $user)
    {
        return !$user->getAllShifts()->exists(function ($key, Shift $shift) {
            return $shift->getStart() == $this->getStart() && $shift->getEnd() == $this->getEnd();
        });
    }

    public function getBookableShifts(User $user)
    {
        if ($this->canBookInterval($user)) {
            return $this->shifts->filter(function (Shift $shift) use ($user) {
                return
                    ($this->getStart() > $user->endOfCycle(1) || $this->getDuration() <= $user->remainingToBook(1))
                    && ($this->getStart() < $user->startOfCycle(2) || $this->getDuration() <= $user->remainingToBook(2))
                    && (($shift->getIsDismissed() && $shift->getBooker()->getId() != $user->getId())
                        || !$shift->getShifter());

            });
        } else {
            return new \Doctrine\Common\Collections\ArrayCollection();
        }
    }

    public function isBookable(User $user)
    {
        return count($this->getBookableShifts($user)) > 0;
    }

    public function getFirstBookable(User $user)
    {
        if ($this->isBookable($user)) {
            $iterator = $this->getBookableShifts($user)->getIterator();
            $iterator->uasort(function (Shift $a, Shift $b) {
                return $this->compareShifts($a, $b) * -1; // We want priority shift first
            });
            $sorted = new \Doctrine\Common\Collections\ArrayCollection(iterator_to_array($iterator));
            return $sorted->first();
        } else {
            return null;
        }
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
