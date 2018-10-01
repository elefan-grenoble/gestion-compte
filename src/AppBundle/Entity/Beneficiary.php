<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;


/**
 * Beneficiary
 *
 * @ORM\Table(name="beneficiary")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\BeneficiaryRepository")
 */
class Beneficiary
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
     * @var string
     *
     * @ORM\Column(name="lastname", type="string", length=255)
     */
    private $lastname;

    /**
     * @var string
     *
     * @ORM\Column(name="firstname", type="string", length=255)
     */
    private $firstname;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, unique=true)
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(name="phone", type="string", length=255, nullable=true)
     */
    private $phone;

    /**
     * One Beneficiary has One Address.
     * @ORM\OneToOne(targetEntity="Address",cascade={"persist"})
     * @ORM\JoinColumn(name="address_id", referencedColumnName="id")
     */
    private $address;

    /**
     * @ORM\OneToOne(targetEntity="User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id",onDelete="CASCADE")
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="Membership", inversedBy="beneficiaries")
     * @ORM\JoinColumn(name="membership_id", referencedColumnName="id",onDelete="CASCADE")
     */
    private $membership;

    /**
     * @ORM\OneToMany(targetEntity="Shift", mappedBy="shifter",cascade={"remove"})
     */
    private $shifts;

    /**
     * @ORM\OneToMany(targetEntity="Shift", mappedBy="booker",cascade={"remove"})
     */
    private $booked_shifts;

    /**
     * @ORM\OneToMany(targetEntity="Shift", mappedBy="lastShifter",cascade={"remove"})
     */
    private $reservedShifts;

    /**
     * @ORM\OneToMany(targetEntity="SwipeCard", mappedBy="beneficiary",cascade={"remove"})
     * @OrderBy({"number" = "DESC"})
     */
    private $swipe_cards;

    /**
     * @ORM\ManyToOne(targetEntity="Commission", inversedBy="owners")
     * @ORM\JoinColumn(name="commission_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $own;

    /**
     * Many Beneficiary have Many Commissions.
     * @ORM\ManyToMany(targetEntity="Commission", inversedBy="beneficiaries")
     * @ORM\JoinTable(name="beneficiaries_commissions")
     */
    private $commissions;

    /**
     * Many Beneficiary have Many Tasks.
     * @ORM\ManyToMany(targetEntity="Task", inversedBy="owners")
     */
    private $tasks;

    /**
     * Many Beneficiary have Many Roles.
     * @ORM\ManyToMany(targetEntity="Role", inversedBy="beneficiaries")
     * @ORM\JoinTable(name="beneficiaries_roles")
     */
    private $roles;

    /**
     * @ORM\OneToMany(targetEntity="Proxy", mappedBy="giver",cascade={"persist", "remove"})
     */
    private $received_proxies;

    private $_counters = [];

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
     * Set lastname
     *
     * @param string $lastname
     *
     * @return Beneficiary
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get lastname
     *
     * @return string
     */
    public function getLastname()
    {
        return strtoupper($this->lastname);
    }

    /**
     * Set firstname
     *
     * @param string $firstname
     *
     * @return Beneficiary
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getDisplayName(){
        if (!$this->getUser()){
            return $this->getFirstname().' '.$this->getLastname();
        }
        return '#'.$this->getUser()->getMemberNumber().' '.$this->getFirstname().' '.$this->getLastname();
    }

    public function getPublicDisplayName(){
        return '#'.$this->getUser()->getMemberNumber().' '.$this->getFirstname().' '.$this->getLastname()[0];
    }

    public function __toString() {
        return $this->getDisplayName();
    }

    /**
     * Get firstname
     *
     * @return string
     */
    public function getFirstname()
    {
        return ucfirst(strtolower($this->firstname));
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return Beneficiary
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set phone
     *
     * @param string $phone
     *
     * @return Beneficiary
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set user
     *
     * @param \AppBundle\Entity\User $user
     *
     * @return Beneficiary
     */
    public function setUser(\AppBundle\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \AppBundle\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get hasViewUserDataRights
     *
     * @return boolean
     */
    public function canViewUserData()
    {
        foreach ($this->getRoles() as $role){
            if ($role->hasViewUserDataRights())
                return true;
        }
        return false;
    }

    /**
     * Get hasViewUserDataRights
     *
     * @return boolean
     */
    public function canEditUserData()
    {
        foreach ($this->getRoles() as $role){
            if ($role->hasEditUserDataRights())
                return true;
        }
        return false;
    }


    public function isMain()
    {
        return $this === $this->getMembership()->getMainBeneficiary();
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->commissions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->roles = new \Doctrine\Common\Collections\ArrayCollection();
        $this->shifts = new \Doctrine\Common\Collections\ArrayCollection();
        $this->booked_shifts = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add commission
     *
     * @param \AppBundle\Entity\Commission $commission
     *
     * @return Beneficiary
     */
    public function addCommission(\AppBundle\Entity\Commission $commission)
    {
        $this->commissions[] = $commission;

        return $this;
    }

    /**
     * Remove commission
     *
     * @param \AppBundle\Entity\Commission $commission
     */
    public function removeCommission(\AppBundle\Entity\Commission $commission)
    {
        $this->commissions->removeElement($commission);
    }

    /**
     * Get commissions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCommissions()
    {
        return $this->commissions;
    }

    /**
     * Add role
     *
     * @param \AppBundle\Entity\Role $role
     *
     * @return Beneficiary
     */
    public function addRole(\AppBundle\Entity\Role $role)
    {
        $this->roles[] = $role;

        return $this;
    }

    /**
     * Remove role
     *
     * @param \AppBundle\Entity\Role $role
     */
    public function removeRole(\AppBundle\Entity\Role $role)
    {
        $this->roles->removeElement($role);
    }

    /**
     * Get roles
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * Set own
     *
     * @param \AppBundle\Entity\Commission $own
     *
     * @return Beneficiary
     */
    public function setOwn(\AppBundle\Entity\Commission $own = null)
    {
        $this->own = $own;

        return $this;
    }

    /**
     * Get own
     *
     * @return \AppBundle\Entity\Commission
     */
    public function getOwn()
    {
        return $this->own;
    }

    /**
     * Add shift
     *
     * @param \AppBundle\Entity\Shift $shift
     *
     * @return Beneficiary
     */
    public function addShift(\AppBundle\Entity\Shift $shift)
    {
        $this->shifts[] = $shift;

        return $this;
    }

    /**
     * Remove shift
     *
     * @param \AppBundle\Entity\Shift $shift
     */
    public function removeShift(\AppBundle\Entity\Shift $shift)
    {
        $this->shifts->removeElement($shift);
    }

    /**
     * Get shifts
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getShifts()
    {
        return $this->shifts;
    }

    /**
     * Add bookedShift
     *
     * @param \AppBundle\Entity\Shift $bookedShift
     *
     * @return Beneficiary
     */
    public function addBookedShift(\AppBundle\Entity\Shift $bookedShift)
    {
        $this->booked_shifts[] = $bookedShift;

        return $this;
    }

    /**
     * Remove bookedShift
     *
     * @param \AppBundle\Entity\Shift $bookedShift
     */
    public function removeBookedShift(\AppBundle\Entity\Shift $bookedShift)
    {
        $this->booked_shifts->removeElement($bookedShift);
    }

    /**
     * Get bookedShifts
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBookedShifts()
    {
        return $this->booked_shifts;
    }

    /**
     * Add task
     *
     * @param \AppBundle\Entity\Task $task
     *
     * @return Beneficiary
     */
    public function addTask(\AppBundle\Entity\Task $task)
    {
        $this->tasks[] = $task;

        return $this;
    }

    /**
     * Remove task
     *
     * @param \AppBundle\Entity\Task $task
     */
    public function removeTask(\AppBundle\Entity\Task $task)
    {
        $this->tasks->removeElement($task);
    }

    /**
     * Get tasks
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTasks()
    {
        return $this->tasks;
    }

    /**
     * Add givenProxy
     *
     * @param \AppBundle\Entity\Proxy $givenProxy
     *
     * @return Beneficiary
     */
    public function addGivenProxy(\AppBundle\Entity\Proxy $givenProxy)
    {
        $this->given_proxys[] = $givenProxy;

        return $this;
    }

    /**
     * Remove givenProxy
     *
     * @param \AppBundle\Entity\Proxy $givenProxy
     */
    public function removeGivenProxy(\AppBundle\Entity\Proxy $givenProxy)
    {
        $this->given_proxys->removeElement($givenProxy);
    }

    /**
     * Get givenProxys
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getGivenProxys()
    {
        return $this->given_proxys;
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

    /**
     * Add receivedProxy
     *
     * @param \AppBundle\Entity\Proxy $receivedProxy
     *
     * @return Beneficiary
     */
    public function addReceivedProxy(\AppBundle\Entity\Proxy $receivedProxy)
    {
        $this->received_proxies[] = $receivedProxy;

        return $this;
    }

    /**
     * Remove receivedProxy
     *
     * @param \AppBundle\Entity\Proxy $receivedProxy
     */
    public function removeReceivedProxy(\AppBundle\Entity\Proxy $receivedProxy)
    {
        $this->received_proxies->removeElement($receivedProxy);
    }

    /**
     * Get receivedProxies
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getReceivedProxies()
    {
        return $this->received_proxies;
    }

    public function getAutocompleteLabel(){
        return '#'.$this->getMembership()->getMemberNumber().' '.$this->getFirstname().' '.$this->getLastname().' ('. $this->getId() .')';
    }

    public function getAutocompleteLabelFull(){
        return '#'.$this->getMembership()->getMemberNumber().' '.$this->getFirstname().' '.$this->getLastname().' '.$this->getEmail().' ('. $this->getId() .')';
    }

    /**
     * Add reservedShift
     *
     * @param \AppBundle\Entity\Shift $reservedShift
     *
     * @return Beneficiary
     */
    public function addReservedShift(\AppBundle\Entity\Shift $reservedShift)
    {
        $this->reservedShifts[] = $reservedShift;

        return $this;
    }

    /**
     * Remove reservedShift
     *
     * @param \AppBundle\Entity\Shift $reservedShift
     */
    public function removeReservedShift(\AppBundle\Entity\Shift $reservedShift)
    {
        $this->reservedShifts->removeElement($reservedShift);
    }

    /**
     * Get reservedShifts
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getReservedShifts()
    {
        return $this->reservedShifts;
    }

    /**
     * Add swipeCard.
     *
     * @param \AppBundle\Entity\SwipeCard $swipeCard
     *
     * @return Beneficiary
     */
    public function addSwipeCard(\AppBundle\Entity\SwipeCard $swipeCard)
    {
        $this->swipe_cards[] = $swipeCard;

        return $this;
    }

    /**
     * Remove swipeCard.
     *
     * @param \AppBundle\Entity\SwipeCard $swipeCard
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeSwipeCard(\AppBundle\Entity\SwipeCard $swipeCard)
    {
        return $this->swipe_cards->removeElement($swipeCard);
    }

    /**
     * Get swipeCards.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSwipeCards()
    {
        return $this->swipe_cards;
    }

    public function getEnabledSwipeCards()
    {
        return $this->swipe_cards->filter(function ($card) {
            return $card->getEnable();
        });
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
     * @return mixed
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param mixed $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
    }

    public function canBook($duration = 90,$cycle = 0){

        $member = $this->getMembership();
        $beneficiary_counter = $this->getTimeCount($cycle);

        //check if beneficiary booked time is ok
        //if timecount <=0 : some shift to catchup, can book more than what's due
        if ($member->getTimeCount($member->endOfCycle($cycle)) > 0 && $beneficiary_counter >= $this->_getDueDurationByCycle()){ //Beneficiary is already ok
            return false;
        }

        //time count at start of cycle (before decrease)
        $timeCounter = $member->getTimeCount($member->startOfCycle($cycle));
        //time count at start of cycle  (after decrease)
        if ($timeCounter > $this->_getDueDurationByCycle()){
            $timeCounter = 0;
        }else{
            $timeCounter -= $this->_getDueDurationByCycle();
        }
        // duration of shift + what beneficiary already booked for cycle + timecount (may be < 0) minus due should be <= what can membership book for this cycle
        return ($duration + $beneficiary_counter + $timeCounter <= ($cycle + 1) * $this->_getDueDurationByCycle());

    }

    public function getTimeCount($cycle = 0){
        if (!isset($this->_counters[$cycle])){
            $this->_counters[$cycle] = 0;
            $member = $this->getMembership();
            //todo add a custom query for this
            $beneficiary_shift_for_current_cycle = $this->getShifts()->filter(function (Shift $shift) use ($member,$cycle) {
                return ($shift->getStart() > $member->startOfCycle($cycle) && $shift->getEnd() < $member->endOfCycle($cycle));
            });
            foreach ($beneficiary_shift_for_current_cycle as $s){
                $this->_counters[$cycle] += $s->getDuration();
            }
        }
        return $this->_counters[$cycle];
    }

    private function _getDueDurationByCycle(){
        return 180; //todo return form parameters $this->container->getParameter('due_duration_by_cycle')
    }
}
