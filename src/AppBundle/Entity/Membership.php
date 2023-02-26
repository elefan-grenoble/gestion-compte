<?php

namespace AppBundle\Entity;

use DateTime;
use AppBundle\Repository\RegistrationRepository;
use Doctrine\ORM\EntityRepository;
use FOS\OAuthServerBundle\Model\ClientInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\OrderBy;

/**
 * Membership
 *
 * @ORM\Table(name="membership", uniqueConstraints={@ORM\UniqueConstraint(columns={"member_number"})})
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\MembershipRepository")
 * @UniqueEntity(fields={"member_number"}, message="Ce numéro de membre existe déjà")
 */
class Membership
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="bigint")
     * @Assert\NotBlank(message="Merci d'entrer votre numéro d'adhérent")
     */
    protected $member_number;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="withdrawn", type="boolean", nullable=false, options={"default" : 0})
     */
    private $withdrawn;

    /**
     * @var bool
     *
     * @ORM\Column(name="withdrawn_date", type="date", nullable=true)
     */
    private $withdrawnDate;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="withdrawn_by_id", referencedColumnName="id")
     */
    private $withdrawnBy;

    /**
     * @var bool
     *
     * @ORM\Column(name="frozen", type="boolean", nullable=false, options={"default" : 0})
     */
    private $frozen;

    /**
     * @var bool
     *
     * @ORM\Column(name="frozen_change", type="boolean", nullable=false, options={"default" : 0})
     */
    private $frozen_change;

    /**
     * @ORM\OneToMany(targetEntity="Registration", mappedBy="membership",cascade={"persist", "remove"})
     * @OrderBy({"date" = "DESC"})
     */
    private $registrations;

    /**
     * @ORM\OneToMany(targetEntity="Beneficiary", mappedBy="membership", cascade={"persist", "remove"})
     */
    private $beneficiaries;

    /**
     * @var Beneficiary
     * One Membership has One Main Beneficiary.
     * @ORM\OneToOne(targetEntity="Beneficiary",cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="main_beneficiary_id", referencedColumnName="id", onDelete="SET NULL")
     * @Assert\Valid
     */
    private $mainBeneficiary;

    /**
     * @ORM\OneToMany(targetEntity="Note", mappedBy="subject",cascade={"persist", "remove"})
     * @OrderBy({"createdAt" = "ASC"})
     */
    private $notes;

    /**
     * @ORM\OneToMany(targetEntity="Proxy", mappedBy="giver", cascade={"persist", "remove"})
     */
    private $given_proxies;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="first_shift_date", type="date", nullable=true)
     */
    private $firstShiftDate;

    /**
     * @ORM\OneToMany(targetEntity="TimeLog", mappedBy="membership", cascade={"persist", "remove"})
     * @OrderBy({"createdAt" = "DESC", "type" = "DESC"})
     */
    private $timeLogs;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\OneToMany(targetEntity="MembershipShiftExemption", mappedBy="membership", cascade={"persist", "remove"})
     * @OrderBy({"createdAt" = "DESC"})
     */
    private $membershipShiftExemptions;

    /**
     * Membership constructor.
     */
    public function __construct()
    {
        $this->registrations = new ArrayCollection();
        $this->beneficiaries = new ArrayCollection();
        $this->timeLogs = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getDisplayMemberNumber();
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->createdAt = new \DateTime();
    }

    public function getTmpToken($key = '')
    {
        return md5($this->getId().$this->getMemberNumber().$key.date('d'));
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
     * Set memberNumber
     *
     * @param integer $memberNumber
     *
     * @return Membership
     */
    public function setMemberNumber($memberNumber)
    {
        $this->member_number = $memberNumber;
        return $this;
    }

    /**
     * Get memberNumber
     *
     * @return integer
     */
    public function getMemberNumber()
    {
        return $this->member_number;
    }

    /**
     * Add registration
     *
     * @param \AppBundle\Entity\Registration $registration
     *
     * @return Membership
     */
    public function addRegistration(\AppBundle\Entity\Registration $registration)
    {
        $this->registrations[] = $registration;
        return $this;
    }

    /**
     * Remove registration
     *
     * @param \AppBundle\Entity\Registration $registration
     */
    public function removeRegistration(\AppBundle\Entity\Registration $registration)
    {
        $this->registrations->removeElement($registration);
    }

    /**
     * Get registrations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRegistrations()
    {
        return $this->registrations;
    }

    /**
     * Add beneficiary
     *
     * @param \AppBundle\Entity\Beneficiary $beneficiary
     *
     * @return Membership
     */
    public function addBeneficiary(\AppBundle\Entity\Beneficiary $beneficiary)
    {
        $this->beneficiaries[] = $beneficiary;
        return $this;
    }

    /**
     * Remove beneficiary
     *
     * @param \AppBundle\Entity\Beneficiary $beneficiary
     */
    public function removeBeneficiary(\AppBundle\Entity\Beneficiary $beneficiary)
    {
        $this->beneficiaries->removeElement($beneficiary);
    }

    /**
     * Get beneficiaries
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBeneficiaries()
    {
        return $this->beneficiaries;
    }

    /**
     * Get beneficiaries (with main in first position)
     * Why? because beneficiaries are ordered by id ASC
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBeneficiariesWithMainInFirstPosition()
    {
        $beneficiaries[] = $this->getMainBeneficiary();
        if ($this->getBeneficiaries()->count() > 1) {
            foreach ($this->getBeneficiaries() as $beneficiary) {
                if ($beneficiary !== $this->getMainBeneficiary()) {
                    $beneficiaries[] = $beneficiary;
                }
            }
        }
        return $beneficiaries;
    }

    /**
     * Get member_number & list of beneficiaries
     *
     * @return string
     */
    public function getMemberNumberWithBeneficiaryListString()
    {
        $memberNumberWithBeneficiaryListString = '#' . $this->getMemberNumber();
        foreach ($this->getBeneficiariesWithMainInFirstPosition() as $key => $beneficiary) {
            if ($key > 0) {
                $memberNumberWithBeneficiaryListString .= ' &';
            }
            $memberNumberWithBeneficiaryListString .= ' '. $beneficiary->getDisplayName();
        }
        return $memberNumberWithBeneficiaryListString;
    }

    /**
     * Set mainBeneficiary
     *
     * @param \AppBundle\Entity\Beneficiary $mainBeneficiary
     *
     * @return Membership
     */
    public function setMainBeneficiary(\AppBundle\Entity\Beneficiary $mainBeneficiary = null)
    {
        if ($mainBeneficiary) {
            $this->addBeneficiary($mainBeneficiary);
            $mainBeneficiary->setMembership($this);
        }

        $this->mainBeneficiary = $mainBeneficiary;

        return $this;
    }

    /**
     * Get mainBeneficiary
     *
     * @return \AppBundle\Entity\Beneficiary
     */
    public function getMainBeneficiary()
    {
        if (!$this->mainBeneficiary){
            if ($this->getBeneficiaries()->count())
                $this->setMainBeneficiary($this->getBeneficiaries()->first());
        }
        return $this->mainBeneficiary;
    }

    /**
     * Set withdrawn
     *
     * @param boolean $withdrawn
     *
     * @return Membership
     */
    public function setWithdrawn($withdrawn)
    {
        $this->withdrawn = $withdrawn;
        if ($this->withdrawn == false) {
            $this->withdrawnDate = null;
            $this->withdrawnBy = null;
        }
        return $this;
    }

    /**
     * Get isWithdrawn
     *
     * @return boolean
     */
    public function isWithdrawn()
    {
        return $this->withdrawn;
    }

    /**
     * Get withdrawn
     *
     * @return boolean
     */
    public function getWithdrawn()
    {
        return $this->withdrawn;
    }

    /**
     * Set withdrawnDate
     *
     * @param \DateTime $createdAt
     *
     * @return Membership
     */
    public function setWithdrawnDate($date)
    {
        $this->withdrawnDate = $date;
        return $this;
    }

    /**
     * Get withdrawnDate
     *
     * @return \DateTime
     */
    public function getWithdrawnDate()
    {
        return $this->withdrawnDate;
    }

    /**
     * Set withdrawnBy
     *
     * @param \AppBundle\Entity\User $createBy
     *
     * @return TimeLog
     */
    public function setWithdrawnBy(\AppBundle\Entity\User $user = null)
    {
        $this->withdrawnBy = $user;
        return $this;
    }

    /**
     * Get withdrawnBy
     *
     * @return \AppBundle\Entity\User
     */
    public function getWithdrawnBy()
    {
        return $this->withdrawnBy;
    }

    public function getCommissions()
    {
        $commissions = array();
        foreach ($this->getBeneficiaries() as $beneficiary){
            $commissions = array_merge($beneficiary->getCommissions()->toArray(),$commissions);
        }
        return new ArrayCollection($commissions);
    }

    public function getOwnedCommissions()
    {
        return $this->getCommissions()->filter(function($commission) {
            $r = false;
            foreach ($commission->getOwners() as $owner){
                if ($this->getBeneficiaries()->contains($owner))
                    return true;
            }
            return false;
        });
    }

    /**
     * Set frozen
     *
     * @param boolean $frozen
     *
     * @return Membership
     */
    public function setFrozen($frozen)
    {
        $this->frozen = $frozen;

        return $this;
    }

    /**
     * Get frozen
     * @deprecated illogic isFlying, isWithdrawn but getFrozen
     * @return boolean
     */
    public function getFrozen()
    {
        return $this->frozen;
    }

    /**
     * return if the member is frozen
     *
     * @return boolean
     */
    public function isFrozen()
    {
        return $this->frozen;
    }

    /**
     * Set frozen_change
     *
     * @param boolean $frozen_change
     *
     * @return Membership
     */
    public function setFrozenChange($frozen_change)
    {
        $this->frozen_change = $frozen_change;
        return $this;
    }

    /**
     * Get frozen_change
     *
     * @return boolean
     */
    public function getFrozenChange()
    {
        return $this->frozen_change;
    }

    /**
     * Get lastRegistration
     *
     * @return \AppBundle\Entity\Registration
     */
    public function getLastRegistration()
    {
        return $this->getRegistrations()->first();
    }

    /**
     * Return if the member has a valid registration before the given date
     *
     * @param \DateTime $date
     * @return bool
     */
    public function hasValidRegistrationBefore($date)
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        foreach ($this->getRegistrations() as $registration) {
            if ($registration->getDate() < $date) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all reserved shifts for all beneficiaries
     */
    public function getReservedShifts()
    {
        $shifts = new ArrayCollection();
        foreach ($this->getBeneficiaries() as $beneficiary) {
            foreach ($beneficiary->getReservedShifts() as $shift) {
                $shifts->add($shift);
            }
        }
        return $shifts;
    }

    /**
     * Add note
     *
     * @param \AppBundle\Entity\Note $note
     *
     * @return Membership
     */
    public function addNote(\AppBundle\Entity\Note $note)
    {
        $this->notes[] = $note;
        return $this;
    }

    /**
     * Remove note
     *
     * @param \AppBundle\Entity\Note $note
     */
    public function removeNote(\AppBundle\Entity\Note $note)
    {
        $this->notes->removeElement($note);
    }

    /**
     * Get notes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Add givenProxy
     *
     * @param \AppBundle\Entity\Proxy $givenProxy
     *
     * @return Membership
     */
    public function addGivenProxy(\AppBundle\Entity\Proxy $givenProxy)
    {
        $this->given_proxies[] = $givenProxy;
        return $this;
    }

    /**
     * Remove givenProxy
     *
     * @param \AppBundle\Entity\Proxy $givenProxy
     */
    public function removeGivenProxy(\AppBundle\Entity\Proxy $givenProxy)
    {
        $this->given_proxies->removeElement($givenProxy);
    }

    /**
     * Get givenProxies
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getGivenProxies()
    {
        return $this->given_proxies;
    }

    public function getDisplayMemberNumber()
    {
        return '#' . $this->getMemberNumber();
    }

    /**
     * Set firstShiftDate
     *
     * @param \DateTime $firstShiftDate
     *
     * @return Membership
     */
    public function setFirstShiftDate($firstShiftDate)
    {
        $this->firstShiftDate = $firstShiftDate;
        return $this;
    }

    /**
     * Get firstShiftDate
     *
     * @return \DateTime
     */
    public function getFirstShiftDate()
    {
        return $this->firstShiftDate;
    }

    /**
     * Add timeLog
     *
     * @param \AppBundle\Entity\TimeLog $timeLog
     *
     * @return Membership
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
     * Get shiftTimeLogs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getShiftTimeLogs()
    {
        return $this->timeLogs->filter(function (TimeLog $log) {
            return ($log->getType() != TimeLog::TYPE_SAVING);
        });
    }

    /**
     * Get savingTimeLogs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSavingTimeLogs()
    {
        return $this->timeLogs->filter(function (TimeLog $log) {
            return ($log->getType() == TimeLog::TYPE_SAVING);
        });
    }

    public function getShiftTimeCount($before = null)
    {
        $sum = function($carry, TimeLog $log)
        {
            $carry += $log->getTime();
            return $carry;
        };

        $logs = $this->getShiftTimeLogs();
        if ($before) {
            $logs = $this->getShiftTimeLogs()->filter(function (TimeLog $log) use ($before) {
                return ($log->getCreatedAt() < $before);
            });
        }

        return array_reduce($logs->toArray(), $sum, 0);
    }

    public function getSavingTimeCount($before = null)
    {
        $sum = function($carry, TimeLog $log)
        {
            $carry += $log->getTime();
            return $carry;
        };

        $logs = $this->getSavingTimeLogs();
        if ($before) {
            $logs = $logs->filter(function (TimeLog $log) use ($before) {
                return ($log->getCreatedAt() < $before);
            });
        }

        return array_reduce($logs->toArray(), $sum, 0);
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
     * Get membershipShiftExemptions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getMembershipShiftExemptions()
    {
        return $this->membershipShiftExemptions;
    }

    /**
     * Get valid membership shiftExemptions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCurrentMembershipShiftExemptions(\DateTime $date = null)
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        return $this->membershipShiftExemptions->filter(function($membershipShiftExemption) use ($date) {
            return $membershipShiftExemption->isCurrent($date);
        });
    }

    /**
     * Return if the membership is exempted from doing shifts
     *
     * @param \DateTime $date
     * @return boolean
     */
    public function isCurrentlyExemptedFromShifts(\DateTime $date = null)
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        return $this->membershipShiftExemptions->exists(function($key, $membershipShiftExemption) use ($date) {
            return $membershipShiftExemption->isCurrent($date);
        });
    }

}
