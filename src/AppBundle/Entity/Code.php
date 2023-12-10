<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Code
 *
 * @ORM\Table(name="code")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CodeRepository")
 */
class Code
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
     * @ORM\Column(name="value", type="string", length=255, nullable=true)
     */
    private $value;

    /**
     * @var bool
     *
     * @ORM\Column(name="closed", type="boolean", nullable=false, options={"default" : 0})
     */
    private $closed;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="registrar_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $registrar;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="start_date", type="datetime", nullable=true)
     */
    private $startDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="end_date", type="datetime", nullable=true)
     */
    private $endDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var CodeDevice
     * @ORM\ManyToOne(targetEntity="CodeDevice", inversedBy="codes")
     * @ORM\JoinColumn(name="codedevice_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    private $codeDevice;

    /**
     * Constructor
     */
    public function __construct()
    {
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
     * Set value
     *
     * @param string $value
     *
     * @return Code
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set registrar
     *
     * @param \AppBundle\Entity\User $registrar
     *
     * @return Code
     */
    public function setRegistrar(\AppBundle\Entity\User $registrar = null)
    {
        $this->registrar = $registrar;

        return $this;
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
     * Set closed
     *
     * @param boolean $closed
     *
     * @return Code
     */
    public function setClosed($closed)
    {
        $this->closed = $closed;

        return $this;
    }

    /**
     * Get closed
     *
     * @return boolean
     */
    public function getClosed()
    {
        return $this->closed;
    }

    /**
     * Set description
     *
     * @param string $value
     *
     * @return Code
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set start date
     *
     * @param \DateTime $date
     *
     * @return Code
     */
    public function setStartDate($date)
    {
        $this->startDate = $date;

        return $this;
    }

    /**
     * Get start date
     *
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Set end date
     *
     * @param \DateTime $date
     *
     * @return Code
     */
    public function setEndDate($date)
    {
        $this->endDate = $date;

        return $this;
    }

    /**
     * Get end date
     *
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        if (!$this->createdAt) {
            $this->createdAt = new \DateTime();
        }
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return CodeDevice
     */
    public function getCodeDevice()
    {
        return $this->codeDevice;
    }

    /**
     * @param mixed $codedevice
     */
    public function setCodeDevice($codedevice)
    {
        $this->codeDevice = $codedevice;
    }

    public function isInactive()
    {
        if ($this->codedevice && $this->codedevice->getType == 'igloohome') {
            $now = new \DateTime();
            return $this->closed || $this->startDate < $now || $this->endDate > $now;
        } else {
            return $this->closed;
        }
    }
}
