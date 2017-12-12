<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BookedShift
 *
 * @ORM\Table(name="booked_shift")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\BookedShiftRepository")
 */
class BookedShift
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
     * @ORM\Column(name="booked_time", type="datetime")
     */
    private $bookedTime;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_dismissed", type="boolean")
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
     * @ORM\ManyToOne(targetEntity="Shift", inversedBy="booked_shifts")
     * @ORM\JoinColumn(name="shift_id", referencedColumnName="id")
     */
    private $shift;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="booked_shifts")
     * @ORM\JoinColumn(name="shifter_id", referencedColumnName="id")
     */
    private $schifter;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="shifts")
     * @ORM\JoinColumn(name="booker_id", referencedColumnName="id")
     */
    private $booker;

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
     * Set shift
     *
     * @param \AppBundle\Entity\Shift $shift
     *
     * @return BookedShift
     */
    public function setShift(\AppBundle\Entity\Shift $shift = null)
    {
        $this->shift = $shift;

        return $this;
    }

    /**
     * Get shift
     *
     * @return \AppBundle\Entity\Shift
     */
    public function getShift()
    {
        return $this->shift;
    }

    /**
     * Set schifter
     *
     * @param \AppBundle\Entity\Beneficiary $schifter
     *
     * @return BookedShift
     */
    public function setSchifter(\AppBundle\Entity\Beneficiary $schifter = null)
    {
        $this->schifter = $schifter;

        return $this;
    }

    /**
     * Get schifter
     *
     * @return \AppBundle\Entity\Beneficiary
     */
    public function getSchifter()
    {
        return $this->schifter;
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
}
