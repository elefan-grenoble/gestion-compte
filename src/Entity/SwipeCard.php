<?php

namespace App\Entity;

use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use CodeItNow\BarcodeBundle\Utils\QrCode;
use Doctrine\ORM\Mapping as ORM;

/**
 * SwipeCard
 *
 * @ORM\Table(name="swipe_card")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="App\Repository\SwipeCardRepository")
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
     * @ORM\Column(name="code", type="string", length=50, unique=true)
     */
    private $code;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", inversedBy="swipe_cards")
     * @ORM\JoinColumn(name="beneficiary_id", referencedColumnName="id")
     */
    private $beneficiary;

    /**
     * @ORM\OneToMany(targetEntity="SwipeCardLog", mappedBy="swipeCard",cascade={"persist"})
     */
    private $logs;

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
        }else{
            $this->setDisabledAt(null);
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
     * @param \App\Entity\Beneficiary|null $beneficiary
     *
     * @return SwipeCard
     */
    public function setBeneficiary(\App\Entity\Beneficiary $beneficiary = null)
    {
        $this->beneficiary = $beneficiary;

        return $this;
    }

    /**
     * Get beneficiary.
     *
     * @return \App\Entity\Beneficiary|null
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
     * @param \DateTime? $disabledAt
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
        $barcode->setType(BarcodeGenerator::Ean13);
        $barcode->setScale(2);
        $barcode->setThickness(25);
        $barcode->setFontSize(10);
        return $barcode->generate();
    }

    //FROM : \CodeItNow\BarcodeBundle\Generator\CINean13::calculateChecksum
    public static function checkEAN13($code,$checksum = null)
    {
        $c = strlen($code);
        if ($c === 13) {
            if (!$checksum){
                $checksum = substr($code, -1, 1);
            }
            $code = substr($code, 0, 12);
        } elseif ($c !== 12 || !$checksum) {
            return false;
        }
        $odd = true;
        $checksumValue = 0;
        $keys = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $c = strlen($code);
        for ($i = $c; $i > 0; $i--) {
            if ($odd === true) {
                $multiplier = 3;
                $odd = false;
            } else {
                $multiplier = 1;
                $odd = true;
            }

            if (!isset($keys[$code[$i - 1]])) {
                return;
            }

            $checksumValue += $keys[$code[$i - 1]] * $multiplier;
        }

        $checksumValue = (10 - $checksumValue % 10) % 10;

        return $checksumValue == $checksum;
    }
}
