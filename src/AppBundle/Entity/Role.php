<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Role
 *
 * @ORM\Table(name="role")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\RoleRepository")
 */
class Role
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
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    private $name;

    /**
     * Many Roles have Many Beneficiaries.
     * @ORM\ManyToMany(targetEntity="Beneficiary", mappedBy="roles")
     */
    private $beneficiaries;

    /**
     * @var bool
     *
     * @ORM\Column(name="has_view_user_data_rights", type="boolean", unique=false, options={"default" : 0})
     */
    private $has_view_user_data_rights;

    /**
     * @var bool
     *
     * @ORM\Column(name="has_edit_user_data_rights", type="boolean", unique=false, options={"default" : 0})
     */
    private $has_edit_user_data_rights;

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
     * @return Role
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
     * @param \AppBundle\Entity\Beneficiary $beneficiary
     *
     * @return Role
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

    public function hasViewUserDataRights()
    {
        return $this->has_view_user_data_rights;
    }

    public function hasEditUserDataRights()
    {
        return $this->has_edit_user_data_rights;
    }

    /**
     * Set hasViewUserDataRights
     *
     * @param boolean $hasViewUserDataRights
     *
     * @return Role
     */
    public function setHasViewUserDataRights($hasViewUserDataRights)
    {
        $this->has_view_user_data_rights = $hasViewUserDataRights;

        return $this;
    }

    /**
     * Get hasViewUserDataRights
     *
     * @return boolean
     */
    public function getHasViewUserDataRights()
    {
        return $this->has_view_user_data_rights;
    }

    /**
     * Set hasEditUserDataRights
     *
     * @param boolean $hasEditUserDataRights
     *
     * @return Role
     */
    public function setHasEditUserDataRights($hasEditUserDataRights)
    {
        $this->has_edit_user_data_rights = $hasEditUserDataRights;

        return $this;
    }

    /**
     * Get hasEditUserDataRights
     *
     * @return boolean
     */
    public function getHasEditUserDataRights()
    {
        return $this->has_edit_user_data_rights;
    }
}
