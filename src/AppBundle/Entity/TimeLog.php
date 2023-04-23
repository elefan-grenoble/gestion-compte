<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TimeLog
 *
 * @ORM\Table(name="time_log")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TimeLogRepository")
 */
class TimeLog
{
    const TYPE_CUSTOM = 0;

    const TYPE_SHIFT_VALIDATED = 1;
    const TYPE_SHIFT_INVALIDATED = 10;
    const TYPE_SHIFT_FREED_SAVING = 21;

    const TYPE_CYCLE_END = 2;
    const TYPE_CYCLE_END_FROZEN = 3;
    const TYPE_CYCLE_END_EXPIRED_REGISTRATION = 4;
    const TYPE_CYCLE_END_EXEMPTED = 6;
    const TYPE_CYCLE_END_SAVING = 7;

    const TYPE_REGULATE_OPTIONAL_SHIFTS = 5;

    const TYPE_SAVING = 20;

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
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="created_by_id", referencedColumnName="id")
     */
    private $createdBy;

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
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $requestRoute;

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
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set createdBy
     *
     * @param \AppBundle\Entity\User $createBy
     *
     * @return TimeLog
     */
    public function setCreatedBy(\AppBundle\Entity\User $user = null)
    {
        $this->createdBy = $user;

        return $this;
    }

    /**
     * Get createdBy
     *
     * @return \AppBundle\Entity\User
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
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
     * @param \AppBundle\Entity\Shift $shift
     *
     * @return TimeLog
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
     * Set created_at
     *
     * @param \DateTime $created_at
     *
     * @return TimeLog
     */
    public function setCreatedAt($date)
    {
        $this->createdAt = $date;

        return $this;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getRequestRoute(): ?string
    {
        return $this->requestRoute;
    }

    public function setRequestRoute(?string $requestRoute): self
    {
        $this->requestRoute = $requestRoute;

        return $this;
    }

    /**
     * @return string
     */
    public function getTypeDisplay(): string
    {
        switch ($this->type) {
            case self::TYPE_CUSTOM:
                return $this->description;
            case self::TYPE_SHIFT_VALIDATED:
                return "Créneau validé";
            case self::TYPE_SHIFT_INVALIDATED:
                return "Créneau invalidé";
            case self::TYPE_SHIFT_FREED_SAVING:
                return "Créneau libéré et compteur temps incrémenté (grâce au compteur épargne)";
            case self::TYPE_CYCLE_END:
                return "Début de cycle";
            case self::TYPE_CYCLE_END_FROZEN:
                return "Début de cycle (compte gelé)";
            case self::TYPE_CYCLE_END_EXPIRED_REGISTRATION:
                return "Début de cycle (compte expiré)";
            case self::TYPE_CYCLE_END_EXEMPTED:
                return "Début de cycle (compte exempté de créneau - exemption n°" . join(",", $this->membership->getMembershipShiftExemptions()->filter(function($membershipShiftExemption) {
                    return $membershipShiftExemption->isCurrent($this->createdAt);
                })->map(function($element) {
                    return $element->getId();
                })->toArray()) . ")";
            case self::TYPE_CYCLE_END_SAVING:
                if ($this->getTime() > 0) {
                    return "Début de cycle (compteur temps incrémenté grâce au compteur épargne)";
                } else {
                    return "Début de cycle " . $this->description;
                }
            case self::TYPE_REGULATE_OPTIONAL_SHIFTS:
                return "Régulation du bénévolat facultatif";
            case self::TYPE_SAVING:
                if ($this->getTime() >= 0) {
                    return "Compteur épargne incrémenté";
                } else {
                    return "Compteur épargne décrémenté";
                }
        }
        return "Type de log de temps inconnu : " . $this->type;
    }
}
