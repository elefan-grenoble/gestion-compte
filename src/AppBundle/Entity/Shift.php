<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Shift
 *
 * @ORM\Table(name="shift")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ShiftRepository")
 */
class Shift
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
     * @var \DateTime
     *
     * @ORM\Column(name="start", type="datetime")
     */
    private $start;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="end", type="datetime")
     */
    private $end;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="booked_time", type="datetime", nullable=true)
     */
    private $bookedTime;

    /**
     * @var bool
     *
     * @ORM\Column(name="was_carried_out", type="boolean", options={"default" : 0})
     */
    private $wasCarriedOut;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="shifts")
     * @ORM\JoinColumn(name="shifter_id", referencedColumnName="id")
     */
    private $shifter;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="booker_id", referencedColumnName="id")
     */
    private $booker;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="reservedShifts")
     * @ORM\JoinColumn(name="last_shifter_id", referencedColumnName="id")
     */
    private $lastShifter;

    /**
     * One Shift has one Formation.
     * @ORM\ManyToOne(targetEntity="Formation")
     * @ORM\JoinColumn(name="formation_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $formation;

    /**
     * One Shift has One Job.
     * @ORM\ManyToOne(targetEntity="Job", inversedBy="shifts", fetch="EAGER")
     * @ORM\JoinColumn(name="job_id", referencedColumnName="id", nullable=false)
     */
    private $job;

    /**
     * One Shift may have been created from One PeriodPosition.
     * @ORM\ManyToOne(targetEntity="PeriodPosition", inversedBy="shifts")
     * @ORM\JoinColumn(name="position_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $position;

    /**
     * @ORM\OneToMany(targetEntity="TimeLog", mappedBy="shift")
     */
    private $timeLogs;

    /**
     * @ORM\OneToMany(targetEntity="ShiftFreeLog", mappedBy="shift")
     */
    private $shiftFreeLogs;

    /**
     * @var bool
     *
     * @ORM\Column(name="locked", type="boolean", options={"default" : 0}, nullable=false)
     */
    private $locked = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="fixe", type="boolean", options={"default" : 0}, nullable=false)
     */
    private $fixe = false;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="created_by_id", referencedColumnName="id")
     */
    private $createdBy;

    public function __construct()
    {
        $this->wasCarriedOut = false;
    }

    /**
     * Example: "vendredi 22 juillet de 09:30 à 12:30 [#0001 Prénom NOM]"
     */
    public function __toString()
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%A %e %B", $this->getStart()->getTimestamp()) . ' de ' . $this->getStart()->format('H:i') . ' à ' . $this->getEnd()->format('H:i') . ' [' . $this->getShifter() . ']';
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->createdAt = new \DateTime();
    }

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
     * Set start
     *
     * @param \DateTime $start
     *
     * @return Shift
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
     * @return Shift
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
     * Set wasCarriedOut
     *
     * @param boolean $wasCarriedOut
     *
     * @return BookedShift
     */
    public function setWasCarriedOut($wasCarriedOut)
    {
        $this->wasCarriedOut = $wasCarriedOut;

        return $this;
    }

    /**
     * Validate shift participation
     *
     * @return BookedShift
     */
    public function validateShiftParticipation()
    {
        $this->setWasCarriedOut(true);

        return $this;
    }

    /**
     * Invalidate shift participation
     *
     * @return BookedShift
     */
    public function invalidateShiftParticipation()
    {
        $this->setWasCarriedOut(false);

        return $this;
    }

    /**
     * Get wasCarriedOut
     *
     * @return bool
     */
    public function getWasCarriedOut()
    {
        return $this->wasCarriedOut;
    }

    /**
     * Set booker
     *
     * @param \AppBundle\Entity\User $booker
     *
     * @return BookedShift
     */
    public function setBooker(\AppBundle\Entity\User $booker = null)
    {
        $this->booker = $booker;

        return $this;
    }

    /**
     * Get booker
     *
     * @return \AppBundle\Entity\User
     */
    public function getBooker()
    {
        return $this->booker;
    }

    /**
     * Set shifter
     *
     * @param \AppBundle\Entity\Beneficiary $shifter
     *
     * @return BookedShift
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


    public function getDuration()
    {
        $diff = date_diff($this->start, $this->end);
        return $diff->h * 60 + $diff->i;
    }

    public function getIntervalCode()
    {
        return $this->start->format("h-i") . $this->end->format("h-i");
    }

    /**
     * Set formation
     *
     * @param \AppBundle\Entity\Formation formation
     *
     * @return Shift
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
     * Set job
     *
     * @param \AppBundle\Entity\Job $job
     *
     * @return Shift
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
     * free // unbook
     *
     * @return \AppBundle\Entity\Shift
     */
    public function free()
    {
        $this->setBooker(null);
        $this->setBookedTime(null);
        $this->setShifter(null);
        $this->setFixe(false);
        return $this;
    }

    /**
     * Return true if the shift is in the past
     *
     * @return boolean
     */
    public function getIsPast()
    {
        $now = new \DateTime('now');
        return $this->end < $now;
    }

    /**
     * Return true if the shift is now
     *
     * @return boolean
     */
    public function getIsCurrent()
    {
        $now = new \DateTime('now');
        return ($this->start < $now) && ($now < $this->end);
    }

    /**
     * Return true if the shift is now or in the past
     *
     * @return boolean
     */
    public function getIsPastOrCurrent()
    {
        return ($this->getIsPast() or $this->getIsCurrent());
    }

    /**
     * Return true if the shift is in the future
     *
     * @return boolean
     */
    public function getIsFuture()
    {
        return !$this->getIsPastOrCurrent();
    }

    /**
     * Return true if the shift is not in the past, not current, and close enough
     *
     * @return boolean
     */
    public function getIsUpcoming()
    {
        return $this->isBefore('2 days');
    }

    /**
     * Return true if the shift starts before the duration given as parameter
     *
     * @param string $duration
     *
     * @return boolean
     */
    public function isBefore($duration)
    {
        $futureDate = new \DateTime($duration);
        $futureDate->setTime(23, 59, 59);
        return $this->getIsFuture() && ($this->start < $futureDate);
    }

    /**
     * Set lastShifter
     *
     * @param \AppBundle\Entity\Beneficiary $lastShifter
     *
     * @return Shift
     */
    public function setLastShifter(\AppBundle\Entity\Beneficiary $lastShifter = null)
    {
        $this->lastShifter = $lastShifter;

        return $this;
    }

    /**
     * Get lastShifter
     *
     * @return \AppBundle\Entity\Beneficiary
     */
    public function getLastShifter()
    {
        return $this->lastShifter;
    }

    /**
     * Add timeLog
     *
     * @param \AppBundle\Entity\TimeLog $timeLog
     *
     * @return Shift
     */
    public function addTimeLog(\AppBundle\Entity\TimeLog $timeLog)
    {
        $this->timeLogs[] = $timeLog;

        return $this;
    }

    /**
     * Remove timeLog
     *
     * @param \AppBundle\Entity\TimeLog $timeLog
     */
    public function removeTimeLog(\AppBundle\Entity\TimeLog $timeLog)
    {
        $this->timeLogs->removeElement($timeLog);
    }

    /**
     * Get timeLogs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTimeLogs()
    {
        return $this->timeLogs;
    }

    /**
     * @return bool
     */
    public function isLocked(): ?bool
    {
        return $this->locked;
    }

    /**
     * @param bool $locked
     */
    public function setLocked(?bool $locked): void
    {
        $this->locked = $locked;
    }

    /**
     * @return bool
     */
    public function isFixe(): ?bool
    {
        return $this->fixe;
    }

    /**
     * @param bool $fixe
     */
    public function setFixe(?bool $fixe): void
    {
        $this->fixe = $fixe;
    }

    /**
     * Set position
     *
     * @param \AppBundle\Entity\PeriodPosition $position
     *
     * @return Shift
     */
    public function setPosition(\AppBundle\Entity\PeriodPosition $position = null)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set createdBy.
     *
     * @param \AppBundle\Entity\User $createBy
     *
     * @return Shift
     */
    public function setCreatedBy(\AppBundle\Entity\User $createdBy = null)
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * Get createdBy.
     *
     * @return \AppBundle\Entity\User
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * Return true if this is the first ever shift by the shifter
     *
     * @return bool
     */
    public function isFirstByShifter()
    {
        if ($this->getShifter()) {
            // last? beneficiary->shifts are ordered by start DESC
            if ($this === $this->getShifter()->getShifts()->last()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate token from key
     */
    public function getTmpToken($key = '')
    {
        return md5($this->getId().$this->getStart()->format('d/m/Y').$this->getEnd()->format('d/m/Y').$key);
    }

    /**
     * Example: "vendredi 22 juillet de 9h30 à 12h30"
     */
    public function getDisplayDateLongWithTime()
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%A %e %B", $this->getStart()->getTimestamp()) . ' de ' . $this->getStart()->format('G\\hi') . ' à ' . $this->getEnd()->format('G\\hi');
    }

    /**
     * Example: "22/07/2022 de 9h30 à 12h30"
     */
    public function getDisplayDateWithTime()
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return $this->getStart()->format('d/m/Y') . ' de ' . $this->getStart()->format('G\\hi') . ' à ' . $this->getEnd()->format('G\\hi');
    }
}
