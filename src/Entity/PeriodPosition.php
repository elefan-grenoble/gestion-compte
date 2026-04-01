<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

/**
 * PeriodRoom.
 *
 * @ORM\Table(name="period_position")
 *
 * @ORM\HasLifecycleCallbacks()
 *
 * @ORM\Entity(repositoryClass="App\Repository\PeriodPositionRepository")
 */
class PeriodPosition
{
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
     * One Period has One Formation.
     *
     * @ORM\ManyToOne(targetEntity="Formation", fetch="EAGER")
     *
     * @ORM\JoinColumn(name="formation_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $formation;

    /**
     * @ORM\ManyToOne(targetEntity="Period", inversedBy="positions", fetch="EAGER")
     *
     * @ORM\JoinColumn(name="period_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $period;

    /**
     * @var string
     *
     * @ORM\Column(name="week_cycle", type="string", length=1, nullable=true)
     */
    private $weekCycle;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="periodPositions")
     *
     * @ORM\JoinColumn(name="shifter_id", referencedColumnName="id")
     */
    private $shifter;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     *
     * @ORM\JoinColumn(name="booker_id", referencedColumnName="id")
     */
    private $booker;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="booked_time", type="datetime", nullable=true)
     */
    private $bookedTime;

    /**
     * @ORM\OneToMany(targetEntity="Shift", mappedBy="position", cascade={"persist"})
     */
    private $shifts;

    /**
     * @ORM\OneToMany(targetEntity="PeriodPositionFreeLog", mappedBy="periodPosition")
     */
    private $freeLogs;

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
    public function __construct() {}

    /**
     * Example: "Epicerie/Livraison - Lundi - 09:30 à 12:30 (Semaine D) (sans formation)".
     */
    public function __toString()
    {
        $name = (string) $this->getPeriod();
        if ($this->getWeekCycle()) {
            $name .= ' - Semaine ' . $this->getWeekCycle();
        }
        if ($this->getFormation()) {
            $name .= ' (' . $this->getFormation()->getName() . ')';
        }

        return $name;
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
     * Set period.
     *
     * @return PeriodPosition
     */
    public function setPeriod(?Period $period = null)
    {
        $this->period = $period;

        return $this;
    }

    /**
     * Get period.
     *
     * @return Period
     */
    public function getPeriod()
    {
        return $this->period;
    }

    /**
     * Set formation.
     *
     * @return PeriodPosition
     */
    public function setFormation(?Formation $formation = null)
    {
        $this->formation = $formation;

        return $this;
    }

    /**
     * Get formation.
     *
     * @return Formation
     */
    public function getFormation()
    {
        return $this->formation;
    }

    /**
     * Set weekCycle.
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
     * Get weekCycle.
     *
     * @return array
     */
    public function getWeekCycle()
    {
        return $this->weekCycle;
    }

    /**
     * Set shifter.
     *
     * @return PeriodPosition
     */
    public function setShifter(?Beneficiary $shifter = null)
    {
        $this->shifter = $shifter;

        return $this;
    }

    /**
     * Get shifter.
     *
     * @return Beneficiary
     */
    public function getShifter()
    {
        return $this->shifter;
    }

    /**
     * Set booker.
     *
     * @return BookedShift
     */
    public function setBooker(?User $booker = null)
    {
        $this->booker = $booker;

        return $this;
    }

    /**
     * Get booker.
     *
     * @return User
     */
    public function getBooker()
    {
        return $this->booker;
    }

    /**
     * Set bookedTime.
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
     * Get bookedTime.
     *
     * @return \DateTime
     */
    public function getBookedTime()
    {
        return $this->bookedTime;
    }

    /**
     * Get shifts.
     *
     * @return Collection
     */
    public function getShifts()
    {
        return $this->shifts;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $date
     *
     * @return PeriodPosition
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
     * @return PeriodPosition
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
     * @return PeriodPosition
     */
    public function setUpdatedBy(?User $user = null)
    {
        $this->updatedBy = $user;

        return $this;
    }

    /**
     * free.
     *
     * @return PeriodPosition
     */
    public function free()
    {
        $this->setBooker(null);
        $this->setBookedTime(null);
        $this->setShifter(null);

        return $this;
    }
}
