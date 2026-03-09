<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\Common\Collections\Collection;

/**
 * Membership.
 *
 * @ORM\Table(name="membership", uniqueConstraints={@ORM\UniqueConstraint(columns={"member_number"})})
 *
 * @ORM\HasLifecycleCallbacks()
 *
 * @ORM\Entity(repositoryClass="App\Repository\MembershipRepository")
 *
 * @UniqueEntity(fields={"member_number"}, message="Ce numéro de membre existe déjà")
 */
class Membership
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer")
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @Assert\NotBlank(message="Merci d'entrer votre numéro d'adhérent")
     *
     * @ORM\Column(type="bigint")
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
     *
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
     * @var bool
     *
     * @ORM\Column(name="flying", type="boolean", options={"default" : 0}, nullable=false)
     */
    private $flying;

    /**
     * @ORM\OneToMany(targetEntity="Registration", mappedBy="membership",cascade={"persist", "remove"})
     *
     * @OrderBy({"date" = "DESC"})
     */
    private $registrations;

    /**
     * @ORM\OneToMany(targetEntity="Beneficiary", mappedBy="membership", cascade={"persist", "remove"})
     */
    private $beneficiaries;

    /**
     * @var Beneficiary
     *
     * One Membership has One Main Beneficiary
     *
     * @Assert\Valid
     *
     * @ORM\OneToOne(targetEntity="Beneficiary",cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(name="main_beneficiary_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $mainBeneficiary;

    /**
     * @ORM\OneToMany(targetEntity="Note", mappedBy="subject",cascade={"persist", "remove"})
     *
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
     *
     * @OrderBy({"createdAt" = "DESC", "type" = "DESC"})
     */
    private $timeLogs;

    /**
     * @ORM\OneToMany(targetEntity="MembershipShiftExemption", mappedBy="membership", cascade={"persist", "remove"})
     *
     * @OrderBy({"createdAt" = "DESC"})
     */
    private $membershipShiftExemptions;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * Membership constructor.
     */
    public function __construct()
    {
        $this->registrations = new ArrayCollection();
        $this->beneficiaries = new ArrayCollection();
        $this->timeLogs = new ArrayCollection();
        $this->membershipShiftExemptions = new ArrayCollection();
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
        if (!$this->createdAt) {
            $this->createdAt = new \DateTime();
        }
    }

    public function getTmpToken($key = '')
    {
        return md5($this->getId() . $this->getMemberNumber() . $key . date('d'));
    }

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
     * Set memberNumber.
     *
     * @param int $memberNumber
     *
     * @return Membership
     */
    public function setMemberNumber($memberNumber)
    {
        $this->member_number = $memberNumber;

        return $this;
    }

    /**
     * Get memberNumber.
     *
     * @return int
     */
    public function getMemberNumber()
    {
        return $this->member_number;
    }

    /**
     * Add registration.
     *
     * @return Membership
     */
    public function addRegistration(Registration $registration)
    {
        $this->registrations[] = $registration;

        return $this;
    }

    /**
     * Remove registration.
     */
    public function removeRegistration(Registration $registration)
    {
        $this->registrations->removeElement($registration);
    }

    /**
     * Get registrations.
     *
     * @return Collection
     */
    public function getRegistrations()
    {
        return $this->registrations;
    }

    /**
     * Add beneficiary.
     *
     * @return Membership
     */
    public function addBeneficiary(Beneficiary $beneficiary)
    {
        $this->beneficiaries[] = $beneficiary;

        return $this;
    }

    /**
     * Remove beneficiary.
     */
    public function removeBeneficiary(Beneficiary $beneficiary)
    {
        $this->beneficiaries->removeElement($beneficiary);
    }

    /**
     * Get beneficiaries.
     *
     * @return Collection
     */
    public function getBeneficiaries()
    {
        return $this->beneficiaries;
    }

    /**
     * Get beneficiaries (with main in first position)
     * Why? because beneficiaries are ordered by id ASC.
     *
     * @return Collection
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
     * Get member_number & list of beneficiaries.
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
            $memberNumberWithBeneficiaryListString .= ' ' . $beneficiary->getDisplayName();
        }

        return $memberNumberWithBeneficiaryListString;
    }

    /**
     * Set mainBeneficiary.
     *
     * @return Membership
     */
    public function setMainBeneficiary(?Beneficiary $mainBeneficiary = null)
    {
        if ($mainBeneficiary) {
            $this->addBeneficiary($mainBeneficiary);
            $mainBeneficiary->setMembership($this);
        }

        $this->mainBeneficiary = $mainBeneficiary;

        return $this;
    }

    /**
     * Get mainBeneficiary.
     *
     * @return Beneficiary
     */
    public function getMainBeneficiary()
    {
        if (!$this->mainBeneficiary) {
            if ($this->getBeneficiaries()->count()) {
                $this->setMainBeneficiary($this->getBeneficiaries()->first());
            }
        }

        return $this->mainBeneficiary;
    }

    /**
     * Set withdrawn.
     *
     * @param bool $withdrawn
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
     * Get isWithdrawn.
     *
     * @return bool
     */
    public function isWithdrawn()
    {
        return $this->withdrawn;
    }

    /**
     * Get withdrawn.
     *
     * @return bool
     */
    public function getWithdrawn()
    {
        return $this->withdrawn;
    }

    /**
     * Set withdrawnDate.
     *
     * @param \DateTime $date
     *
     * @return Membership
     */
    public function setWithdrawnDate($date)
    {
        $this->withdrawnDate = $date;

        return $this;
    }

    /**
     * Get withdrawnDate.
     *
     * @return \DateTime
     */
    public function getWithdrawnDate()
    {
        return $this->withdrawnDate;
    }

    /**
     * Set withdrawnBy.
     *
     * @return TimeLog
     */
    public function setWithdrawnBy(?User $user = null)
    {
        $this->withdrawnBy = $user;

        return $this;
    }

    /**
     * Get withdrawnBy.
     *
     * @return User
     */
    public function getWithdrawnBy()
    {
        return $this->withdrawnBy;
    }

    public function getCommissions()
    {
        $commissions = [];
        foreach ($this->getBeneficiaries() as $beneficiary) {
            $commissions = array_merge($beneficiary->getCommissions()->toArray(), $commissions);
        }

        return new ArrayCollection($commissions);
    }

    public function getOwnedCommissions()
    {
        return $this->getCommissions()->filter(function ($commission) {
            $r = false;
            foreach ($commission->getOwners() as $owner) {
                if ($this->getBeneficiaries()->contains($owner)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Set frozen.
     *
     * @param bool $frozen
     *
     * @return Membership
     */
    public function setFrozen($frozen)
    {
        $this->frozen = $frozen;

        return $this;
    }

    /**
     * Get frozen.
     *
     * @deprecated illogic isFlying, isWithdrawn but getFrozen
     *
     * @return bool
     */
    public function getFrozen()
    {
        return $this->frozen;
    }

    /**
     * return if the member is frozen.
     *
     * @return bool
     */
    public function isFrozen()
    {
        return $this->frozen;
    }

    /**
     * Set frozen_change.
     *
     * @param bool $frozen_change
     *
     * @return Membership
     */
    public function setFrozenChange($frozen_change)
    {
        $this->frozen_change = $frozen_change;

        return $this;
    }

    /**
     * Get frozen_change.
     *
     * @return bool
     */
    public function getFrozenChange()
    {
        return $this->frozen_change;
    }

    public function isFlying(): ?bool
    {
        return $this->flying;
    }

    public function setFlying(?bool $flying): void
    {
        $this->flying = $flying;
    }

    /**
     * Get lastRegistration.
     *
     * @return Registration
     */
    public function getLastRegistration()
    {
        return $this->getRegistrations()->first();
    }

    /**
     * Return if the member has a valid registration before the given date.
     *
     * @param \DateTime $date
     *
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
     * Get all reserved shifts for all beneficiaries.
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
     * Add note.
     *
     * @return Membership
     */
    public function addNote(Note $note)
    {
        $this->notes[] = $note;

        return $this;
    }

    /**
     * Remove note.
     */
    public function removeNote(Note $note)
    {
        $this->notes->removeElement($note);
    }

    /**
     * Get notes.
     *
     * @return Collection
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Add givenProxy.
     *
     * @return Membership
     */
    public function addGivenProxy(Proxy $givenProxy)
    {
        $this->given_proxies[] = $givenProxy;

        return $this;
    }

    /**
     * Remove givenProxy.
     */
    public function removeGivenProxy(Proxy $givenProxy)
    {
        $this->given_proxies->removeElement($givenProxy);
    }

    /**
     * Get givenProxies.
     *
     * @return Collection
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
     * Set firstShiftDate.
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
     * Get firstShiftDate.
     *
     * @return \DateTime
     */
    public function getFirstShiftDate()
    {
        return $this->firstShiftDate;
    }

    /**
     * Add timeLog.
     *
     * @return Membership
     */
    public function addTimeLog(TimeLog $timeLog)
    {
        $this->timeLogs[] = $timeLog;

        return $this;
    }

    /**
     * Remove timeLog.
     */
    public function removeTimeLog(TimeLog $timeLog)
    {
        $this->timeLogs->removeElement($timeLog);
    }

    /**
     * Get timeLogs.
     *
     * @return Collection
     */
    public function getTimeLogs()
    {
        return $this->timeLogs;
    }

    /**
     * Get shiftTimeLogs.
     *
     * @return Collection
     */
    public function getShiftTimeLogs()
    {
        return $this->timeLogs->filter(function (TimeLog $log) {
            return $log->getType() != TimeLog::TYPE_SAVING;
        });
    }

    /**
     * Get savingTimeLogs.
     *
     * @return Collection
     */
    public function getSavingTimeLogs()
    {
        return $this->timeLogs->filter(function (TimeLog $log) {
            return $log->getType() == TimeLog::TYPE_SAVING;
        });
    }

    public function getShiftTimeCount($before = null)
    {
        $sum = function ($carry, TimeLog $log) {
            $carry += $log->getTime();

            return $carry;
        };

        $logs = $this->getShiftTimeLogs();
        if ($before) {
            $logs = $this->getShiftTimeLogs()->filter(function (TimeLog $log) use ($before) {
                return $log->getCreatedAt() < $before;
            });
        }

        return array_reduce($logs->toArray(), $sum, 0);
    }

    public function getSavingTimeCount($before = null)
    {
        $sum = function ($carry, TimeLog $log) {
            $carry += $log->getTime();

            return $carry;
        };

        $logs = $this->getSavingTimeLogs();
        if ($before) {
            $logs = $logs->filter(function (TimeLog $log) use ($before) {
                return $log->getCreatedAt() < $before;
            });
        }

        return array_reduce($logs->toArray(), $sum, 0);
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
     * Get membershipShiftExemptions.
     *
     * @return Collection
     */
    public function getMembershipShiftExemptions()
    {
        return $this->membershipShiftExemptions;
    }

    /**
     * Get valid membership shiftExemptions.
     *
     * @return Collection
     */
    public function getCurrentMembershipShiftExemptions(?\DateTime $date = null)
    {
        if (!$date) {
            $date = new \DateTime('now');
        }

        return $this->membershipShiftExemptions->filter(function ($membershipShiftExemption) use ($date) {
            return $membershipShiftExemption->isCurrent($date);
        });
    }

    /**
     * Return if the membership is exempted from doing shifts.
     *
     * @return bool
     */
    public function isCurrentlyExemptedFromShifts(?\DateTime $date = null)
    {
        if (!$date) {
            $date = new \DateTime('now');
        }

        return $this->membershipShiftExemptions->exists(function ($key, $membershipShiftExemption) use ($date) {
            return $membershipShiftExemption->isCurrent($date);
        });
    }
}
