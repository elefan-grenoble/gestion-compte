<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;


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
     * @ORM\Column(name="phone", type="string", length=255)
     */
    private $phone;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="beneficiaries")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="Commission", inversedBy="owners")
     * @ORM\JoinColumn(name="commission_id", referencedColumnName="id")
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
        return $this->lastname;
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
        return '#'.$this->getUser()->getMemberNumber().' '.$this->getFirstname().' '.$this->getLastname();
    }

    /**
     * Get firstname
     *
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
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
        return $this === $this->getUser()->getMainBeneficiary();
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->commissions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->roles = new \Doctrine\Common\Collections\ArrayCollection();
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
}
