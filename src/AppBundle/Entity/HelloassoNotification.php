<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * HelloassoNotification
 *
 * @ORM\Table(name="helloasso_notification")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\HelloassoNotificationRepository")
 */
class HelloassoNotification
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
     * @ORM\Column(name="notification_id", type="integer", unique=true)
     */
    private $notificationId;

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
     * @ORM\Column(name="url", type="string")
     */
    private $url;

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
     * @ORM\Column(name="url_receipt", type="string")
     */
    private $url_receipt;

    /**
     * @var string
     *
     * @ORM\Column(name="url_tax_receipt", type="string")
     */
    private $url_tax_receipt;

    /**
     * @var string
     *
     * @ORM\Column(name="content", type="text")
     */
    private $content;

    /**
     * @ORM\OneToOne(targetEntity="Registration")
     * @ORM\JoinColumn(name="registration_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $registration;

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
     * @return HelloassoNotification
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
     * Set notificationId.
     *
     * @param int $notificationId
     *
     * @return HelloassoNotification
     */
    public function setNotificationId($notificationId)
    {
        $this->notificationId = $notificationId;

        return $this;
    }

    /**
     * Get notificationId.
     *
     * @return int
     */
    public function getNotificationId()
    {
        return $this->notificationId;
    }

    /**
     * Set content.
     *
     * @param string $content
     *
     * @return HelloassoNotification
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set date.
     *
     * @param \DateTime $date
     *
     * @return HelloassoNotification
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
     * @return HelloassoNotification
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
     * Set url.
     *
     * @param string $url
     *
     * @return HelloassoNotification
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set payerFirstName.
     *
     * @param string $payerFirstName
     *
     * @return HelloassoNotification
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
     * @return HelloassoNotification
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
     * Set urlReceipt.
     *
     * @param string $urlReceipt
     *
     * @return HelloassoNotification
     */
    public function setUrlReceipt($urlReceipt)
    {
        $this->url_receipt = $urlReceipt;

        return $this;
    }

    /**
     * Get urlReceipt.
     *
     * @return string
     */
    public function getUrlReceipt()
    {
        return $this->url_receipt;
    }

    /**
     * Set urlTaxReceipt.
     *
     * @param string $urlTaxReceipt
     *
     * @return HelloassoNotification
     */
    public function setUrlTaxReceipt($urlTaxReceipt)
    {
        $this->url_tax_receipt = $urlTaxReceipt;

        return $this;
    }

    /**
     * Get urlTaxReceipt.
     *
     * @return string
     */
    public function getUrlTaxReceipt()
    {
        return $this->url_tax_receipt;
    }

    /**
     * Set registration.
     *
     * @param \AppBundle\Entity\Registration|null $registration
     *
     * @return HelloassoNotification
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
}
