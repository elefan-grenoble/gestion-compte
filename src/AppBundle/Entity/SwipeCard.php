<?php

namespace AppBundle\Entity;

use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use CodeItNow\BarcodeBundle\Utils\QrCode;
use Doctrine\ORM\Mapping as ORM;

/**
 * SwipeCard
 *
 * @ORM\Table(name="swipe_card")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SwipeCardRepository")
 */
class SwipeCard
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
     * @var \DateTime
     *
     * @ORM\Column(name="disabled_at", type="datetime", nullable=true)
     */
    private $disabled_at;

    /**
     * @var int
     *
     * @ORM\Column(name="number", type="integer")
     */
    private $number;

    /**
     * @var bool
     *
     * @ORM\Column(name="enable", type="boolean", nullable=true, options={"default" : 0})
     */
    private $enable;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=255, unique=true)
     */
    private $code;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="swipe_cards")
     * @ORM\JoinColumn(name="beneficiary_id", referencedColumnName="id")
     */
    private $beneficiary;

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->created_at = new \DateTime();
        $this->disabled_at = null;
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
     * Set number.
     *
     * @param int $number
     *
     * @return SwipeCard
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Get number.
     *
     * @return int
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set code.
     *
     * @param string $code
     *
     * @return SwipeCard
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set enable.
     *
     * @param bool|null $enable
     *
     * @return SwipeCard
     */
    public function setEnable($enable = null)
    {
        $this->enable = $enable;

        if (!$enable){
            $this->setDisabledAt(new \DateTime('now'));
        }

        return $this;
    }

    /**
     * Get enable.
     *
     * @return bool|null
     */
    public function getEnable()
    {
        if ($this->getDisabledAt()) //forever
            return false;
        return $this->enable;
    }

    /**
     * Set beneficiary.
     *
     * @param \AppBundle\Entity\Beneficiary|null $beneficiary
     *
     * @return SwipeCard
     */
    public function setBeneficiary(\AppBundle\Entity\Beneficiary $beneficiary = null)
    {
        $this->beneficiary = $beneficiary;

        return $this;
    }

    /**
     * Get beneficiary.
     *
     * @return \AppBundle\Entity\Beneficiary|null
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }


    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return SwipeCard
     */
    public function setCreatedAt($createdAt)
    {
        $this->created_at = $createdAt;

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
     * Set disabledAt.
     *
     * @param \DateTime $disabledAt
     *
     * @return SwipeCard
     */
    public function setDisabledAt($disabledAt)
    {
        $this->disabled_at = $disabledAt;

        return $this;
    }

    /**
     * Get disabledAt.
     *
     * @return \DateTime
     */
    public function getDisabledAt()
    {
        return $this->disabled_at;
    }

    public function getBarcode()
    {
        $barcode = new BarcodeGenerator();
        $barcode->setText($this->getCode());
        $barcode->setType(BarcodeGenerator::Code128);
        $barcode->setScale(2);
        $barcode->setThickness(25);
        $barcode->setFontSize(10);
        return $barcode->generate();
    }
}
