<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AbstractRegistration
 *
 * @ORM\Table(name="view_abstract_registration")
 * @ORM\Entity(readOnly=true)
 */
class AbstractRegistration
{

    const TYPE_ANONYMOUS = 2;
    const TYPE_MEMBER = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="id", type="string")
     * @ORM\Id
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="integer")
     */
    private $type;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date", type="datetime")
     */
    private $date;

    /**
     * @var string
     *
     * @ORM\Column(name="amount", type="string", length=255)
     */
    private $amount;

    /**
     * @var string
     *
     * @ORM\Column(name="mode", type="integer")
     */
    private $mode;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="recordedRegistrations")
     * @ORM\JoinColumn(name="registrar_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $registrar;

    /**
     * @var string
     *
     * @ORM\Column(name="beneficiary", type="string", length=255)
     */
    private $beneficiary;

    /**
     * @ORM\ManyToOne(targetEntity="Membership")
     * @ORM\JoinColumn(name="membership_id", referencedColumnName="id")
     */
    private $membership;


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
     * Get date
     *
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Get amount
     *
     * @return string
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Get mode
     *
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Get type
     *
     * @return integer
     */
    public function getType()
    {
        return $this->type;
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
     * Get beneficiary
     *
     * @return string
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }

    /**
     * @return Membership
     */
    public function getMembership()
    {
        return $this->membership;
    }

    public function toRegistration()
    {
        $registration = new Registration();
        $registration->setRegistrar($this->registrar);
        $registration->setMode($this->mode);
        $registration->setAmount($this->amount);
        $registration->setDate($this->date);
        return $registration;
    }

}
