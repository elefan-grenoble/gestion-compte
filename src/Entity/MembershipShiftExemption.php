<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * MembershipShiftExemption
 *
 * @ORM\Table(name="membership_shift_exemption")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="App\Repository\MembershipShiftExemptionRepository")
 * @UniqueEntity(
 *     fields={"membership", "start"},
 * )
 * @UniqueEntity(
 *     fields={"membership", "end"},
 * )
 */
class MembershipShiftExemption
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
     * @ORM\ManyToOne(targetEntity="ShiftExemption", inversedBy="membershipShiftExemptions", fetch="EAGER")
     * @ORM\JoinColumn(name="shift_exemption_id", referencedColumnName="id")
     */
    private $shiftExemption;

    /**
     * @var string
     * @Assert\NotBlank
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=false)
     */
    private $description;

    /**
     * @ORM\ManyToOne(targetEntity="Membership", inversedBy="membershipShiftExemptions")
     * @ORM\JoinColumn(name="membership_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    private $membership;

    /**
     * @Assert\Date
     *
     * @ORM\Column(name="start", type="date")
     */
    private $start;

    /**
     * @Assert\Date
     * @Assert\GreaterThan(propertyPath="start")
     *
     * @ORM\Column(name="end", type="date")
     */
    private $end;

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
     * Set shiftExemption
     *
     * @param \App\Entity\ShiftExemption $shiftExemption
     *
     * @return MembershipShiftExemption
     */
    public function setShiftExemption(\App\Entity\ShiftExemption $shiftExemption)
    {
        $this->shiftExemption = $shiftExemption;

        return $this;
    }

    /**
     * Get shiftExemption
     *
     * @return ShiftExemption
     */
    public function getShiftExemption()
    {
        return $this->shiftExemption;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return MembershipShiftExemption
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
     * Get membership
     *
     * @return Membership
     */
    public function getMembership()
    {
        return $this->membership;
    }

    /**
     * Set membership
     *
     * @param \App\Entity\Membership $membership
     */
    public function setMembership($membership)
    {
        $this->membership = $membership;
    }

    /**
     * Set start
     *
     * @param \DateTime $start
     *
     * @return MembershipShiftExemption
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
     * @return MembershipShiftExemption
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
     * Set createdAt
     *
     * @param \DateTime $date
     *
     * @return MembershipShiftExemption
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

    /**
     * Set createdBy
     *
     * @param \App\Entity\User $createBy
     *
     * @return MembershipShiftExemption
     */
    public function setCreatedBy(\App\Entity\User $createdBy = null)
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * Get createdBy
     *
     * @return \App\Entity\User
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * @Assert\IsTrue(message="La date de début doit être avant celle de fin")
     */
    public function isStartBeforeEnd()
    {
        return $this->start < $this->end;
    }

    /**
     * Return if the membershipShiftExemption is past for a given date
     *
     * @param \DateTime $date
     * @return boolean
     */
    public function isPast(\Datetime $date = null)
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        return $date > $this->end;
    }

    /**
     * Return if the membershipShiftExemption is upcoming for a given date
     *
     * @param \DateTime $date
     * @return boolean
     */
    public function isUpcoming(\Datetime $date = null)
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        return $date < $this->start;
    }

    /**
     * Return if the membershipShiftExemption is current (ongoing) for a given date
     *
     * @param \DateTime $date
     * @return boolean
     */
    public function isCurrent(\Datetime $date = null)
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        return !$this->isPast($date) && !$this->isUpcoming($date);
    }
}
