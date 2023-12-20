<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\Group;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Formation
 *
 * @ORM\Table(name="formation")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="App\Repository\FormationRepository")
 * @UniqueEntity(fields={"name"}, message="Ce nom est déjà utilisé par une autre formation")
 */
class Formation extends Group
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    // private $name;  // from Group
    // private $roles;  // from Group

    /**
     * @var string
     * 
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @var string
     * 
     * @ORM\Column(name="url", type="string", length=255, nullable=true)
     */
    private $url;

    /**
     * Many Formations have Many Beneficiaries.
     * @ORM\ManyToMany(targetEntity="Beneficiary", mappedBy="formations")
     */
    private $beneficiaries;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="created_by_id", referencedColumnName="id")
     */
    private $createdBy;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(null);
        $this->beneficiaries = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
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
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get roles
     *
     * @return array
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * Get description
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description ? $this->description : '';
    }

    /**
     * Set description
     * 
     * @param string $description
     * @return Formation
     */
    public function setDescription(string $description): Formation
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set url
     *
     * @param string $url
     *
     * @return Formation
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Add beneficiary
     *
     * @param \App\Entity\Beneficiary $beneficiary
     *
     * @return Formation
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
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set createdBy
     *
     * @param \App\Entity\User $createBy
     *
     * @return Formation
     */
    public function setCreatedBy(\App\Entity\User $user = null)
    {
        $this->createdBy = $user;

        return $this;
    }

    /**
     * Get createdBy
     *
     * @return \App\Entity\User
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }
}
