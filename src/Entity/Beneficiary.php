<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Beneficiary
 *
 * @ORM\Table(name="beneficiary")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="App\Repository\BeneficiaryRepository")
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
     * @Assert\NotBlank(message="Le nom du bénéficiaire est requis")
     */
    private $lastname;

    /**
     * @var string
     *
     * @ORM\Column(name="firstname", type="string", length=255)
     * @Assert\NotBlank(message="Le prénom du bénéficiaire est requis")
     */
    private $firstname;

    /**
     * @var string
     *
     * @ORM\Column(name="phone", type="string", length=255, nullable=true)
     */
    private $phone;

    /**
     * @var Address
     * One Beneficiary has One Address.
     * @ORM\OneToOne(targetEntity="Address", inversedBy="beneficiary", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="address_id", referencedColumnName="id")
     * @Assert\NotNull
     * @Assert\Valid
     */
    private $address;

    /**
     * @var bool
     *
     * @ORM\Column(name="flying", type="boolean", options={"default" : 0}, nullable=false)
     */
    private $flying;

    /**
     * @ORM\OneToOne(targetEntity="User", inversedBy="beneficiary", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id",nullable=false)
     * @Assert\NotNull
     * @Assert\Valid
     */
    private $user;

    /**
     * @var Membership
     * @ORM\ManyToOne(targetEntity="Membership", inversedBy="beneficiaries", cascade={"persist"})
     * @ORM\JoinColumn(name="membership_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $membership;

    /**
     * @ORM\Column(name="openid", type="string", length=255)
     * @var string $openid
     */
    protected $openid;

    /**
     * @ORM\Column(name="openid_member_number", type="string", length=255)
     * @var string $openid_member_number
     */
    protected $openid_member_number;

    /**
     * @ORM\OneToMany(targetEntity="Shift", mappedBy="shifter", cascade={"remove"})
     * @OrderBy({"start" = "DESC"})
     */
    private $shifts;

    /**
     * @ORM\OneToMany(targetEntity="Shift", mappedBy="lastShifter", cascade={"remove"})
     */
    private $reservedShifts;

    /**
     * @ORM\OneToMany(targetEntity="PeriodPosition", mappedBy="shifter", cascade={"persist"})
     */
    private $periodPositions;

    /**
     * @ORM\OneToMany(targetEntity="SwipeCard", mappedBy="beneficiary", cascade={"remove"})
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
     * @ORM\ManyToMany(targetEntity="Task", mappedBy="owners")
     */
    private $tasks;

    /**
     * Many Beneficiary have Many Formations.
     * @ORM\ManyToMany(targetEntity="Formation", inversedBy="beneficiaries")
     * @ORM\JoinTable(name="beneficiaries_formations")
     */
    private $formations;

    /**
     * @ORM\OneToMany(targetEntity="Proxy", mappedBy="owner", cascade={"persist", "remove"})
     */
    private $received_proxies;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->commissions = new ArrayCollection();
        $this->formations = new ArrayCollection();
        $this->shifts = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getDisplayNameWithMemberNumber();
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
     * Get membernumber
     *
     * @return int
     */
    public function getMemberNumber()
    {
        $membership = $this->getMembership();
        if (!$membership)
            return null;
        return $membership->getMemberNumber();
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

    public function getDisplayName(): string
    {
        return $this->getFirstname() . ' ' . $this->getLastname();
    }

    /**
     * /!\ DO NOT MODIFY /!\
     *
     * Such a method is also used for autocomplete. If you want to
     * change it, you HAVE to adapt the methods used in data
     * transformer: BeneficiaryToStringTransformer. Otherwise,
     * autocomplete will be broken.
     */
    public function getDisplayNameWithMemberNumber(): string
    {
        return '#' . $this->getMemberNumber() . ' ' . $this->getDisplayName();
    }

    public function getPublicDisplayName(): string
    {
        return $this->getFirstname() . ' ' . $this->getLastname()[0];
    }

    public function getPublicDisplayNameWithMemberNumber(): string
    {
        return '#' . $this->getMemberNumber() . ' ' . $this->getPublicDisplayName();
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
        $this->getUser()->setEmail($email);

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        if ($this->getUser()) {
            return $this->getUser()->getEmail();
        } else {
            return null;
        }
    }

    /**
     * @return string
     */
    public function getOpenId() {
        return $this->openid;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setOpenId(string $id) : Beneficiary
    {
        $this->openid = $id;
        return $this;
    }
    /**
     * @return string
     */
    public function getOpenIdMemberNumber() : ?string
    {
        return $this->openid_member_number;
    }

    /**
     * @param string $number
     * @return $this
     */
    public function setOpenIdMemberNumber(string $number) : Beneficiary
    {
        $this->openid_member_number = $number;
        return $this;
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
     * @param \App\Entity\User $user
     *
     * @return Beneficiary
     */
    public function setUser(\App\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \App\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    public function isMain()
    {
        return $this === $this->getMembership()->getMainBeneficiary();
    }

    /**
     * Add commission
     *
     * @param \App\Entity\Commission $commission
     *
     * @return Beneficiary
     */
    public function addCommission(\App\Entity\Commission $commission)
    {
        $this->commissions[] = $commission;

        return $this;
    }

    /**
     * Remove commission
     *
     * @param \App\Entity\Commission $commission
     */
    public function removeCommission(\App\Entity\Commission $commission)
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

    public function getOwnedCommissions()
    {
        return $this->commissions->filter(function ($commission) {
            return $commission->getOwners()->contains($this);
        });
    }

    /**
     * Add formation
     *
     * @param \App\Entity\Formation $formation
     *
     * @return Beneficiary
     */
    public function addFormation(\App\Entity\Formation $formation)
    {
        $this->formations[] = $formation;

        return $this;
    }

    /**
     * Remove formation
     *
     * @param \App\Entity\Formation $formation
     */
    public function removeFormation(\App\Entity\Formation $formation)
    {
        $this->formations->removeElement($formation);
    }

    /**
     * Get formations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFormations()
    {
        return $this->formations;
    }

    /**
     * Set own
     *
     * @param \App\Entity\Commission $own
     *
     * @return Beneficiary
     */
    public function setOwn(\App\Entity\Commission $own = null)
    {
        $this->own = $own;

        return $this;
    }

    /**
     * Get own
     *
     * @return \App\Entity\Commission
     */
    public function getOwn()
    {
        return $this->own;
    }

    /**
     * Add shift
     *
     * @param \App\Entity\Shift $shift
     *
     * @return Beneficiary
     */
    public function addShift(\App\Entity\Shift $shift)
    {
        $this->shifts[] = $shift;

        return $this;
    }

    /**
     * Remove shift
     *
     * @param \App\Entity\Shift $shift
     */
    public function removeShift(\App\Entity\Shift $shift)
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
     * Add task
     *
     * @param \App\Entity\Task $task
     *
     * @return Beneficiary
     */
    public function addTask(\App\Entity\Task $task)
    {
        $this->tasks[] = $task;

        return $this;
    }

    /**
     * Remove task
     *
     * @param \App\Entity\Task $task
     */
    public function removeTask(\App\Entity\Task $task)
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
     * Add receivedProxy
     *
     * @param \App\Entity\Proxy $receivedProxy
     *
     * @return Beneficiary
     */
    public function addReceivedProxy(\App\Entity\Proxy $receivedProxy)
    {
        $this->received_proxies[] = $receivedProxy;

        return $this;
    }

    /**
     * Remove receivedProxy
     *
     * @param \App\Entity\Proxy $receivedProxy
     */
    public function removeReceivedProxy(\App\Entity\Proxy $receivedProxy)
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

    /**
     * Add reservedShift
     *
     * @param \App\Entity\Shift $reservedShift
     *
     * @return Beneficiary
     */
    public function addReservedShift(\App\Entity\Shift $reservedShift)
    {
        $this->reservedShifts[] = $reservedShift;

        return $this;
    }

    /**
     * Remove reservedShift
     *
     * @param \App\Entity\Shift $reservedShift
     */
    public function removeReservedShift(\App\Entity\Shift $reservedShift)
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
     * Get periodPositions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPeriodPositions()
    {
        return $this->periodPositions;
    }

    /**
     * Add swipeCard
     *
     * @param \App\Entity\SwipeCard $swipeCard
     *
     * @return Beneficiary
     */
    public function addSwipeCard(\App\Entity\SwipeCard $swipeCard)
    {
        $this->swipe_cards[] = $swipeCard;

        return $this;
    }

    /**
     * Remove swipeCard
     *
     * @param \App\Entity\SwipeCard $swipeCard
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeSwipeCard(\App\Entity\SwipeCard $swipeCard)
    {
        return $this->swipe_cards->removeElement($swipeCard);
    }

    /**
     * Get swipeCards
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSwipeCards()
    {
        return $this->swipe_cards;
    }

    /**
     * Get enabled swipeCards
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getEnabledSwipeCards()
    {
        return $this->swipe_cards->filter(function ($card) {
            return $card->getEnable();
        });
    }

    /**
     * @return Membership
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
     * @return Address
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

    /**
     * @return bool
     */
    public function isFlying(): ?bool {
        return $this->flying;
    }

    /**
     * @param bool $flying
     */
    public function setFlying(?bool $flying): void {
        $this->flying = $flying;
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
     * Simple method to detect new beneficiaires.
     * TODO: move to Membership? Look at registration data instead?
     * 
     * @return bool
     */
    public function isNew()
    {
        $shiftCountThreshold = 3;

        return $this->shifts->count() <= $shiftCountThreshold;
    }
}