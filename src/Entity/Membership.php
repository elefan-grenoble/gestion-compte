<?php

namespace App\Entity;

use DateTime;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityRepository;
use FOS\OAuthServerBundle\Model\ClientInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\OrderBy;

/**
 * Commission
 *
 * @ORM\Table(name="membership")
 * @ORM\Entity(repositoryClass="App\Repository\MembershipRepository")
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
     * @var bool
     *
     * @ORM\Column(name="withdrawn", type="boolean", nullable=false, options={"default" : 0})
     */
    private $withdrawn;

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
     * @OrderBy({"created_at" = "ASC"})
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

    // array of date
    private $_startOfCycle;
    private $_endOfCycle;

    /**
     * @ORM\OneToMany(targetEntity="TimeLog", mappedBy="membership",cascade={"persist", "remove"})
     * @OrderBy({"date" = "DESC"})
     */
    private $timeLogs;

    /**
     * Membership constructor.
     */
    public function __construct()
    {
        $this->registrations = new ArrayCollection();
        $this->beneficiaries = new ArrayCollection();
        $this->timeLogs = new ArrayCollection();
    }

    public function getTmpToken($key = ''){
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
     * @param \App\Entity\Registration $registration
     *
     * @return Membership
     */
    public function addRegistration(\App\Entity\Registration $registration)
    {
        $this->registrations[] = $registration;
        return $this;
    }

    /**
     * Remove registration
     *
     * @param \App\Entity\Registration $registration
     */
    public function removeRegistration(\App\Entity\Registration $registration)
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
     * @param \App\Entity\Beneficiary $beneficiary
     *
     * @return Membership
     */
    public function addBeneficiary(\App\Entity\Beneficiary $beneficiary)
    {
        $this->beneficiaries[] = $beneficiary;
        return $this;
    }

    /**
     * Remove beneficiary
     *
     * @param \App\Entity\Beneficiary $beneficiary
     */
    public function removeBeneficiary(\App\Entity\Beneficiary $beneficiary)
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

    public function __toString()
    {
        return '#'.$this->getMemberNumber();
    }

    /**
     * Set mainBeneficiary
     *
     * @param \App\Entity\Beneficiary $mainBeneficiary
     *
     * @return Membership
     */
    public function setMainBeneficiary(\App\Entity\Beneficiary $mainBeneficiary = null)
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
     * @return \App\Entity\Beneficiary
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

    public function getCommissions(){
        $commissions = array();
        foreach ($this->getBeneficiaries() as $beneficiary){
            $commissions = array_merge($beneficiary->getCommissions()->toArray(),$commissions);
        }
        return new ArrayCollection($commissions);
    }

    public function getOwnedCommissions(){
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
     *
     * @return boolean
     */
    public function getFrozen()
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
     * @return \App\Entity\Registration
     */
    public function getLastRegistration()
    {
        return $this->getRegistrations()->first();
    }

    /**
     * Get total shift duration for current cycle
     */
    public function getCycleShiftsDuration($cycleOffset = 0, $excludeDismissed = false)
    {
        $duration = 0;
        foreach ($this->getShiftsOfCycle($cycleOffset, $excludeDismissed) as $shift) {
            $duration += $shift->getDuration();
        }
        return $duration;
    }

    /**
     * Get all shifts for all beneficiaries
     */
    public function getAllShifts($excludeDismissed = false)
    {
        $shifts = new ArrayCollection();
        foreach ($this->getBeneficiaries() as $beneficiary) {
            foreach ($beneficiary->getShifts() as $shift) {
                $shifts->add($shift);
            }
        }
        if ($excludeDismissed) {
            return $shifts->filter(function($shift) {
                return !$shift->getIsDismissed();
            });
        } else {
            return $shifts;
        }
    }

    /**
     * Get all booked shifts for all beneficiaries
     */
    public function getAllBookedShifts()
    {
        $shifts = new ArrayCollection();
        foreach ($this->getBeneficiaries() as $beneficiary) {
            foreach ($beneficiary->getBookedShifts() as $shift) {
                $shifts->add($shift);
            }
        }
        return $shifts;
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
     * Get shifts of a specific cycle
     * @param $cycleOffset int to chose a cycle (0 for current cycle, 1 for next, -1 for previous)
     * @param bool $excludeDismissed
     * @return ArrayCollection|\Doctrine\Common\Collections\Collection
     */
    public function getShiftsOfCycle($cycleOffset = 0, $excludeDismissed = false)
    {
        return $this->getAllShifts($excludeDismissed)->filter(function($shift) use ($cycleOffset) {
            return $shift->getStart() > $this->startOfCycle($cycleOffset) &&
                $shift->getEnd() < $this->endOfCycle($cycleOffset);
        });
    }

    /**
     * Get start date of current cycle
     * IMPORTANT : time are reset, only date are kept
     * @param int $cycleIndex
     * @return DateTime|null
     */
    public function startOfCycle($cycleOffset = 0)
    {
        if (!isset($this->_startOfCycle) || !isset($this->_startOfCycle[$cycleOffset])) {
            if (!isset($this->_startOfCycle) || !isset($this->_startOfCycle[0])){
                if (!isset($this->_startOfCycle)) {
                    $this->_startOfCycle = array();
                }
                $firstDate = $this->getFirstShiftDate();
                $modFirst = null;
                $now = new DateTime('now');
                $now->setTime(0, 0, 0);
                if ($firstDate) {
                    $diff = $firstDate->diff($now);
                    $currentCycleCount = intval($diff->format('%a') / 28);
                }else{
                    $firstDate = new DateTime('now');
                    $currentCycleCount = 0;
                }
                $this->_startOfCycle[0] = clone($firstDate);
                if ($firstDate < $now) {
                    $this->_startOfCycle[0]->modify("+" . (28 * $currentCycleCount) . " days");
                }
            }
            if ($cycleOffset != 0 ){
                $this->_startOfCycle[$cycleOffset] = clone($this->_startOfCycle[0]);
                $this->_startOfCycle[$cycleOffset]->modify((($cycleOffset>0)?"+":"").(28*$cycleOffset)." days");
            }
        }

        return $this->_startOfCycle[$cycleOffset];
    }

    /**
     * Get end date of current cycle
     * @param int $cycleIndex
     * @return DateTime|null
     */
    public function endOfCycle($cycleOffset = 0)
    {
        if (!isset($this->_endOfCycle) || !isset($this->_endOfCycle[$cycleOffset])) {
            if (!isset($this->_endOfCycle) || !isset($this->_endOfCycle[0])) {
                if (!isset($this->_endOfCycle)) {
                    $this->_endOfCycle = array();
                }
                $this->_endOfCycle[0] = clone($this->startOfCycle());
                $this->_endOfCycle[0]->modify("+27 days");
                $this->_endOfCycle[0]->setTime(23, 59, 59);
            }

            if ($cycleOffset != 0 ){
                $this->_endOfCycle[$cycleOffset] = clone($this->_endOfCycle[0]);
                $this->_endOfCycle[$cycleOffset]->modify("+".(28*$cycleOffset)."days");
            }
        }

        return $this->_endOfCycle[$cycleOffset];
    }

    /**
     * Get all rebooked shifts in the future
     */
    public function getFutureRebookedShifts()
    {
        return $this->getAllBookedShifts()->filter(function($shift) {
            return $shift->getStart() > new DateTime('now') &&
                $shift->getBooker() != $shift->getShifter();
        });
    }

    /**
     * Add note
     *
     * @param \App\Entity\Note $note
     *
     * @return Membership
     */
    public function addNote(\App\Entity\Note $note)
    {
        $this->notes[] = $note;
        return $this;
    }

    /**
     * Remove note
     *
     * @param \App\Entity\Note $note
     */
    public function removeNote(\App\Entity\Note $note)
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
     * @param \App\Entity\Proxy $givenProxy
     *
     * @return Membership
     */
    public function addGivenProxy(\App\Entity\Proxy $givenProxy)
    {
        $this->given_proxies[] = $givenProxy;
        return $this;
    }

    /**
     * Remove givenProxy
     *
     * @param \App\Entity\Proxy $givenProxy
     */
    public function removeGivenProxy(\App\Entity\Proxy $givenProxy)
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

    public function getAutocompleteLabel(){
        return '#'.$this->getMemberNumber();
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
     * @param \App\Entity\TimeLog $timeLog
     *
     * @return Membership
     */
    public function addTimeLog(\App\Entity\TimeLog $timeLog)
    {
        $this->timeLogs[] = $timeLog;
        return $this;
    }

    /**
     * Remove timeLog
     *
     * @param \App\Entity\TimeLog $timeLog
     */
    public function removeTimeLog(\App\Entity\TimeLog $timeLog)
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

    public function getTimeCount($before = null)
    {
        $sum = function($carry, TimeLog $log)
        {
            $carry += $log->getTime();
            return $carry;
        };
        if ($before)
            $logs = $this->getTimeLogs()->filter(function ($log) use ($before){
                return ($log->getDate() < $before);
            });
        else
            $logs = $this->getTimeLogs();
        return array_reduce($logs->toArray(), $sum, 0);
    }
}
