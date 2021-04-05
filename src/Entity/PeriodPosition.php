<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PeriodRoom
 *
 * @ORM\Table(name="period_position")
 * @ORM\Entity(repositoryClass="App\Repository\PeriodPositionRepository")
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
     * @var int
     *
     * @ORM\Column(name="nb_of_shifter", type="integer")
     */
    private $nbOfShifter;

    /**
     * One Period has One Formation.
     * @ORM\ManyToOne(targetEntity="Formation")
     * @ORM\JoinColumn(name="formation_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $formation;

    /**
     * Many Positions have Many Periods.
     * @ORM\ManyToMany(targetEntity="Period", inversedBy="positions")
     */
    private $periods;

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
     * Set nbOfShifter
     *
     * @param integer $nbOfShifter
     *
     * @return PeriodPosition
     */
    public function setNbOfShifter($nbOfShifter)
    {
        $this->nbOfShifter = $nbOfShifter;

        return $this;
    }

    /**
     * Get nbOfShifter
     *
     * @return int
     */
    public function getNbOfShifter()
    {
        return $this->nbOfShifter;
    }


    /**
     * Set formation
     *
     * @param \App\Entity\Formation $formation
     *
     * @return PeriodPosition
     */
    public function setFormation(\App\Entity\Formation $formation = null)
    {
        $this->formation = $formation;

        return $this;
    }

    /**
     * Get formation
     *
     * @return \App\Entity\Formation
     */
    public function getFormation()
    {
        return $this->formation;
    }

    public function __toString()
    {
        if ($this->getFormation())
            return $this->getNbOfShifter()." x ".$this->getFormation()->getName();
        else
            return $this->getNbOfShifter()." x Membre";
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->periods = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add period
     *
     * @param \App\Entity\Period $period
     *
     * @return PeriodPosition
     */
    public function addPeriod(\App\Entity\Period $period)
    {
        $this->periods[] = $period;

        return $this;
    }

    /**
     * Remove period
     *
     * @param \App\Entity\Period $period
     */
    public function removePeriod(\App\Entity\Period $period)
    {
        $this->periods->removeElement($period);
    }

    /**
     * Get periods
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPeriods()
    {
        return $this->periods;
    }
}
