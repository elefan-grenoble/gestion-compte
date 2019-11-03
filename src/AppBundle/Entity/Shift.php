<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Shift
 *
 * @ORM\Table(name="shift")
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
     * @ORM\Column(name="is_dismissed", type="boolean", options={"default" : 0})
     */
    private $isDismissed;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="dismissed_time", type="datetime", nullable=true)
     */
    private $dismissedTime;

    /**
     * @var string
     *
     * @ORM\Column(name="dismissed_reason", type="string", length=255, nullable=true)
     */
    private $dismissedReason;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="shifts")
     * @ORM\JoinColumn(name="shifter_id", referencedColumnName="id")
     */
    private $shifter;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="booked_shifts")
     * @ORM\JoinColumn(name="booker_id", referencedColumnName="id")
     */
    private $booker;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="reservedShifts")
     * @ORM\JoinColumn(name="last_shifter_id", referencedColumnName="id")
     */
    private $lastShifter;

    /**
     * One Period has One Formation.
     * @ORM\ManyToOne(targetEntity="Formation")
     * @ORM\JoinColumn(name="formation_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $formation;

    /**
     * One Period has One Job.
     * @ORM\ManyToOne(targetEntity="Job", inversedBy="shifts")
     * @ORM\JoinColumn(name="job_id", referencedColumnName="id", nullable=false)
     */
    private $job;

    /**
     * @ORM\OneToMany(targetEntity="TimeLog", mappedBy="shift")
     */
    private $timeLogs;

    /**
     * @var bool
     *
     * @ORM\Column(name="locked", type="boolean", options={"default" : 0}, nullable=false)
     */
    private $locked = false;

    public function __construct()
    {
        $this->isDismissed = false;
    }

    public function __toString()
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%A %e %B de %R", $this->getStart()->getTimestamp()).' à '.strftime("%R", $this->getEnd()->getTimestamp()).' ['.$this->getShifter().']';
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
     * Set isDismissed
     *
     * @param boolean $isDismissed
     *
     * @return BookedShift
     */
    public function setIsDismissed($isDismissed)
    {
        $this->isDismissed = $isDismissed;

        return $this;
    }

    /**
     * Get isDismissed
     *
     * @return bool
     */
    public function getIsDismissed()
    {
        return $this->isDismissed;
    }

    /**
     * Set dismissedTime
     *
     * @param \DateTime $dismissedTime
     *
     * @return BookedShift
     */
    public function setDismissedTime($dismissedTime)
    {
        $this->dismissedTime = $dismissedTime;

        return $this;
    }

    /**
     * Get dismissedTime
     *
     * @return \DateTime
     */
    public function getDismissedTime()
    {
        return $this->dismissedTime;
    }

    /**
     * Set dismissedReason
     *
     * @param string $dismissedReason
     *
     * @return BookedShift
     */
    public function setDismissedReason($dismissedReason)
    {
        $this->dismissedReason = $dismissedReason;

        return $this;
    }

    /**
     * Get dismissedReason
     *
     * @return string
     */
    public function getDismissedReason()
    {
        return $this->dismissedReason;
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
    public function free(){
        $this->setBooker(null);
        $this->setBookedTime(null);
        $this->setDismissedReason('');
        $this->setIsDismissed(false);
        $this->setDismissedTime(null);
        $this->setShifter(null);
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
        return ($this->start < $now) && ($now < $this->end );
    }

    /**
     * Return true if the shift is not in the past, not current, and close enough
     *
     * @return boolean
     */
    public function getIsUpcoming(){
        $intwodays = new \DateTime('2 days');
        return !$this->getIsPast() && !$this->getIsCurrent() && ($intwodays > $this->start);
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

    public function getTmpToken($key = ''){
        return md5($this->getId().$this->getStart()->format('d-m-Y').$this->getEnd()->format('d-m-Y').$key);
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
}
