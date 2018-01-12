<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Member
 *
 * @ORM\Table(name="member")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\MemberRepository")
 */
class Member
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
     * @var int
     *
     * @ORM\Column(name="number", type="integer", unique=true)
     */
    private $number;

    /**
     * @var bool
     *
     * @ORM\Column(name="withdrawn", type="boolean", nullable=true)
     */
    private $withdrawn;

    /**
     * @var bool
     *
     * @ORM\Column(name="frozen", type="boolean", nullable=true)
     */
    private $frozen;

    /**
     * @ORM\OneToMany(targetEntity="Beneficiary", mappedBy="member",cascade={"persist", "remove"})
     */
    private $beneficiaries;


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
     * Set number
     *
     * @param integer $number
     *
     * @return Member
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Get number
     *
     * @return int
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set withdrawn
     *
     * @param boolean $withdrawn
     *
     * @return Member
     */
    public function setWithdrawn($withdrawn)
    {
        $this->withdrawn = $withdrawn;

        return $this;
    }

    /**
     * Get withdrawn
     *
     * @return bool
     */
    public function getWithdrawn()
    {
        return $this->withdrawn;
    }

    /**
     * Set frozen
     *
     * @param boolean $frozen
     *
     * @return Member
     */
    public function setFrozen($frozen)
    {
        $this->frozen = $frozen;

        return $this;
    }

    /**
     * Get frozen
     *
     * @return bool
     */
    public function getFrozen()
    {
        return $this->frozen;
    }
}

