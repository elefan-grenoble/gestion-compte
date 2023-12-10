<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CodeDevice
 *
 * @ORM\Table(name="code_device")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CodeDeviceRepository")
 */
class CodeDevice
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
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=255)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="igloohome_api_key", type="string", length=255, nullable=true)
     * @Assert\Expression(
     *     expression="this.getType() != 'igloohome' || (this.getType() == 'igloohome' && value != '')",
     *     message="If this is a Igloohome lock, API Key must be set"
     * )
     */
    private $igloohome_api_key;

    /**
     * @var string
     *
     * @ORM\Column(name="igloohome_lock_id", type="string", length=255, nullable=true)
     * @Assert\Expression(
     *     expression="this.getType() != 'igloohome' || (this.getType() == 'igloohome' && value != '')",
     *     message="If this is a Igloohome lock, Lock Id must be set"
     * )
     */
    private $igloohome_lock_id;

    /**
     * @var bool
     *
     * @ORM\Column(name="enabled", type="boolean")
     */
    private $enabled;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime")
     */
    private $updatedAt;

    /**
     * @ORM\OneToMany(targetEntity="Code", mappedBy="codeDevice")
     */
    private $codes;

    /**
     * Define toString.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
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
     * Set name.
     *
     * @param string $name
     *
     * @return CodeDevice
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return CodeDevice
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set type.
     *
     * @param string $type
     *
     * @return CodeDevice
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set Igloohome API Key.
     *
     * @param string $type
     *
     * @return CodeDevice
     */
    public function setIgloohomeApiKey($key)
    {
        $this->igloohome_api_key = $key;

        return $this;
    }

    /**
     * Get Igloohome API Key.
     *
     * @return string
     */
    public function getIgloohomeApiKey()
    {
        return $this->igloohome_api_key;
    }

    /**
     * Set Igloohome lock id;.
     *
     * @param string $type
     *
     * @return CodeDevice
     */
    public function setIgloohomeLockId($id)
    {
        $this->igloohome_lock_id = $id;

        return $this;
    }

    /**
     * Get Igloohome lock id.
     *
     * @return string
     */
    public function getIgloohomeLockId()
    {
        return $this->igloohome_lock_id;
    }

    /**
     * Set enabled.
     *
     * @param bool $enabled
     *
     * @return CodeDevice
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Get enabled.
     *
     * @return bool
     */
    public function getEnabled()
    {
        return $this->enabled;
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
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function setUpdatedAtValue()
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Get updatedAt.
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

}
