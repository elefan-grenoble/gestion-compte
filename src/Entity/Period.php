<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Period.
 *
 * @ORM\Table(name="period")
 *
 * @ORM\HasLifecycleCallbacks()
 *
 * @ORM\Entity(repositoryClass="App\Repository\PeriodRepository")
 */
class Period
{
    public const DAYS_OF_WEEK = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    public const DAYS_OF_WEEK_LIST_WITH_INT = ['Lundi' => 0, 'Mardi' => 1, 'Mercredi' => 2, 'Jeudi' => 3, 'Vendredi' => 4, 'Samedi' => 5, 'Dimanche' => 6];
    public const WEEK_A = 'A';
    public const WEEK_B = 'B';
    public const WEEK_C = 'C';
    public const WEEK_D = 'D';
    public const WEEK_CYCLE = [Period::WEEK_A, Period::WEEK_B, Period::WEEK_C, Period::WEEK_D];
    public const WEEK_CYCLE_CHOICE_LIST = ['Semaine A' => Period::WEEK_A, 'Semaine B' => Period::WEEK_B, 'Semaine C' => Period::WEEK_C, 'Semaine D' => Period::WEEK_D];

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="day_of_week", type="smallint")
     */
    private $dayOfWeek;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="start", type="time")
     */
    private $start;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="end", type="time")
     */
    private $end;

    /**
     * One Period has One Job.
     *
     * @ORM\ManyToOne(targetEntity="Job", inversedBy="periods", fetch="EAGER")
     *
     * @ORM\JoinColumn(name="job_id", referencedColumnName="id", nullable=false)
     */
    private $job;

    /**
     * One Period has Many Positions.
     *
     * @ORM\OneToMany(targetEntity="PeriodPosition", mappedBy="period", cascade={"persist", "remove"}), orphanRemoval=true)
     */
    private $positions;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     *
     * @ORM\JoinColumn(name="created_by_id", referencedColumnName="id")
     */
    private $createdBy;

    /**
     * @ORM\Column(type="datetime")
     *
     * @var \DateTime
     */
    private $updatedAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     *
     * @ORM\JoinColumn(name="updated_by_id", referencedColumnName="id")
     */
    private $updatedBy;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->positions = new ArrayCollection();
    }

    /**
     * Example: "Epicerie/Livraison - Lundi - 9h30 à 12h30".
     */
    public function __toString()
    {
        return $this->getJob() . ' - ' . ucfirst($this->getDayOfWeekString()) . ' - ' . $this->getStart()->format('G\hi') . ' à ' . $this->getEnd()->format('G\hi');
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        if (!$this->createdAt) {
            $this->createdAt = new \DateTime();
        }
    }

    /**
     * @ORM\PrePersist
     *
     * @ORM\PreUpdate
     */
    public function setUpdatedAtValue()
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set dayOfWeek.
     *
     * @param int $dayOfWeek
     *
     * @return Period
     */
    public function setDayOfWeek($dayOfWeek)
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    /**
     * Get dayOfWeek.
     *
     * @return int
     */
    public function getDayOfWeek()
    {
        return $this->dayOfWeek;
    }

    /**
     * Get dayOfWeekString.
     *
     * @return int
     */
    public function getDayOfWeekString()
    {
        return strftime('%A', strtotime("Monday + {$this->dayOfWeek} days"));
    }

    /**
     * Set start.
     *
     * @param \DateTime $start
     *
     * @return Period
     */
    public function setStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get start.
     *
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set end.
     *
     * @param \DateTime $end
     *
     * @return Period
     */
    public function setEnd($end)
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Get end.
     *
     * @return \DateTime
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * @Assert\IsTrue(message="L'heure de début doit être avant celle de fin")
     */
    public function isStartBeforeEnd()
    {
        return $this->start < $this->end;
    }

    /**
     * Set job.
     *
     * @return Period
     */
    public function setJob(?Job $job = null)
    {
        $this->job = $job;

        return $this;
    }

    /**
     * Get job.
     *
     * @return Job
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * Add periodPosition.
     *
     * @return Period
     */
    public function addPosition(PeriodPosition $position)
    {
        $position->setPeriod($this);
        $this->positions[] = $position;

        return $this;
    }

    /**
     * Remove periodPosition.
     */
    public function removePosition(PeriodPosition $position)
    {
        $this->positions->removeElement($position);
    }

    /**
     * Get periodPositions.
     *
     * @return Collection
     */
    public function getPositions()
    {
        return $this->positions;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $date
     *
     * @return Period
     */
    public function setCreatedAt($date)
    {
        $this->createdAt = $date;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Get createdBy.
     *
     * @return User
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * Set createdBy.
     *
     * @return Period
     */
    public function setCreatedBy(?User $user = null)
    {
        $this->createdBy = $user;

        return $this;
    }

    /**
     * Get updatedAt.
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Get updatedBy.
     *
     * @return User
     */
    public function getUpdatedBy()
    {
        return $this->updatedBy;
    }

    /**
     * Set updatedBy.
     *
     * @return Period
     */
    public function setUpdatedBy(?User $user = null)
    {
        $this->updatedBy = $user;

        return $this;
    }

    /**
     * Get all the positions.
     *
     * @param null|mixed $booked
     * @param null|mixed $weekCycle
     *
     * @return Collection
     */
    public function getPositionsWithFilter($booked = null, $weekCycle = null)
    {
        $positions = $this->getPositions();

        if ($booked === true) {
            $positions = $positions->filter(function (PeriodPosition $position) {
                return $position->getShifter();
            });
        } elseif ($booked === false) {
            $positions = $positions->filter(function (PeriodPosition $position) {
                return !$position->getShifter();
            });
        }

        if ($weekCycle) {
            $positions = $positions->filter(function (PeriodPosition $position) use ($weekCycle) {
                return $position->getWeekCycle() == $weekCycle;
            });
        }

        return $positions;
    }

    /**
     * Get all the positions per week cycle.
     */
    public function getPositionsPerWeekCycle(): array
    {
        $positionsPerWeekCycle = [];
        foreach ($this->positions as $position) {
            if (!array_key_exists($position->getWeekCycle(), $positionsPerWeekCycle)) {
                $positionsPerWeekCycle[$position->getWeekCycle()] = [];
            }
            $positionsPerWeekCycle[$position->getWeekCycle()][] = $position;
        }
        ksort($positionsPerWeekCycle);

        return $positionsPerWeekCycle;
    }

    /**
     * Get periodPositions grouped per week cycle.
     *
     * @param null|string $weekCycle a string of the week to keep or null if no filter
     */
    public function getGroupedPositionsPerWeekCycle(?string $weekCycle = null): array
    {
        $aggregatePerFormation = [];
        foreach ($this->positions as $position) {
            if (!array_key_exists($position->getWeekCycle(), $aggregatePerFormation)) {
                $aggregatePerFormation[$position->getWeekCycle()] = [];
            }
            if ($position->getFormation()) {
                $formation = $position->getFormation()->getName();
            } else {
                $formation = 'Membre';
            }
            if (array_key_exists($formation, $aggregatePerFormation[$position->getWeekCycle()])) {
                ++$aggregatePerFormation[$position->getWeekCycle()][$formation];
            } else {
                $aggregatePerFormation[$position->getWeekCycle()][$formation] = 1;
            }
        }
        ksort($aggregatePerFormation);
        $aggregatePerWeekCycle = [];

        foreach ($aggregatePerFormation as $week => $position) {
            if ($weekCycle && $week == $weekCycle or !$weekCycle) {
                // week_filter not null and in the filter list or week_filter null
                $key = $week;
                foreach ($aggregatePerWeekCycle as $w => $p) {
                    if ($p == $position) {
                        $key = $w . ', ' . $week;
                        unset($aggregatePerWeekCycle[$w]);

                        break;
                    }
                }
                $aggregatePerWeekCycle[$key] = $position;
            }
        }

        ksort($aggregatePerWeekCycle);

        return $aggregatePerWeekCycle;
    }

    /**
     * Return true if 0 periods have been assigned to a shifter (a.k.a. beneficiary)
     * Note: useful only if the use_fly_and_fixed is activated.
     *
     * @param null|string $weekCycle a string of the week to keep or null if no filter
     */
    public function isEmpty(?string $weekCycle = null): bool
    {
        $bookedPositions = $this->getPositionsWithFilter(true, $weekCycle);

        return count($bookedPositions) == 0;
    }

    /**
     * Return true if all the periods have been assigned to a shifter (a.k.a. beneficiary)
     * Note: useful only if the use_fly_and_fixed is activated.
     *
     * @param null|string $weekCycle a string of the week to keep or null if no filter
     */
    public function isFull(?string $weekCycle = null): bool
    {
        $emptyPositions = $this->getPositionsWithFilter(false, $weekCycle);

        return count($emptyPositions) == 0;
    }

    /**
     * Return true if neither 0 nor all the periods have been assigned to a shifter (a.k.a. beneficiary)
     * Note: useful only if the use_fly_and_fixed is activated.
     *
     * @param null|string $weekCycle a string of the week to keep or null if no filter
     */
    public function isPartial(?string $weekCycle = null): bool
    {
        $bookedPositions = $this->getPositionsWithFilter(true, $weekCycle);
        $emptyPositions = $this->getPositionsWithFilter(false, $weekCycle);

        return (count($bookedPositions) > 0) && (count($emptyPositions) > 0);
    }

    public function hasShifter(?Beneficiary $beneficiary = null)
    {
        if (!$beneficiary) {
            return true;
        }

        return $this->getPositions()->filter(function (PeriodPosition $position) use ($beneficiary) {
            return $position->getShifter() === $beneficiary;
        });
    }
}
