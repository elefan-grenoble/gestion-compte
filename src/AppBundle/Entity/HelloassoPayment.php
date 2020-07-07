<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * HelloassoNotification
 *
 * @ORM\Table(name="helloasso_payment")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\HelloassoPaymentRepository")
 */
class HelloassoPayment
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
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $created_at;

    /**
     * @var int
     *
     * @ORM\Column(name="payment_id", type="integer", unique=true)
     */
    private $paymentId;

    /**
     * @var int
     *
     * @ORM\Column(name="campaign_id", type="integer", nullable=true)
     */
    private $campaignId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date", type="datetime")
     */
    private $date;

    /**
     * @var float
     *
     * @ORM\Column(name="amount", type="float")
     */
    private $amount;


    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string")
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(name="payer_first_name", type="string")
     */
    private $payer_first_name;

    /**
     * @var string
     *
     * @ORM\Column(name="payer_last_name", type="string")
     */
    private $payer_last_name;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string")
     */
    private $status;

    /**
     * @ORM\OneToOne(targetEntity="Registration", inversedBy="helloassoPayment")
     * @ORM\JoinColumn(name="registration_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $registration;


    /***
     * @return string
     */
    public function __toString(){
        return '#'.$this->getId().' de '.$this->getEmail().' le '. $this->getCreatedAt()->format('d-M-Y à H:i').' '.$this->getAmount().' €';
    }
    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->created_at = new \DateTime();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return HelloassoPayment
     */
    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Set date.
     *
     * @param \DateTime $date
     *
     * @return HelloassoPayment
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date.
     *
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set amount.
     *
     * @param float $amount
     *
     * @return HelloassoPayment
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get amount.
     *
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set payerFirstName.
     *
     * @param string $payerFirstName
     *
     * @return HelloassoPayment
     */
    public function setPayerFirstName($payerFirstName)
    {
        $this->payer_first_name = $payerFirstName;

        return $this;
    }

    /**
     * Get payerFirstName.
     *
     * @return string
     */
    public function getPayerFirstName()
    {
        return $this->payer_first_name;
    }

    /**
     * Set payerLastName.
     *
     * @param string $payerLastName
     *
     * @return HelloassoPayment
     */
    public function setPayerLastName($payerLastName)
    {
        $this->payer_last_name = $payerLastName;

        return $this;
    }

    /**
     * Get payerLastName.
     *
     * @return string
     */
    public function getPayerLastName()
    {
        return $this->payer_last_name;
    }

    /**
     * Set registration.
     *
     * @param \AppBundle\Entity\Registration|null $registration
     *
     * @return HelloassoPayment
     */
    public function setRegistration(\AppBundle\Entity\Registration $registration = null)
    {
        $this->registration = $registration;

        return $this;
    }

    /**
     * Get registration.
     *
     * @return \AppBundle\Entity\Registration|null
     */
    public function getRegistration()
    {
        return $this->registration;
    }

    /**
     * Set paymentId.
     *
     * @param int $paymentId
     *
     * @return HelloassoPayment
     */
    public function setPaymentId($paymentId)
    {
        $this->paymentId = $paymentId;

        return $this;
    }

    /**
     * Get paymentId.
     *
     * @return int
     */
    public function getPaymentId()
    {
        return $this->paymentId;
    }

    /**
     * populate payment with action object.
     * https://dev.helloasso.com/v3/resources#detail-action
     *
     * @param object $ha_action_obj
     *
     * @return HelloassoPayment
     */
    public function fromActionObj($ha_action_obj)
    {

        $date = new \DateTime();
        $date->setTimestamp(strtotime($ha_action_obj->date));

        $amount = $ha_action_obj->amount;
        $amount = str_replace(',', '.', $amount);

        $this->setPaymentId($ha_action_obj->id_payment);
        $this->setDate($date);
        $this->setAmount($amount);
        $this->setCampaignId($ha_action_obj->id_campaign);
        $this->setPayerFirstName($ha_action_obj->first_name);
        $this->setPayerLastName($ha_action_obj->last_name);
        $this->setStatus($ha_action_obj->status);
        $this->setEmail($ha_action_obj->email);

        return $this;
    }

    public function fromPaymentObj($paymentObject, $campaignId)
    {
        $date = new \DateTime();
        $date->setTimestamp(strtotime($paymentObject->date));

        $amount = $paymentObject->amount;

        $this->setPaymentId($paymentObject->id);
        $this->setDate($date);
        $this->setAmount($amount);
        $this->setCampaignId($campaignId);
        $this->setPayerFirstName($paymentObject->payer_first_name);
        $this->setPayerLastName($paymentObject->payer_last_name);
        $this->setStatus($paymentObject->status);
        $this->setEmail($paymentObject->payer_email);

        return $this;
    }

    /**
     * Set campaignId.
     *
     * @param int $campaignId
     *
     * @return HelloassoPayment
     */
    public function setCampaignId($campaignId)
    {
        $this->campaignId = $campaignId;

        return $this;
    }

    /**
     * Get campaignId.
     *
     * @return int
     */
    public function getCampaignId()
    {
        return $this->campaignId;
    }

    /**
     * Set status.
     *
     * @param string $status
     *
     * @return HelloassoPayment
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set email.
     *
     * @param string $email
     *
     * @return HelloassoPayment
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }
}
