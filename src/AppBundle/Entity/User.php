<?php
// src/AppBundle/Entity/User.php

namespace AppBundle\Entity;

use DateTime;
use AppBundle\Repository\RegistrationRepository;
use Doctrine\ORM\EntityRepository;
use FOS\OAuthServerBundle\Model\ClientInterface;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\OrderBy;

/**
 * @ORM\Entity
 * @ORM\Table(name="fos_user")
 * @UniqueEntity("member_number")
 */
class User extends BaseUser
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
     * @ORM\OneToMany(targetEntity="Registration", mappedBy="user",cascade={"persist", "remove"})
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
     * @ORM\OneToMany(targetEntity="Beneficiary", mappedBy="user",cascade={"persist", "remove"})
     */
    private $beneficiaries;

    /**
     * One User has One Main Beneficiary.
     * @ORM\OneToOne(targetEntity="Beneficiary",cascade={"persist"})
     * @ORM\JoinColumn(name="main_beneficiary_id", referencedColumnName="id")
     */
    private $mainBeneficiary;

    /**
     * One User has One Address.
     * @ORM\OneToOne(targetEntity="Address",cascade={"persist"})
     * @ORM\JoinColumn(name="address_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $address;

    /**
     * Many Users have Many clients.
     * @ORM\ManyToMany(targetEntity="Client", inversedBy="users")
     * @ORM\JoinTable(name="users_clients")
     */
    private $clients;


    public function __construct()
    {
        parent::__construct();
        $this->registrations = new ArrayCollection();
        $this->beneficiaries = new ArrayCollection();
    }

    /**
     * Set memberNumber
     *
     * @param integer $memberNumber
     *
     * @return User
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
     * @return User
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
     * @return User
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
     * Set address
     *
     * @param \AppBundle\Entity\Address $address
     *
     * @return User
     */
    public function setAddress(\AppBundle\Entity\Address $address = null)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get address
     *
     * @return \AppBundle\Entity\Address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Add service
     *
     * @param \AppBundle\Entity\Service $service
     *
     * @return User
     */
    public function addService(\AppBundle\Entity\Service $service)
    {
        $this->services[] = $service;

        return $this;
    }

    /**
     * Remove service
     *
     * @param \AppBundle\Entity\Service $service
     */
    public function removeService(\AppBundle\Entity\Service $service)
    {
        $this->services->removeElement($service);
    }

    /**
     * Get services
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getServices()
    {
        return $this->services;
    }

    public function setLastRegistration(){
        return null;
    }

    /**
     * Get lastRegistration
     *
     * @return \AppBundle\Entity\Registration
     */
    public function getLastRegistration(){
        if ($this->getRegistrations()->count()){
            return ($this->getRegistrations()->first());
        }
        return null;
    }

    public function getFirstname() {
        $mainBeneficiary = $this->getMainBeneficiary();
        if ($mainBeneficiary)
            return $mainBeneficiary->getFirstname();
        else
            return $this->getUsername();

    }

    public function getLastname() {
        return $this->getMainBeneficiary()->getLastname();
    }

    public function __toString()
    {
        return $this->getUsername();
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

    static function makeUsername($firstname,$lastname,$extra = ''){
        $lastname = preg_replace('/[-\/]+/', ' ', $lastname);
        $ln = explode(' ',$lastname);
//        if (in_array(strtolower($ln[0]),array('la','du','de'))&&count($ln>1))
        if (strlen($ln[0])<3&&count($ln)>1)
            $ln = $ln[0].$ln[1];
        else
            $ln = $ln[0];
        $username = strtolower(substr(explode(' ',$firstname)[0],0,1).$ln);
        $username = preg_replace('/[^a-z]/', '', $username);
        $username .= $extra;
        return $username;
    }

    static function randomPassword() {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }

    /**
     * Set mainBeneficiary
     *
     * @param \AppBundle\Entity\Beneficiary $mainBeneficiary
     *
     * @return User
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
     * @return User
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


    /**
     * Add recordedRegistration
     *
     * @param \AppBundle\Entity\Registration $recordedRegistration
     *
     * @return User
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

    public function isRegistrar($ip){
        if ($this->hasRole("ROLE_ADMIN") || $this->hasRole("ROLE_SUPER_ADMIN")){
            return true;
        }
        elseif (isset($ip) and in_array($ip,array('127.0.0.1','78.209.62.101','193.33.56.47'))){ //todo put this in conf
            return true;
        //}elseif ($this->getMainBeneficiary()->isAmbassador()){ //todo check also other Beneficiary ?
        //    return true;
        }
        return false;
    }

    public function isTaskEditor(){
        if ($this->hasRole("ROLE_ADMIN") || $this->hasRole("ROLE_SUPER_ADMIN")){
            return true;
        }
        elseif ($this->getCommissions()){ //todo put this in conf
            return true;
        }
        return false;
    }

    /**
     * Set frozen
     *
     * @param boolean $frozen
     *
     * @return User
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
     * determine whether the given client (ClientInterface) is allowed by the user, or not.
     * @param ClientInterface $client
     * @return bool
     */
    public function isAuthorizedClient(Client $client)
    {
        return $this->getClients()->contains($client);
    }

    /**
     * Add client
     *
     * @param \AppBundle\Entity\Client $client
     *
     * @return User
     */
    public function addClient(\AppBundle\Entity\Client $client)
    {
        $this->clients[] = $client;

        return $this;
    }

    /**
     * Remove client
     *
     * @param \AppBundle\Entity\Client $client
     */
    public function removeClient(\AppBundle\Entity\Client $client)
    {
        $this->clients->removeElement($client);
    }

    /**
     * Get clients
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getClients()
    {
        return $this->clients;
    }

    /**
     * Get total shift duration for current cycle
     */
    public function getCycleShiftsDuration()
    {
        $duration = 0;
        foreach ($this->getCycleShifts() as $shift) {
            $duration += $shift->getShift()->getDuration();
        }
        return $duration;
    }

    /**
     * Get all shifts for all beneficiaries
     */
    public function getAllShifts()
    {
        $shifts = new ArrayCollection();
        foreach ($this->getBeneficiaries() as $beneficiary) {
            foreach ($beneficiary->getShifts() as $shift) {
                $shifts->add($shift);
            }
        }
        return $shifts;
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
     * Get shifts in the current cycle
     */
    public function getCycleShifts()
    {
        return $this->getAllShifts()->filter(function($shift) {
            return $shift->getShift()->getStart() > $this->startOfCycle() &&
                $shift->getShift()->getEnd() < $this->endOfCycle();
        });
    }

    /**
     * Get all shifts in the future
     * TODO Fonction utilisée ???
     */
    public function getFutureShifts()
    {
        return $this->getAllShifts()->filter(function($shift) {
            return $shift->getShift()->getStart() > new DateTime('now');
        });
    }

    public function needToBookAShift()
    {
	    return $this->remainingToBook() > 0;
    }

    /**
     * Get start date of current cycle
     */
    public function startOfCycle()
    {
        $first = $this->getFirstShift();
        $modFirst = null;
        if ($first) {  	  
            $now = new DateTime('now');
            $diff = $first->getShift()->getStart()->diff($now);           
            $modFirst = $diff->format('%a') % 28;
        }
        $startCurrCycle = null;
        if ($modFirst) {
            /* Exception if first cycle in the future */          
            if ($first->getShift()->getStart() < $now) {
                $startCurrCycle = clone($now);           
                $startCurrCycle->modify("-".$modFirst." days");
            }
            else {
            	 $startCurrCycle = clone($first->getShift()->getStart());
            }
            /* Reset time, keep only date */
            $startCurrCycle->setTime(0, 0, 0);
        }
        return $startCurrCycle;
    }
    
    /**
     * Get end date of current cycle
     */
    public function endOfCycle()
    {
        $endCurrCycle = null;
        $startCurrCycle = $this->startOfCycle();
        if ($startCurrCycle) {
            $endCurrCycle = clone($startCurrCycle);
            $endCurrCycle->modify("+28 days");
        }
        return $endCurrCycle;
    }
    
    /**
     * Get start date of next cycle
     */
    public function startOfNextCycle()
    {
        $startNextCycle = null;
        $endCurrCycle = $this->endOfCycle();
        if ($endCurrCycle) {
            $startNextCycle = clone($endCurrCycle);     
            $startNextCycle->modify("+1 day");
        }
        return $startNextCycle;
    }
    
    /**
     * Get end date of next cycle
     */
    public function endOfNextCycle()
    {
    	  $endNextCycle = null;
    	  $startNextCycle = $this->startOfNextCycle();
        if ($startNextCycle) {
            $endNextCycle = clone($startNextCycle);
            $endNextCycle->modify("+28 days");
        }
        return $endNextCycle;
    }

    /**
     * Get total shift time for a cycle
     */
    // TODO Valeur à mettre dans une conf
    public function shiftTimeByCycle()
    {
        return 60 * 3;
    }

    /**
     * Get remaining time to book
     */
    public function remainingToBook() {
        return $this->shiftTimeByCycle() - $this->getCycleShiftsDuration();
    }

    /**
     * Get first shift ever
     */
    public function getFirstShift()
    {
        $first = null;
        foreach ($this->getAllBookedShifts() as $shift) {
            if (!$first || $shift->getShift()->getStart() < $first->getShift()->getStart()) {
                $first = $shift;
            }
        };
        return $first;
    }
}
