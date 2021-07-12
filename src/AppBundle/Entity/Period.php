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
     * @var array
     *
     * @ORM\Column(name="week_cycle", type="simple_array",  options={"default" : "0,1,2,3"})
     */
    private $weekCycle;

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
     * Many Period have Many Positions.
     * @ORM\ManyToMany(targetEntity="PeriodPosition", mappedBy="periods",cascade={"persist"})
     * @OrderBy({"nbOfShifter" = "ASC"})
     * @ORM\JoinTable(name="period_positions")
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
     * Set weekCycle
     *
     * @param array $weekCycle
     *
     */
    public function setWeekCycle($weekCycle)
    {
        $this->weekCycle = $weekCycle;
    }

    /**
     * Get weekCycle
     *
     * @return array
     */
    public function getWeekCycle()
    {
        return $this->weekCycle;
    }

    /**
     * Get weekCycleName
     *
     * @return string
     */
    public function getWeekCycleName()
    {
        $weekCycleNb = count($this->weekCycle);
        if ($weekCycleNb == 4) {
            return "Chaque semaine";
        } else {
            $map = [ 'A', 'B', 'C', 'D'];
            $output = "Semaine#";
            sort($this->weekCycle);
            for ($i = 0, $size = count($this->weekCycle); $i < $size; ++$i) {
                $output .= $map[intval($this->weekCycle[$i])];
                if ($i < $size-1) {
                    $output .= '-';
                }
            }
            return $output;
        }
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
        $position->addPeriod($this);
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
        $position->removePeriod($this);
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
}
