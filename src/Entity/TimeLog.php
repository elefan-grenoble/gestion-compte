<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TimeLog
 *
 * @ORM\Table(name="time_log")
 * @ORM\Entity(repositoryClass="App\Repository\TimeLogRepository")
 */
class TimeLog
{
    const TYPE_CUSTOM = 0;

    const TYPE_SHIFT = 1;

    const TYPE_CYCLE_END = 2;
    const TYPE_CYCLE_END_FROZEN = 3;
    const TYPE_CYCLE_END_EXPIRED_REGISTRATION = 4;
    const TYPE_CYCLE_END_REGULATE_OPTIONAL_SHIFTS = 5;

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
     * @ORM\Column(name="date", type="datetime")
     */
    private $date;

    /**
     * @var int
     *
     * @ORM\Column(name="time", type="smallint")
     */
    private $time;

    /**
     * @var int
     *
     * @ORM\Column(name="type", type="smallint")
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @ORM\ManyToOne(targetEntity="Membership", inversedBy="timeLogs")
     * @ORM\JoinColumn(name="membership_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    private $membership;

    /**
     * @ORM\ManyToOne(targetEntity="Shift", inversedBy="timeLogs")
     * @ORM\JoinColumn(name="shift_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $shift;

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
     * Set date
     *
     * @param \DateTime $date
     *
     * @return TimeLog
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date
     *
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set time
     *
     * @param integer $time
     *
     * @return TimeLog
     */
    public function setTime($time)
    {
        $this->time = $time;

        return $this;
    }

    /**
     * Get time
     *
     * @return int
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return TimeLog
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set shift
     *
     * @param \App\Entity\Shift $shift
     *
     * @return TimeLog
     */
    public function setShift(\App\Entity\Shift $shift = null)
    {
        $this->shift = $shift;

        return $this;
    }

    /**
     * Get shift
     *
     * @return \App\Entity\Shift
     */
    public function getShift()
    {
        return $this->shift;
    }

    /**
     * @return mixed
     */
    public function getMembership()
    {
        return $this->membership;
    }

    /**
     * @param mixed $membership
     */
    public function setMembership($membership)
    {
        $this->membership = $membership;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }


    /**
     * @return string
     */
    public function getComputedDescription(): string
    {
        switch ($this->type) {
            case self::TYPE_CUSTOM:
                return $this->description;
            case self::TYPE_SHIFT:
                if ($this->shift) {
                    setlocale(LC_TIME, 'fr_FR.UTF8');
                    return strftime("Créneau de %R", $this->shift->getStart()->getTimestamp()) . ' à ' . strftime("%R", $this->shift->getEnd()->getTimestamp()) . ' [' . $this->shift->getShifter() . ']';
                } else {
                    return "Créneau (non renseigné)";
                }
            case self::TYPE_CYCLE_END:
                return "Début de cycle";
            case self::TYPE_CYCLE_END_FROZEN:
                return "Début de cycle (compte gelé)";
            case self::TYPE_CYCLE_END_EXPIRED_REGISTRATION:
                return "Début de cycle (compte expiré)";
            case self::TYPE_CYCLE_END_REGULATE_OPTIONAL_SHIFTS:
                return "Régulation du bénévolat facultatif";
        }
        return "Type de log de temps inconnu: " . $this->type;
    }

}
