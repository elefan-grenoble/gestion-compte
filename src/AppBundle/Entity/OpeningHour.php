<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * OpeningHour
 *
 * @ORM\Table(name="opening_hour")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\OpeningHourRepository")
 */
class OpeningHour
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
     * @ORM\Column(name="day_of_week", type="smallint")
     */
    private $dayOfWeek;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="start", type="time", nullable=true)
     */
    private $start;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="end", type="time", nullable=true)
     */
    private $end;

    /**
     * @var bool
     *
     * @ORM\Column(name="closed", type="boolean", options={"default" : 0})
     */
    private $closed;

    /**
     * @ORM\ManyToOne(targetEntity="OpeningHourKind", inversedBy="openingHours", fetch="EAGER")
     * @ORM\JoinColumn(name="opening_hour_kind_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $kind;

    /**
     * @var \DateTime
     * 
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

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
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set dayOfWeek
     *
     * @param integer $dayOfWeek
     *
     * @return Period
     */
    public function setDayOfWeek($dayOfWeek)
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    /**
     * Get dayOfWeek
     *
     * @return int
     */
    public function getDayOfWeek()
    {
        return $this->dayOfWeek;
    }

    /**
     * Get dayOfWeekString
     *
     * @return int
     */
    public function getDayOfWeekString()
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%A", strtotime("Monday + {$this->dayOfWeek} days"));
    }

    /**
     * Set start
     *
     * @param \DateTime $start
     *
     * @return Period
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
     * @return Period
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
     * @Assert\IsTrue(message="L'heure de début doit être avant celle de fin")
     */
    public function isStartBeforeEnd()
    {
        if (!$this->closed) {
            return $this->start < $this->end;
        }
        return true;
    }

    /**
     * Set closed
     *
     * @param boolean $closed
     *
     * @return OpeningHour
     */
    public function setClosed($closed)
    {
        $this->closed = $closed;

        return $this;
    }

    /**
     * Get closed
     *
     * @return bool
     */
    public function getClosed()
    {
        return $this->closed;
    }

    /**
     * Set kind
     *
     * @param \AppBundle\Entity\OpeningHourKind $openingHourKind
     *
     * @return Event
     */
    public function setKind(\AppBundle\Entity\OpeningHourKind $openingHourKind = null)
    {
        $this->kind = $openingHourKind;

        return $this;
    }

    /**
     * Get kind
     *
     * @return OpeningHourKind
     */
    public function getKind()
    {
        return $this->kind;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $date
     *
     * @return OpeningHour
     */
    public function setCreatedAt($date)
    {
        $this->createdAt = $date;

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
}
