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

    public function addShifts(iterable $shifts)
    {
        foreach ($shifts as $shift) {
            if ($shift instanceof Shift) {
                $this->shifts[] = $shift;
            }
        }
    }

    static public function compareShifts(Shift $a, Shift $b, Beneficiary $beneficiary = null)
    {
        if (!$beneficiary) {
            if (!$a->getFormation()) {
                if (!$b->getFormation()) {
                    if (!$a->getShifter()) {
                        if (!$b->getShifter()) {
                            return 0;
                        } else {
                            return 1;
                        }
                    } else {
                        if (!$b->getShifter()) {
                            return -1;
                        } else {
                            return $a->getBookedTime() < $b->getBookedTime();
                        }
                    }
                } else {
                    return 1;
                }
            } else {
                if (!$b->getFormation())
                    return -1;
                else
                    return $a->getFormation()->getId() < $b->getFormation()->getId();
            }
        }
        if ($a->getLastShifter() && $a->getLastShifter()->getId() == $beneficiary->getId()) {
            return -1;
        }
        if ($b->getLastShifter() && $b->getLastShifter()->getId() == $beneficiary->getId()) {
            return 1;
        }
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

    public function getShifts()
    {
        return $this->shifts;
    }

    public function getShiftIds(){
        $ids = array();
        foreach ($this->getShifts() as $shift){
            $ids[] = $shift->getId();
        }
        return $ids;
    }

    public function getShifterCount()
    {
        $bookedShifts = $this->getShifts()->filter(function (Shift $shift) {
            return ($shift->getShifter() != NULL);
        });
        return count($bookedShifts);
    }

    public function getSortedShifts()
    {
        $iterator = $this->getShifts()->getIterator();
        $iterator->uasort(function (Shift $a, Shift $b) {
            return ShiftBucket::compareShifts($a, $b);
        });
        $sorted = new \Doctrine\Common\Collections\ArrayCollection(iterator_to_array($iterator));
        return $sorted->isEmpty() ? null : $sorted;

    }

    public function getFirst()
    {
        return $this->shifts->first();
    }

    /**
     * @return Job
     */
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

    public function canBookInterval(Beneficiary $beneficiary) // check if none of the shifts belong to the beneficiary ?
    {
        $alreadyBooked =  $beneficiary->getShifts()->exists(function ($key, Shift $shift) {
            return $shift->getStart() == $this->getStart() && $shift->getEnd() == $this->getEnd();
        });
        $alreadyReserved = $beneficiary->getReservedShifts()->exists(function ($key, Shift $shift) {
            return $shift->getStart() == $this->getStart() && $shift->getEnd() == $this->getEnd();
        });
        return !$alreadyBooked && !$alreadyReserved;
    }

    public function getIntervalCode()
    {
        return $this->shifts[0]->getIntervalCode();
    }

    /**
     * Return true if the intersection between $formations and
     * the shifts' formations is not empty.
     */
    static public function shiftIntersectFormations($shifts, $formations)
    {
        $formationsIds = [];
        foreach ($formations as $formation) {
            $formationsIds[] = $formation->getId();
        }

        $formationInFormationIdsCallback = function ($key, Shift $shift) use ($formationsIds) {
            $formation = $shift->getFormation();
            return !$formation ? false : in_array($formation->getId(), $formationsIds);
        };

        return $shifts->exists($formationInFormationIdsCallback);
    }

    /**
     * Renvoie une collection filtrée en fonction des formations.
     *
     * Si un des shifts a une formation qui appartient à $formations,
     * on renvoie seulement les shifts qui ont un formation.
     *
     * Sinon, on renvoie seulement les shifts qui n'ont pas de formation.
     */
    static public function filterByFormations($shifts, $formations)
    {
        $intersectionNotEmpty = ShiftBucket::shiftIntersectFormations($shifts, $formations);
        $filterCallback = ShiftBucket::createShiftFilterCallback($intersectionNotEmpty);
        return $shifts->filter($filterCallback);
    }

    /**
     * If $withFormations, return a callback which returns true if the
     * shift has a formation.
     *
     * Else, return a callback which return true if the shift
     * doesn't have a formation.
     */
    static public function createShiftFilterCallback($withFormations)
    {
        if ($withFormations) {
            return function (Shift $shift) {
                if ($shift->getFormation())
                    return true;
            };
        } else {
            return function (Shift $shift) {
                if (!$shift->getFormation())
                    return true;
            };
        }
    }
}
