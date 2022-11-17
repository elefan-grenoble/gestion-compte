<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * Event
 *
 * @ORM\Table(name="event")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EventRepository")
 * @Vich\Uploadable
 */
class Event
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
     * @Assert\NotBlank()
     * @ORM\Column(name="title", type="string", length=255)
     */
    private $title;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Length(
     *      min = 1,
     *      max = 1000,
     *      minMessage = "La description doit avoir au minimum {{ limit }} caractères",
     *      maxMessage = "La description ne doit pas dépasser {{ limit }} caractères"
     * )
     * @ORM\Column(name="description", type="text")
     */
    private $description;

    /**
     * NOTE: This is not a mapped field of entity metadata, just a simple property.
     *
     * @Vich\UploadableField(mapping="event_img", fileNameProperty="img", size="imgSize")
     *
     * @var File
     */
    private $imgFile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @var string
     */
    private $img;

    /**
     * @ORM\Column(type="integer",nullable=true)
     *
     * @var integer
     */
    private $imgSize;

    /**
     * @var \DateTime
     *
     * @Assert\DateTime()
     * @Assert\NotNull()
     * @ORM\Column(name="date", type="datetime")
     */
    private $date;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="max_date_of_last_registration", type="datetime", nullable=true)
     */
    private $max_date_of_last_registration;

    /**
     * @var bool
     *
     * @ORM\Column(name="need_proxy", type="boolean", unique=false, options={"default" : 0},nullable=true)
     */
    private $need_proxy;

    /**
     * @var bool
     *
     * @ORM\Column(name="anonymous_proxy", type="boolean", unique=false, options={"default" : 0},nullable=true)
     */
    private $anonymous_proxy;

    /**
     * @ORM\OneToMany(targetEntity="Proxy", mappedBy="event",cascade={"persist", "remove"})
     */
    private $proxies;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime")
     *
     * @var \DateTime
     */
    private $updatedAt;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->proxies = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->createdAt = new \DateTime();
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
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $image
     */
    public function setImgFile($image = null)
    {
        $this->imgFile = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getImgFile()
    {
        return $this->imgFile;
    }

    /**
     * Set title
     *
     * @param string $title
     *
     * @return Event
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set date
     *
     * @param \DateTime $date
     *
     * @return Event
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
     * Get time
     *
     * @return \DateTime
     */
    public function getTime()
    {
        return $this->date;
    }

    /**
     * Add proxy
     *
     * @param \AppBundle\Entity\Proxy $proxy
     *
     * @return Event
     */
    public function addProxy(\AppBundle\Entity\Proxy $proxy)
    {
        $this->proxys[] = $proxy;

        return $this;
    }

    /**
     * Remove proxy
     *
     * @param \AppBundle\Entity\Proxy $proxy
     */
    public function removeProxy(\AppBundle\Entity\Proxy $proxy)
    {
        $this->proxys->removeElement($proxy);
    }

    public function getProxiesByOwner(Beneficiary $beneficiary)
    {
        return $this->proxies->filter(function (Proxy $proxy) use ($beneficiary) {
            return ($proxy->getOwner() === $beneficiary);
        });
    }

    public function getProxiesByGiver(Membership $membership)
    {
        return $this->proxies->filter(function (Proxy $proxy) use ($membership) {
            return ($proxy->getGiver() === $membership);
        });
    }

    /**
     * Set address
     *
     * @param \AppBundle\Entity\Address $address
     *
     * @return Event
     */
    public function setAddress(\AppBundle\Entity\Address $address = null)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get address
     *
     * @return \AppBundle\Entity\Address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Get proxies
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getProxies()
    {
        return $this->proxies;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Event
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
     * Set needProxy
     *
     * @param boolean $needProxy
     *
     * @return Event
     */
    public function setNeedProxy($needProxy)
    {
        $this->need_proxy = $needProxy;

        return $this;
    }

    /**
     * Get needProxy
     *
     * @return boolean
     */
    public function getNeedProxy()
    {
        return $this->need_proxy;
    }

    /**
     * Set anonymousProxy
     *
     * @param boolean $anonymousProxy
     *
     * @return Event
     */
    public function setAnonymousProxy($anonymousProxy)
    {
        $this->anonymous_proxy = $anonymousProxy;

        return $this;
    }

    /**
     * Get anonymousProxy
     *
     * @return boolean
     */
    public function getAnonymousProxy()
    {
        return $this->anonymous_proxy;
    }

    /**
     * Set maxDateOfLastRegistration
     *
     * @param \DateTime $maxDateOfLastRegistration
     *
     * @return Event
     */
    public function setMaxDateOfLastRegistration($maxDateOfLastRegistration)
    {
        $this->max_date_of_last_registration = $maxDateOfLastRegistration;

        return $this;
    }

    /**
     * Get maxDateOfLastRegistration
     *
     * @return \DateTime
     */
    public function getMaxDateOfLastRegistration()
    {
        if (is_null($this->max_date_of_last_registration)) {
            return $this->date;
        }
        return $this->max_date_of_last_registration;
    }

    /**
     * Get minDateOfLastRegistration
     *
     * @return \DateTime
     */
    public function getMinDateOfLastRegistration()
    {
        $registrationDuration = $this->getParameter('registration_duration');
        if (!is_null($registrationDuration)) {
            $minDateOfLastRegistration = clone $this->getMaxDateOfLastRegistration();
            $minDateOfLastRegistration->modify('-'.$registrationDuration);
            return $minDateOfLastRegistration;
        }
        return null;
    }

    /**
     * Set img
     *
     * @param string|null $img
     *
     * @return Event
     */
    public function setImg($img = null)
    {
        $this->img = $img;

        return $this;
    }

    /**
     * Get img
     *
     * @return string|null
     */
    public function getImg()
    {
        return $this->img;
    }

    /**
     * Set imgSize
     *
     * @param int|null $imgSize
     *
     * @return Event
     */
    public function setImgSize($imgSize = null)
    {
        $this->imgSize = $imgSize;

        return $this;
    }

    /**
     * Get imgSize
     *
     * @return int|null
     */
    public function getImgSize()
    {
        return $this->imgSize;
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
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
