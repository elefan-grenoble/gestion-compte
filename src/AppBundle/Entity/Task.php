<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Task
 *
 * @ORM\Table(name="task")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TaskRepository")
 */
class Task
{
    const PRIORITY_URGENT_VALUE = 5;
    const PRIORITY_URGENT_COLOR = "red white-text";
    const PRIORITY_IMPORTANT_VALUE = 4;
    const PRIORITY_IMPORTANT_COLOR = "orange white-text";
    const PRIORITY_NORMAL_VALUE = 3;
    const PRIORITY_NORMAL_COLOR = "brown white-text";
    const PRIORITY_ANNEXE_VALUE = 2;
    const PRIORITY_ANNEXE_COLOR = "gray black-text";

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
     * @ORM\Column(name="title", type="string", length=255)
     */
    private $title;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="due_date", type="date", nullable=true)
     */
    private $dueDate;

    /**
     * @var bool
     *
     * @ORM\Column(name="closed", type="boolean", nullable=true, options={"default" : 0})
     */
    private $closed;

    /**
     * Many Tasks have Many Commissions.
     * @ORM\ManyToMany(targetEntity="Commission", inversedBy="tasks")
     * @ORM\JoinTable(name="tasks_commissions")
     */
    private $commissions;

    /**
     * Many Tasks have Many Owners (beneficiaries).
     * @ORM\ManyToMany(targetEntity="Beneficiary", inversedBy="tasks", cascade={"persist"})
     * @ORM\JoinTable(name="tasks_beneficiaries")
     */
    private $owners;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="registrar_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $registrar;

    /**
     * @var int
     *
     * @ORM\Column(name="priority", type="smallint")
     */
    private $priority;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255, nullable=true)
     */
    private $status;

    /**
     * @var \DateTime
     *
     * Used as start date...
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->commissions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->owners = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set title
     *
     * @param string $title
     *
     * @return Task
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Add commission
     *
     * @param \AppBundle\Entity\Commission $commission
     *
     * @return Task
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
     * Add owner
     *
     * @param \AppBundle\Entity\Beneficiary $owner
     *
     * @return Task
     */
    public function addOwner(\AppBundle\Entity\Beneficiary $owner)
    {
        $this->owners[] = $owner;
        $owner->addTask($this);

        return $this;
    }

    /**
     * Remove owner
     *
     * @param \AppBundle\Entity\Beneficiary $owner
     */
    public function removeOwner(\AppBundle\Entity\Beneficiary $owner)
    {
        $this->owners->removeElement($owner);
    }

    /**
     * Get owners
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getOwners()
    {
        return $this->owners;
    }

    /**
     * Set registrar
     *
     * @param \AppBundle\Entity\User $registrar
     *
     * @return Task
     */
    public function setRegistrar(\AppBundle\Entity\User $registrar = null)
    {
        $this->registrar = $registrar;

        return $this;
    }

    /**
     * Get registrar
     *
     * @return \AppBundle\Entity\User
     */
    public function getRegistrar()
    {
        return $this->registrar;
    }

    /**
     * Set dueDate
     *
     * @param \DateTime $dueDate
     *
     * @return Task
     */
    public function setDueDate($dueDate)
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    /**
     * Get dueDate
     *
     * @return \DateTime
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }

    /**
     * Set closed
     *
     * @param boolean $closed
     *
     * @return Task
     */
    public function setClosed($closed)
    {
        $this->closed = $closed;

        return $this;
    }

    /**
     * Get closed
     *
     * @return boolean
     */
    public function getClosed()
    {
        return $this->closed;
    }

    /**
     * Set priority
     *
     * @param integer $priority
     *
     * @return Task
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Get priority
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Set status
     *
     * @param string $status
     *
     * @return Task
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $date
     *
     * @return Task
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
}
