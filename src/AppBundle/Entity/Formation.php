<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\Group;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Formation
 *
 * @ORM\Table(name="formation")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\FormationRepository")
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
        $this->createdAt = new \DateTime();
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
     * Get roles
     *
     * @return array
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * Get created_at
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Add beneficiary
     *
     * @param \AppBundle\Entity\Beneficiary $beneficiary
     *
     * @return Formation
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

}
