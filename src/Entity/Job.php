<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Job
 *
 * @ORM\Table(name="job")
 * @ORM\Entity(repositoryClass="App\Repository\JobRepository")
 */
class Job
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
     * @var string
     *
     * @Assert\NotBlank()
     * @ORM\Column(name="name", type="string", length=191, unique=true)
     */
    private $name;


    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @ORM\Column(name="color", type="string", length=255, unique=false)
     */
    private $color;

    /**
     * @var string
     * @ORM\Column(name="description", type="text", nullable=false)
     */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity="Shift", mappedBy="job", cascade={"persist", "remove"}), orphanRemoval=true)
     */
    private $shifts;


    /**
     * @ORM\OneToMany(targetEntity="Period", mappedBy="job", cascade={"persist", "remove"}), orphanRemoval=true)
     */
    private $periods;

    /**
     * @var bool
     *
     * @ORM\Column(name="enabled", type="boolean", nullable=false, options={"default" : 1})
     */
    private $enabled;

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
     * Set name
     *
     * @param string $name
     *
     * @return Job
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set color
     *
     * @param string $color
     *
     * @return Job
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->shifts = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add shift.
     *
     * @param \App\Entity\Shift $shift
     *
     * @return Job
     */
    public function addShift(\App\Entity\Shift $shift)
    {
        $this->shifts[] = $shift;

        return $this;
    }

    /**
     * Remove shift.
     *
     * @param \App\Entity\Shift $shift
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeShift(\App\Entity\Shift $shift)
    {
        return $this->shifts->removeElement($shift);
    }

    /**
     * Get shifts.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getShifts()
    {
        return $this->shifts;
    }

    /**
     * Add period.
     *
     * @param \App\Entity\Period $period
     *
     * @return Job
     */
    public function addPeriod(\App\Entity\Period $period)
    {
        $this->periods[] = $period;

        return $this;
    }

    /**
     * Remove period.
     *
     * @param \App\Entity\Period $period
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removePeriod(\App\Entity\Period $period)
    {
        return $this->periods->removeElement($period);
    }

    /**
     * Get periods.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPeriods()
    {
        return $this->periods;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * @return string
     */
    public function getDescription(): string {
        return $this->description ? $this->description : '';
    }

    /**
     * @param string $description
     * @return Job
     */
    public function setDescription(string $description): Job {
        $this->description = $description;
        return $this;
    }

}
