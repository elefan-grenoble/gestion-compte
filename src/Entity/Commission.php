<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;

/**
 * Commission
 *
 * @ORM\Table(name="commission")
 * @ORM\Entity(repositoryClass="App\Repository\CommissionRepository")
 */
class Commission
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
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text")
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255)
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(name="next_meeting_desc", type="string", length=255, nullable=true)
     */
    private $next_meeting_desc;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="next_meeting_date", type="datetime", nullable=true)
     */
    private $next_meeting_date = null;

    /**
     * Many Commissions have Many Beneficiary.
     * @ORM\ManyToMany(targetEntity="Beneficiary", mappedBy="commissions")
     */
    private $beneficiaries;

    /**
     * Many Commissions have Many Tasks.
     * @ORM\ManyToMany(targetEntity="Task", mappedBy="commissions")
     * @OrderBy({"closed" = "ASC","dueDate" = "ASC"})
     */
    private $tasks;

    /**
     * One Commission have Many Owners (Beneficiary).
     * @ORM\OneToMany(targetEntity="Beneficiary", mappedBy="own",cascade={"persist"})
     */
    private $owners;

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
     * Set name
     *
     * @param string $name
     *
     * @return Commission
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->beneficiaries = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add beneficiary
     *
     * @param \App\Entity\Beneficiary $beneficiary
     *
     * @return Commission
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

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Commission
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Add owner
     *
     * @param \App\Entity\Beneficiary $owner
     *
     * @return Commission
     */
    public function addOwner(\App\Entity\Beneficiary $owner)
    {
        $this->owners[] = $owner;

        return $this;
    }

    /**
     * Remove owner
     *
     * @param \App\Entity\Beneficiary $owner
     */
    public function removeOwner(\App\Entity\Beneficiary $owner)
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
     * Set email
     *
     * @param string $email
     *
     * @return Commission
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
     * Add task
     *
     * @param \App\Entity\Task $task
     *
     * @return Commission
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
     * Set nextMeetingDesc.
     *
     * @param string $nextMeetingDesc
     *
     * @return Commission
     */
    public function setNextMeetingDesc($nextMeetingDesc)
    {
        $this->next_meeting_desc = $nextMeetingDesc;

        return $this;
    }

    /**
     * Get nextMeetingDesc.
     *
     * @return string
     */
    public function getNextMeetingDesc()
    {
        return $this->next_meeting_desc;
    }

    /**
     * Set nextMeetingDate.
     *
     * @param \DateTime $nextMeetingDate
     *
     * @return Commission
     */
    public function setNextMeetingDate($nextMeetingDate)
    {
        $this->next_meeting_date = $nextMeetingDate;

        return $this;
    }

    /**
     * Get nextMeetingDate.
     *
     * @return \DateTime
     */
    public function getNextMeetingDate()
    {
        return $this->next_meeting_date;
    }
}
