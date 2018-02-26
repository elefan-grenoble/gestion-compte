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
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="booked_shifts")
     * @ORM\JoinColumn(name="shifter_id", referencedColumnName="id")
     */
    private $shifter;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="shifts")
     * @ORM\JoinColumn(name="booker_id", referencedColumnName="id")
     */
    private $booker;

    /**
     * One Period has One Role.
     * @ORM\ManyToOne(targetEntity="Role")
     * @ORM\JoinColumn(name="role_id", referencedColumnName="id")
     */
    private $role;

    /**
     * One Period has One Job.
     * @ORM\ManyToOne(targetEntity="Job")
     * @ORM\JoinColumn(name="job_id", referencedColumnName="id")
     */
    private $job;


    public function __construct()
    {
        $this->isDismissed = false;
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
     * Set role
     *
     * @param \AppBundle\Entity\Role $role
     *
     * @return Shift
     */
    public function setRole(\AppBundle\Entity\Role $role = null)
    {
        $this->role = $role;

        return $this;
    }

    /**
     * Get role
     *
     * @return \AppBundle\Entity\Role
     */
    public function getRole()
    {
        return $this->role;
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
}
