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
     * @param \AppBundle\Entity\Period $period
     *
     * @return PeriodPosition
     */
    public function addPeriod(\AppBundle\Entity\Period $period)
    {
        $this->periods[] = $period;

        return $this;
    }

    /**
     * Remove period
     *
     * @param \AppBundle\Entity\Period $period
     */
    public function removePeriod(\AppBundle\Entity\Period $period)
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
