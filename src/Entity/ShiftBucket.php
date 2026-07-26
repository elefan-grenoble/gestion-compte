<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Shift Bucket
 * List of shift sharing the same start, end and job.
 *
 * /!\ ATTENTION /!\
 * Le comportement implémenté repose sur l'hypothèse qu'il ne peut y avoir qu'un seul
 * rôle possible pour un bucket, donc pour un job. Il faudra modifier l'implémentation
 * si cela n'est plus le cas.
 */
class ShiftBucket
{
    public $shifts;

    public function __construct()
    {
        $this->shifts = new ArrayCollection();
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

    public function removeEmptyShift()
    {
        foreach ($this->shifts as $shiftKey => $shift) {
            if ($shift->getShifter() == null && count($this->shifts) > 1) {
                unset($this->shifts[$shiftKey]);
            }
        }
    }

    public static function compareShifts(Shift $a, Shift $b, ?Beneficiary $beneficiary = null)
    {
        if (!$beneficiary) {
            if (!$a->getFormation()) {
                if (!$b->getFormation()) {
                    if (!$a->getShifter()) {
                        if (!$b->getShifter()) {
                            return 0;
                        }

                        return 1;

                    }
                    if (!$b->getShifter()) {
                        return -1;
                    }

                    return $a->getBookedTime() < $b->getBookedTime();


                }

                return 1;

            }
            if (!$b->getFormation()) {
                return -1;
            }
            if ($a->getFormation()->getId() != $b->getFormation()->getId()) {
                return $a->getFormation()->getId() < $b->getFormation()->getId();
            }

            return $a->getBookedTime() < $b->getBookedTime();



        }
        if ($a->getLastShifter() && $a->getLastShifter()->getId() == $beneficiary->getId()) {
            return -1;
        }
        if ($b->getLastShifter() && $b->getLastShifter()->getId() == $beneficiary->getId()) {
            return 1;
        }

        return 0;
    }

    public function getShifts()
    {
        return $this->shifts;
    }

    public function getShiftIds()
    {
        $ids = [];
        foreach ($this->getShifts() as $shift) {
            $ids[] = $shift->getId();
        }

        return $ids;
    }

    public function getId()
    {
        return min($this->getShiftIds());
    }

    public function getShiftWithMinId()
    {
        $min = $this->shifts->first();
        foreach ($this->getShifts() as $shift) {
            if ($min->getId() > $shift->getId()) {
                $min = $shift;
            }
        }

        return $min;
    }

    public function getShifterCount()
    {
        $bookedShifts = $this->getShifts()->filter(function (Shift $shift) {
            return $shift->getShifter() != null;
        });

        return count($bookedShifts);
    }

    public function getSortedShifts()
    {
        $iterator = $this->getShifts()->getIterator();
        $iterator->uasort(function (Shift $a, Shift $b) {
            return ShiftBucket::compareShifts($a, $b);
        });
        $sorted = new ArrayCollection(iterator_to_array($iterator));

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

    /**
     * - check that none of the shifts belong to the beneficiary
     * - check that the beneficiary doesn't already have a shift in the same interval.
     */
    public function canBookInterval(Beneficiary $beneficiary)
    {
        $alreadyBooked = $beneficiary->getShifts()->exists(function ($key, Shift $shift) {
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
     *
     * @param mixed $shifts
     * @param mixed $formations
     */
    public static function shiftIntersectFormations($shifts, $formations)
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
     *
     * @param mixed $shifts
     * @param mixed $formations
     */
    public static function filterByFormations($shifts, $formations)
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
     *
     * @param mixed $withFormations
     */
    public static function createShiftFilterCallback($withFormations)
    {
        if ($withFormations) {
            return function (Shift $shift) {
                if ($shift->getFormation()) {
                    return true;
                }
            };
        } else {
            return function (Shift $shift) {
                if (!$shift->getFormation()) {
                    return true;
                }
            };
        }
    }

    /**
     * Example: "vendredi 22 juillet de 09h30 à 12h30".
     */
    public function getDisplayDateLongWithTime()
    {
        return strftime('%A %e %B', $this->getStart()->getTimestamp()) . ' de ' . $this->getStart()->format('G\hi') . ' à ' . $this->getEnd()->format('G\hi');
    }
}
