<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PeriodRoom
 *
 * @ORM\Table(name="period_position")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PeriodPositionRepository")
 */
class PeriodPosition
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
     * One Period has One Formation.
     * @ORM\ManyToOne(targetEntity="Formation")
     * @ORM\JoinColumn(name="formation_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $formation;

    /**
     * @ORM\ManyToOne(targetEntity="Period", inversedBy="positions")
     * @ORM\JoinColumn(name="period_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $period;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary")
     * @ORM\JoinColumn(name="shifter_id", referencedColumnName="id")
     */
    private $shifter;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary")
     * @ORM\JoinColumn(name="booker_id", referencedColumnName="id")
     */
    private $booker;

    /**
     * @var string
     * @ORM\Column(name="week_cycle", type="string", length=1, nullable=false)
     */
    private $weekCycle;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="booked_time", type="datetime", nullable=true)
     */
    private $bookedTime;


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
     * Set period
     *
     * @param \AppBundle\Entity\Period $period
     *
     * @return PeriodPosition
     */
    public function setPeriod(\AppBundle\Entity\Period $period = null)
    {
        $this->period = $period;

        return $this;
    }

    /**
     * Get period
     *
     * @return Period
     */
    public function getPeriod()
    {
        return $this->period;
    }

    /**
     * Set formation
     *
     * @param \AppBundle\Entity\Formation $formation
     *
     * @return PeriodPosition
     */
    public function setFormation(\AppBundle\Entity\Formation $formation = null)
    {
        $this->formation = $formation;

        return $this;
    }

    /**
     * Get formation
     *
     * @return \AppBundle\Entity\Formation
     */
    public function getFormation()
    {
        return $this->formation;
    }

    /**
     * Set weekCycle
     *
     * @param string $weekCycle
     *
     * @return PeriodPosition
     */
    public function setWeekCycle($weekCycle)
    {
        $this->weekCycle = $weekCycle;
        return $this;
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
     * Set shifter
     *
     * @param \AppBundle\Entity\Beneficiary $shifter
     *
     * @return PeriodPosition
     */
    public function setShifter(\AppBundle\Entity\Beneficiary $shifter = null)
    {
        $this->shifter = $shifter;

        return $this;
    }

    /**
     * Get shifter
     *
     * @return \AppBundle\Entity\Beneficiary
     */
    public function getShifter()
    {
        return $this->shifter;
    }

    /**
     * Set booker
     *
     * @param \AppBundle\Entity\Beneficiary $booker
     *
     * @return BookedShift
     */
    public function setBooker(\AppBundle\Entity\Beneficiary $booker = null)
    {
        $this->booker = $booker;

        return $this;
    }

    /**
     * Get booker
     *
     * @return \AppBundle\Entity\Beneficiary
     */
    public function getBooker()
    {
        return $this->booker;
    }

    /**
     * Set bookedTime
     *
     * @param \DateTime $bookedTime
     *
     * @return BookedShift
     */
    public function setBookedTime($bookedTime)
    {
        $this->bookedTime = $bookedTime;

        return $this;
    }

    /**
     * Get bookedTime
     *
     * @return \DateTime
     */
    public function getBookedTime()
    {
        return $this->bookedTime;
    }

    /**
     * free
     *
     * @return \AppBundle\Entity\PeriodPosition
     */
    public function free()
    {
        $this->setBooker(null);
        $this->setBookedTime(null);
        $this->setShifter(null);
        return $this;
    }

    public function __toString()
    {
        if ($this->getFormation())
            return $this->getFormation()->getName();
        else
            return "Membre";
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->periods = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
