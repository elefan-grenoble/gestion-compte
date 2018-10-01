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
 * Commission
 *
 * @ORM\Table(name="membership")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\MembershipRepository")
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
     * @ORM\Column(type="integer")
     * @Assert\NotBlank()
     * @Assert\NotBlank(message="Merci d'entrer votre numéro d'adhérent.", groups={"Registration"})
     */
    protected $member_number;

    /**
     * @var bool
     *
     * @ORM\Column(name="withdrawn", type="boolean", nullable=true, options={"default" : 0})
     */
    private $withdrawn;

    /**
     * @var bool
     *
     * @ORM\Column(name="frozen", type="boolean", nullable=true, options={"default" : 0})
     */
    private $frozen;

    /**
     * @var bool
     *
     * @ORM\Column(name="frozen_change", type="boolean", nullable=true, options={"default" : 0})
     */
    private $frozen_change;

    /**
     * @ORM\OneToMany(targetEntity="Registration", mappedBy="membership",cascade={"persist", "remove"})
     * @OrderBy({"date" = "DESC"})
     */
    private $registrations;

    /**
     * @ORM\OneToOne(targetEntity="Registration",cascade={"persist"})
     * @ORM\JoinColumn(name="last_registration_id", referencedColumnName="id")
     */
    private $lastRegistration;

    /**
     * @ORM\OneToMany(targetEntity="Registration", mappedBy="registrar",cascade={"persist", "remove"})
     * @OrderBy({"date" = "DESC"})
     */
    private $recordedRegistrations;

    /**
     * @ORM\OneToMany(targetEntity="Beneficiary", mappedBy="membership", cascade={"persist", "remove"})
     */
    private $beneficiaries;

    /**
     * One Membership has One Main Beneficiary.
     * @ORM\OneToOne(targetEntity="Beneficiary",cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="main_beneficiary_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $mainBeneficiary;

    /**
     * @ORM\OneToMany(targetEntity="Note", mappedBy="subject",cascade={"persist", "remove"})
     * @OrderBy({"created_at" = "ASC"})
     */
    private $notes;

    /**
     * @ORM\OneToMany(targetEntity="Proxy", mappedBy="owner",cascade={"persist", "remove"})
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
        parent::__construct();
        $this->registrations = new ArrayCollection();
        $this->beneficiaries = new ArrayCollection();
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

        if (!$this->getLastRegistration() || $registration->getDate() > $this->getLastRegistration()->getDate()){
            $this->setLastRegistration($registration);
        }

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

        if ($this->getLastRegistration() === $registration){
            if ($this->getRegistrations()->count()){
                $this->setLastRegistration($this->getRegistrations()->first());
            }
        }
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
     * Get beneficiaries who can still book
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBeneficiariesWhoCanBook(Shift $shift = null,$current_cycle = 0)
    {
        return $this->beneficiaries->filter(function ($beneficiary) use ($shift,$current_cycle){
            return $this->canBook($beneficiary,$shift,$current_cycle);
        });
    }

    public function getFirstname() {
        $mainBeneficiary = $this->getMainBeneficiary();
        if ($mainBeneficiary)
            return $mainBeneficiary->getFirstname();
        else
            return '';

    }

    public function getLastname() {
        $mainBeneficiary = $this->getMainBeneficiary();
        if ($mainBeneficiary)
            return $mainBeneficiary->getLastname();
        else
            return '';
    }

    public function __toString()
    {
        return '#'.$this->getMemberNumber().' '.$this->getFirstname().' '.$this->getLastname();
    }

    public function getEmail() {
        $this->getMainBeneficiary()->getEmail();
    }

    public function getTmpToken($key = ''){
        return md5($this->getEmail().$this->getLastname().$this->getPassword().$key.date('d'));
    }

    public  function getAnonymousEmail(){
        $email = $this->getEmail();
        $splited = explode("@",$email);
        $return = '';
        foreach ($splited as $part){
            $splited_part = explode(".",$part);
            foreach ($splited_part as $mini_part){
                $first_char = substr($mini_part,0,1);
                $last_char = substr($mini_part,strlen($mini_part)-1,1);
                $center = substr($mini_part,1,strlen($mini_part)-2);
                if (strlen($center)>0)
                    $return .= $first_char.preg_replace('/./','_',$center).$last_char;
                elseif(strlen($mini_part)>1)
                    $return .= $first_char.$last_char;
                else
                    $return .= $first_char;
                $return .= '.';
            }
            $return = substr($return,0,strlen($return)-1);
            $return .= '@';
        }
        $return = substr($return,0,strlen($return)-1);
        return preg_replace('/_{3}_*/','___',$return);
    }

    public  function getAnonymousLastname(){
        $lastname = $this->getLastname();
        $splited = explode(" ",$lastname);
        $return = '';
        foreach ($splited as $part){
            $splited_part = explode("-",$part);
            foreach ($splited_part as $mini_part){
                $first_char = substr($mini_part,0,1);
                $last_char = substr($mini_part,strlen($mini_part)-1,1);
                $center = substr($mini_part,1,strlen($mini_part)-2);
                if (strlen($center)>0)
                    $return .= $first_char.preg_replace('/./','*',$center).$last_char;
                else
                    $return .= $first_char.$last_char;
                $return .= '-';
            }
            $return = substr($return,0,strlen($return)-1);
            $return .= ' ';
        }
        $return = substr($return,0,strlen($return)-1);
        return $return;
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
        if ($mainBeneficiary)
            $this->addBeneficiary($mainBeneficiary);

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

        if ($withdrawn)
            $this->setEnabled(false);

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
     * Add recordedRegistration
     *
     * @param \AppBundle\Entity\Registration $recordedRegistration
     *
     * @return Membership
     */
    public function addRecordedRegistration(\AppBundle\Entity\Registration $recordedRegistration)
    {
        $this->recordedRegistrations[] = $recordedRegistration;

        return $this;
    }

    /**
     * Remove recordedRegistration
     *
     * @param \AppBundle\Entity\Registration $recordedRegistration
     */
    public function removeRecordedRegistration(\AppBundle\Entity\Registration $recordedRegistration)
    {
        $this->recordedRegistrations->removeElement($recordedRegistration);
    }

    /**
     * Get recordedRegistrations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRecordedRegistrations()
    {
        return $this->recordedRegistrations;
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
     * Set lastRegistration
     *
     * @param \AppBundle\Entity\Registration $lastRegistration
     *
     * @return Membership
     */
    public function setLastRegistration(\AppBundle\Entity\Registration $lastRegistration = null)
    {
        $this->lastRegistration = $lastRegistration;
        return $this;
    }
    /**
     * Get lastRegistration
     *
     * @return \AppBundle\Entity\Registration
     */
    public function getLastRegistration()
    {
        return $this->lastRegistration;
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
     * Can book a shift
     *
     * @param \AppBundle\Entity\Beneficiary $beneficiary
     * @param \AppBundle\Entity\Shift $shift
     * @param $current_cycle index of cycle
     *
     * @return Boolean
     */
    //todo put this in shift voter ones there is no more beneficiary but only users and membership
    public function canBook(Beneficiary $beneficiary = null, Shift $shift = null,$current_cycle = 'undefined')
    {
        if ($shift && $current_cycle == 'undefined'){ //cycle index can be computed using shift
            for ($cycle = 0; $cycle < 3; $cycle++){
                if ($shift->getStart() > $this->endOfCycle($cycle-1)){
                    if ($shift->getStart() < $this->endOfCycle($cycle)){
                        $current_cycle = $cycle;
                        break;
                    }
                }
            }
        }else if( $current_cycle == 'undefined'){
            $current_cycle = 0; //default
        }
        if ($current_cycle > 1){ //do not book more than on cycle away
            return false;
        }
        if ($this->getFrozen()){
            if (!$current_cycle) //cannot book when frozen
                return false;
            if ($current_cycle > 0 && !$this->getFrozenChange()) //cannot book for next cycle if frozen
                return false;
        }

        if (!$beneficiary && !$shift) // in general, not for a specific beneficiary and shift
            return $this->getTimeCount() < $this->shiftTimeByCycle()*($current_cycle+1); //Can book ?
        else {
            $beneficiary_counter = 0;
            if ($beneficiary){
                if ($beneficiary->getUser()->getId() != $this->getId()) { // beneficiary and user must match
                    return false;
                }
                //compute beneficiary booked time
                $beneficiary_shift = $this->getShiftsOfCycle($current_cycle,true)->filter(function ($shift) use ($beneficiary) {
                    return ($shift->getShifter() == $beneficiary);
                });
                foreach ($beneficiary_shift as $s){
                    $beneficiary_counter += $s->getDuration();
                }
                //check if beneficiary booked time is ok
                //if timecount <=0 : some shift to catchup, can book more than what's due
                if ($this->getTimeCount($this->endOfCycle($current_cycle)) > 0 && $beneficiary_counter >= $this->getDueDurationByCycle()){ //Beneficiary is already ok
                    return false;
                }
            }
            if (!$shift){ // Ok because beneficiary defined and still have time left to book
                return true;
            }
            //$shift and $beneficiary are defined bellow
            if ($shift->getIsPast()){ // Do not book old
                return false;
            }
            if ($shift->getShifter() && !$shift->getIsDismissed()) { // Do not book already booked
                return false;
            }
            if ($shift->getRole() && !$beneficiary->getRoles()->contains($shift->getRole())) { // Do not book shift i do not know how to handle (role)
                return false;
            }
            if ($shift->getLastShifter() && $beneficiary->getMembership()->getId() != $shift->getLastShifter()->getMembership()->getId()) { // Do not book pre-booked shift
                return false;
            }
            //time count at start of cycle (before decrease)
            $timecount = $this->getTimeCount($this->startOfCycle($current_cycle));
            //time count at start of cycle  (after decrease)
            if ($timecount > $this->getDueDurationByCycle()){
                $timecount = 0;
            }else{
                $timecount -= $this->getDueDurationByCycle();
            }
            // duration of shift + what beneficiary already booked for cycle + timecount (may be < 0) minus due should be <= what's can we book for this cycle
            return ($shift->getDuration() + $beneficiary_counter + $timecount <= ($current_cycle+1)*$this->getDueDurationByCycle()) ;
        }
    }

    /**
     * Max time count for a membership
     *
     * @return Integer
     */
    // TODO Valeur à mettre dans une conf
    public function getDueDurationByCycle()
    {
        return 60 * 3;
    }

    /**
     * Get total shift time for a cycle
     */
    // TODO Valeur à mettre dans une conf
    public function shiftTimeByCycle()
    {
        $nbOfBeneficiaries = count($this->getBeneficiaries());
        return 60 * 3 * $nbOfBeneficiaries;
    }

    public function isUptodate()
    {
        return ($this->getRemainder()->format("%R%a") >= 0);
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
     * Check if registration is possible
     *
     * @param \DateTime $date
     * @return boolean
     */
    public function canRegister(\DateTime $date = null)
    {
        $remainder = $this->getRemainder($date);
        if ( ! $remainder->invert ){ //still some days
            $min_delay_to_anticipate =  \DateInterval::createFromDateString('15 days');
            $now = new \DateTimeImmutable();
            $away = $now->add($min_delay_to_anticipate);
            $now = new \DateTimeImmutable();
            $expire = $now->add($remainder);
            return ($expire < $away);
        }
        else {
            return true;
        }
    }

    /**
     * get remainder
     *
     * @return \DateInterval|false
     */
    public function getRemainder(\DateTime $date = null)
    {
        if (!$date){
            $date = new \DateTime('now');
        }
        if (!$this->getLastRegistration()){
            $expire = new \DateTime('-1 day');
            return date_diff($date,$expire);
        }
        $expire = clone $this->getLastRegistration()->getDate();
        $expire = $expire->add(\DateInterval::createFromDateString('1 year'));
        return date_diff($date,$expire);
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

    public function getAutocompleteLabel(){
        if ($this->getMainBeneficiary())
            return '#'.$this->getMemberNumber().' '.$this->getFirstname().' '.$this->getLastname();
        else
            return '#'.$this->getMemberNumber().' '.$this->getUsername();
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
