<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Registration
 *
 * @ORM\Table(name="registration")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="App\Repository\RegistrationRepository")
 */
class Registration
{
    const TYPE_CASH = 1;
    const TYPE_CHECK = 2;
    const TYPE_LOCAL = 3;
    const TYPE_CREDIT_CARD = 4;
    const TYPE_HELLOASSO = 6;
    const TYPE_DEFAULT = 5;
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date", type="datetime")
     * @Assert\DateTime()
     */
    private $date;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     * @Assert\DateTime()
     */
    private $created_at;

    /**
     * @var string
     *
     * @ORM\Column(name="amount", type="string", length=255)
     * @Assert\NotBlank(message="Un montant est requis")
     */
    private $amount;

    /**
     * @var string
     *
     * @ORM\Column(name="mode", type="integer")
     * @Assert\NotBlank(message="Un mode de paiement est requis")
     */
    private $mode;

    /**
     * @ORM\ManyToOne(targetEntity="Membership", inversedBy="registrations",)
     * @ORM\JoinColumn(name="membership_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $membership;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="recordedRegistrations")
     * @ORM\JoinColumn(name="registrar_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $registrar;

    /**
     * @ORM\OneToOne(targetEntity="HelloassoPayment", mappedBy="registration", cascade={"persist"})
     */
    private $helloassoPayment;

    private $is_new;

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->created_at = new \DateTime();
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

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set date
     *
     * @param \DateTime $date
     *
     * @return Registration
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
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
     * Set amount
     *
     * @param string $amount
     *
     * @return Registration
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
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
     * Set mode
     *
     * @param string $mode
     *
     * @return Registration
     */
    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
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
     * Set registrar
     *
     * @param \App\Entity\User $registrar
     *
     * @return Registration
     */
    public function setRegistrar(\App\Entity\User $registrar = null)
    {
        $this->registrar = $registrar;

        return $this;
    }

    /**
     * Get registrar
     *
     * @return \App\Entity\User
     */
    public function getRegistrar()
    {
        return $this->registrar;
    }

    public function getIsNew(){
        return $this->is_new;
    }

    public function setIsNew($value){
        $this->is_new = $value;

        return $this;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Registration
     */
    public function setCreatedAt($createdAt)
    {
        $this->created_at = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return Registration
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updated_at = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    /**
     * Set helloassoPayment.
     *
     * @param \App\Entity\HelloassoPayment|null $helloassoPayment
     *
     * @return Registration
     */
    public function setHelloassoPayment(\App\Entity\HelloassoPayment $helloassoPayment = null)
    {
        $this->helloassoPayment = $helloassoPayment;

        return $this;
    }

    /**
     * Get helloassoPayment.
     *
     * @return \App\Entity\HelloassoPayment|null
     */
    public function getHelloassoPayment()
    {
        return $this->helloassoPayment;
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
}
