<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;

/**
 * Period
 *
 * @ORM\Table(name="period")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PeriodRepository")
 */
class Period
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
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
     * @ORM\ManyToOne(targetEntity="Job", inversedBy="periods")
     * @ORM\JoinColumn(name="job_id", referencedColumnName="id", nullable=false)
     */
    private $job;

    /**
     * One Period have Many Positions.
     * @ORM\OneToMany(targetEntity="PeriodPosition", mappedBy="period", cascade={"persist", "remove"}), orphanRemoval=true)
     */
    private $positions;

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set dayOfWeek
     *
     * @param integer $dayOfWeek
     *
     * @return Period
     */
    public function setDayOfWeek($dayOfWeek)
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    /**
     * Get dayOfWeek
     *
     * @return int
     */
    public function getDayOfWeek()
    {
        return $this->dayOfWeek;
    }


    /**
     * Set start
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
     * Get start
     *
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set end
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
     * Get end
     *
     * @return \DateTime
     */
    public function getEnd()
    {
        return $this->end;
    }


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->positions = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set job
     *
     * @param \AppBundle\Entity\Job $job
     *
     * @return Period
     */
    public function setJob(\AppBundle\Entity\Job $job = null)
    {
        $this->job = $job;

        return $this;
    }

    /**
     * Get job
     *
     * @return \AppBundle\Entity\Job
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * Add periodPosition
     *
     * @param \AppBundle\Entity\PeriodPosition $periodPosition
     *
     * @return Period
     */
    public function addPosition(\AppBundle\Entity\PeriodPosition $position)
    {
        $position->setPeriod($this);
        $this->positions[] = $position;

        return $this;
    }

    /**
     * Remove periodPosition
     *
     * @param \AppBundle\Entity\PeriodPosition $position
     */
    public function removePosition(\AppBundle\Entity\PeriodPosition $position)
    {
        $this->positions->removeElement($position);
    }

    /**
     * Get periodPositions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPositions()
    {
        return $this->positions;
    }

    /**
     * Get periodPositions per week cycle
     *
     * @return array
     */
    public function getPositionsPerWeekCycle()
    {
        $positions_per_week_cycle = array();
        foreach ($this->positions as $position) {
            if (!array_key_exists($position->getWeekCycle(), $positions_per_week_cycle)) {
                $positions_per_week_cycle[$position->getWeekCycle()] = array();
            }
            $positions_per_week_cycle[$position->getWeekCycle()][] = $position;
        }
        ksort($positions_per_week_cycle);
        return $positions_per_week_cycle;
    }

    /**
     * Get periodPositions grouped per week cycle
     *
     * @return array
     */
    public function getGroupedPositionsPerWeekCycle()
    {
        $aggregate_per_formation = array();
        foreach ($this->positions as $position) {
            if (!array_key_exists($position->getWeekCycle(), $aggregate_per_formation)) {
                $aggregate_per_formation[$position->getWeekCycle()] = array();
            }
            if ($position->getFormation()) {
                $formation = $position->getFormation()->getName();
            } else {
                $formation = "Membre";
            }
            if (array_key_exists($formation, $aggregate_per_formation[$position->getWeekCycle()])) {
                $aggregate_per_formation[$position->getWeekCycle()][$formation] += 1;
            } else {
                $aggregate_per_formation[$position->getWeekCycle()][$formation] = 1;
            }
        }
        ksort($aggregate_per_formation);
        $aggregate_per_week_cycle = array();
        foreach ($aggregate_per_formation as $week => $position) {
            $key = $week;
            foreach ($aggregate_per_week_cycle as $w => $p) {
                if ($p == $position) {
                    $key = $w.", ".$week;
                    unset($aggregate_per_week_cycle[$w]);
                    break;
                }
            }
            $aggregate_per_week_cycle[$key] = $position;
        }
        ksort($aggregate_per_week_cycle);
        return $aggregate_per_week_cycle;
    }
}
