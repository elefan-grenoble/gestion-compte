<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use AppBundle\Entity\Beneficiary;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Shift Bucket
 * List of shift sharing the same start, end and job
 *
 * /!\ ATTENTION /!\
 * Le comportement implémenté repose sur l'hypothèse qu'il ne peut y avoir qu'un seul
 * rôle possible pour un bucket, donc pour un job. Il faudra modifier l'implémentation
 * si cela n'est plus le cas.
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

    static private function compareShifts(Shift $a, Shift $b)
    {
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

    public function getShifts(Beneficiary $beneficiary = null)
    {
        if (!$beneficiary)
            return $this->shifts;
        $bookableShifts = $this->getBookableShifts($beneficiary);
        $bookableIntersectRoles = ShiftBucket::shiftIntersectRoles($bookableShifts, $beneficiary->getRoles());
        return $this->shifts->filter(ShiftBucket::createShiftFilterCallback($bookableIntersectRoles));
    }

    public function getShiftsCount(Beneficiary $beneficiary)
    {
        return count($this->getShifts($beneficiary));
    }

    public function getFirst()
    {
        return $this->shifts->first();
    }

    public function getJob()
    {
        return $this->getFirst()->getJob();
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

    public function canBookInterval(Beneficiary $beneficiary)
    {
        return !$beneficiary->getShifts()->exists(function ($key, Shift $shift) {
            return $shift->getStart() == $this->getStart() && $shift->getEnd() == $this->getEnd();
        });
    }

    private function getBookableShifts(Beneficiary $beneficiary = null)
    {
        if (!$beneficiary) {
            $bookableShifts = $this->shifts->filter(function (Shift $shift) {
                return ($shift->getIsDismissed() || !$shift->getShifter()); //dismissed or free
            });
        } else {
            $user = $beneficiary->getUser();
            if ($this->canBookInterval($beneficiary))
            {
                $bookableShifts = $this->shifts->filter(function (Shift $shift) use ($user, $beneficiary) {
                    return
                        ($this->getStart() > $user->endOfCycle(1) || $this->getDuration() <= $user->remainingToBook(1))
                        && ($this->getStart() < $user->startOfCycle(2) || $this->getDuration() <= $user->remainingToBook(2))
                        && (($shift->getIsDismissed() && $shift->getBooker()->getId() != $beneficiary->getId())
                            || !$shift->getShifter());
                });
            }
            else
            {
                $bookableShifts = new ArrayCollection();
            }
        }
        return $bookableShifts;
    }

    public function isBookable(Beneficiary $beneficiary)
    {
        return $this->getBookableShiftsCount($beneficiary) > 0;
    }

    /***
     * Renvoie le premier shift bookable.
     */
    public function getFirstBookable(Beneficiary $beneficiary)
    {
        $user = $beneficiary->getUser();
        if ($this->isBookable($beneficiary)) {
            $bookableShifts = $this->getBookableShifts($beneficiary);
            $iterator = ShiftBucket::filterByRoles($bookableShifts, $beneficiary->getRoles())->getIterator();
            $iterator->uasort(function (Shift $a, Shift $b) {
                return ShiftBucket::compareShifts($a, $b);
            });
            $sorted = new \Doctrine\Common\Collections\ArrayCollection(iterator_to_array($iterator));
            return $sorted->isEmpty() ? null : $sorted->first();
        } else {
            return null;
        }
    }

    /***
     * Renvoie le nombre de shits bookable.
     */
    public function getBookableShiftsCount(Beneficiary $beneficiary = null)
    {
        $bookableShifts = $this->getBookableShifts($beneficiary);
        if (!$beneficiary)
            return count($bookableShifts);
        return count(ShiftBucket::filterByRoles($bookableShifts, $beneficiary->getRoles()));
    }

    public function getIntervalCode()
    {
        return $this->shifts[0]->getIntervalCode();
    }

    /**
     * Return true if the intersection between $roles and
     * the shifts' roles is not empty.
     */
    static private function shiftIntersectRoles($shifts, $roles)
    {
        $roleIds = [];
        foreach ($roles as $role)
        {
            $roleIds[] = $role->getId();
        }

        $roleInRoleIdsCallback = function ($key, Shift $shift) use($roleIds)
        {
            $role = $shift->getRole();
            return !$role ? false : in_array($role->getId(), $roleIds);
        };

        return $shifts->exists($roleInRoleIdsCallback);
    }

    /**
     * Renvoie une collection filtrée en fonction des rôles.
     * 
     * Si un des shifts a un rôle qui appartient à $roles,
     * on renvoie seulement les shifts qui ont un rôle.
     * 
     * Sinon, on renvoie seulement les shifts qui n'ont pas de rôle.
     */
    static private function filterByRoles($shifts, $roles)
    {
        $intersectionNotEmpty = ShiftBucket::shiftIntersectRoles($shifts, $roles);
        $filterCallback = ShiftBucket::createShiftFilterCallback($intersectionNotEmpty);
        return $shifts->filter($filterCallback);
    }

    /**
     * If $withRoles, return a callback which returns true if the
     * shift has a role.
     * 
     * Else, return a callback which return true if the shift
     * doesn't have a role.
     */
    static private function createShiftFilterCallback($withRoles)
    {
        if ($withRoles)
        {
            return function(Shift $shift)
            {
                if ($shift->getRole())
                    return true;
            };
        }
        else {
            return function(Shift $shift)
            {
                if (!$shift->getRole())
                    return true;
            };
        }
    }
}
