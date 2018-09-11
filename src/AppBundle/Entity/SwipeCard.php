<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SwipeCard
 *
 * @ORM\Table(name="swipe_card")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SwipeCardRepository")
 */
class SwipeCard
{
    const PADLENGTH = 10;
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

        return $this;
    }

    /**
     * Get enable.
     *
     * @return bool|null
     */
    public function getEnable()
    {
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

    static public function generateCode(){
        $code = rand(0,pow(10,self::PADLENGTH));
        $code = str_pad($code, self::PADLENGTH, '0', STR_PAD_LEFT);
        return $code;
    }

}
