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
     * @var int
     *
     * @ORM\Column(name="max_shifters_nb", type="smallint")
     */
    private $maxShiftersNb;

    /**
     * @ORM\OneToMany(targetEntity="BookedShift", mappedBy="shift",cascade={"persist", "remove"})
     */
    private $booked_shifts;

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
     * Set maxShiftersNb
     *
     * @param integer $maxShiftersNb
     *
     * @return Shift
     */
    public function setMaxShiftersNb($maxShiftersNb)
    {
        $this->maxShiftersNb = $maxShiftersNb;

        return $this;
    }

    /**
     * Get maxShiftersNb
     *
     * @return int
     */
    public function getMaxShiftersNb()
    {
        return $this->maxShiftersNb;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->booked_shifts = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add bookedShift
     *
     * @param \AppBundle\Entity\BookedShift $bookedShift
     *
     * @return Shift
     */
    public function addBookedShift(\AppBundle\Entity\BookedShift $bookedShift)
    {
        $this->booked_shifts[] = $bookedShift;

        return $this;
    }

    /**
     * Remove bookedShift
     *
     * @param \AppBundle\Entity\BookedShift $bookedShift
     */
    public function removeBookedShift(\AppBundle\Entity\BookedShift $bookedShift)
    {
        $this->booked_shifts->removeElement($bookedShift);
    }

    /**
     * Get bookedShifts
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBookedShifts()
    {
        return $this->booked_shifts;
    }

    public function getDuration()
    {
        $diff = date_diff($this->start, $this->end);
        return $diff->h * 60 + $diff->i;
    }

    public function isBookedBy($userId)
    {
        return $this->getBookedShifts()->filter(function($shift) use ($userId) {
            return $shift->getShifter()->getUser()->getId() == $userId;
        })->count() > 0;
    }

    public function getDismissedShifts()
    {
        return $this->getBookedShifts()->filter(function($shift) {
            return $shift->getIsDismissed();
        });
    }

    public function getNotDismissedShifts()
    {
        return $this->getBookedShifts()->filter(function($shift) {
            return !$shift->getIsDismissed();
        });
    }

    public function getRemainingShifters()
    {
        return $this->getMaxShiftersNb() - $this->getNotDismissedShifts()->count();
    }
}
