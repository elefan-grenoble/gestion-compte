<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Job.
 *
 * @ORM\Table(name="job")
 *
 * @ORM\HasLifecycleCallbacks()
 *
 * @ORM\Entity(repositoryClass="App\Repository\JobRepository")
 */
class Job
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     *
     * @ORM\Column(name="name", type="string", length=191, unique=true)
     */
    private $name;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     *
     * @ORM\Column(name="color", type="string", length=255, unique=false)
     */
    private $color;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="url", type="string", length=255, nullable=true)
     */
    private $url;

    /**
     * @var int
     *
     * @ORM\Column(name="min_shifter_alert", type="integer", options={"default" : 2})
     */
    private $min_shifter_alert;

    /**
     * @ORM\OneToMany(targetEntity="Shift", mappedBy="job", cascade={"persist", "remove"}), orphanRemoval=true)
     */
    private $shifts;

    /**
     * @ORM\OneToMany(targetEntity="Period", mappedBy="job", cascade={"persist", "remove"}), orphanRemoval=true)
     */
    private $periods;

    /**
     * @var bool
     *
     * @ORM\Column(name="enabled", type="boolean", nullable=false, options={"default" : 1})
     */
    private $enabled;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     *
     * @ORM\JoinColumn(name="created_by_id", referencedColumnName="id")
     */
    private $createdBy;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->shifts = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
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
     * @return Job
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
     * Set color.
     *
     * @param string $color
     *
     * @return Job
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color.
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Get min_shifter_alert.
     *
     * @return int
     */
    public function getMinShifterAlert()
    {
        return $this->min_shifter_alert;
    }

    /**
     * Set min_shifter_alert.
     */
    public function setMinShifterAlert(int $min_shifter_alert): Job
    {
        $this->min_shifter_alert = $min_shifter_alert;

        return $this;
    }

    /**
     * Add shift.
     *
     * @return Job
     */
    public function addShift(Shift $shift)
    {
        $this->shifts[] = $shift;

        return $this;
    }

    /**
     * Remove shift.
     *
     * @return bool TRUE if this collection contained the specified element, FALSE otherwise
     */
    public function removeShift(Shift $shift)
    {
        return $this->shifts->removeElement($shift);
    }

    /**
     * Get shifts.
     *
     * @return Collection
     */
    public function getShifts()
    {
        return $this->shifts;
    }

    /**
     * Add period.
     *
     * @return Job
     */
    public function addPeriod(Period $period)
    {
        $this->periods[] = $period;

        return $this;
    }

    /**
     * Remove period.
     *
     * @return bool TRUE if this collection contained the specified element, FALSE otherwise
     */
    public function removePeriod(Period $period)
    {
        return $this->periods->removeElement($period);
    }

    /**
     * Get periods.
     *
     * @return Collection
     */
    public function getPeriods()
    {
        return $this->periods;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * Get description.
     */
    public function getDescription(): string
    {
        return $this->description ? $this->description : '';
    }

    /**
     * Set description.
     */
    public function setDescription(string $description): Job
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set url.
     *
     * @param string $url
     *
     * @return Job
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
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set createdBy.
     *
     * @return Job
     */
    public function setCreatedBy(?User $user = null)
    {
        $this->createdBy = $user;

        return $this;
    }

    /**
     * Get createdBy.
     *
     * @return User
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }
}
