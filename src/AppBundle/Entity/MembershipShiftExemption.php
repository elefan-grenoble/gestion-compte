<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * MembershipShiftExemption
 *
 * @ORM\Table(name="membership_shift_exemption")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\MembershipShiftExemptionRepository")
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
     * @ORM\ManyToOne(targetEntity="ShiftExemption")
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
     *
     * @ORM\Column(name="end", type="date")
     * @Assert\GreaterThan(propertyPath="start")
     */
    private $end;


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
     * @ORM\PrePersist
     */
    public function setCreatedAt()
    {
        $this->createdAt = new \DateTime();
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
     * Set createdBy.
     *
     * @param \AppBundle\Entity\User $createBy
     *
     * @return MembershipShiftExemption
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
     * Set shiftExemption.
     *
     * @param \AppBundle\Entity\ShiftExemption $shiftExemption
     *
     * @return MembershipShiftExemption
     */
    public function setShiftExemption(\AppBundle\Entity\ShiftExemption $shiftExemption)
    {
        $this->shiftExemption = $shiftExemption;

        return $this;
    }

    /**
     * Get shiftExemption.
     *
     * @return ShiftExemption
     */
    public function getShiftExemption()
    {
        return $this->shiftExemption;
    }

    /**
     * Set description.
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
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Get membership.
     *
     * @return Membership
     */
    public function getMembership()
    {
        return $this->membership;
    }

    /**
     * Set membership.
     *
     * @param \AppBundle\Entity\Membership $membership
     */
    public function setMembership($membership)
    {
        $this->membership = $membership;
    }

    /**
     * Set start.
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
     * Get start.
     *
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set end.
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
     * Get end.
     *
     * @return \DateTime
     */
    public function getEnd()
    {
        return $this->end;
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
