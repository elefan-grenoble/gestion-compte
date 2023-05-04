<?php

namespace AppBundle\Entity;

use AppBundle\Repository\PeriodPositionFreeLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * PeriodPositionFreeLog
 *
 * @ORM\Table(name="period_position_free_log")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PeriodPositionFreeLogRepository")
 */
class PeriodPositionFreeLog
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
     * @ORM\ManyToOne(targetEntity="PeriodPosition")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="SET NULL")
     */
    private $periodPosition;

    /**
     * @var string
     *
     * @ORM\Column(name="period_position_string", type="text")
     */
    private $periodPositionString;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", cascade={"remove"})
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
     */
    private $beneficiary;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="booked_time", type="datetime", nullable=true)
     */
    private $bookedTime;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $requestRoute;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(referencedColumnName="id")
     */
    private $createdBy;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->createdAt = new \DateTime();
    }

    public function getPeriodPosition(): ?PeriodPosition
    {
        return $this->periodPosition;
    }

    public function setPeriodPosition(?PeriodPosition $periodPosition): self
    {
        $this->periodPosition = $periodPosition;

        return $this;
    }

    public function getPeriodPositionString(): string
    {
        return $this->periodPositionString;
    }

    public function setPeriodPositionString(?string $periodPositionString): self
    {
        $this->periodPositionString = $periodPositionString;

        return $this;
    }

    public function getBeneficiary(): ?Beneficiary
    {
        return $this->beneficiary;
    }

    public function setBeneficiary(?Beneficiary $beneficiary): self
    {
        $this->beneficiary = $beneficiary;

        return $this;
    }

    public function getBookedTime()
    {
        return $this->bookedTime;
    }

    public function setBookedTime($bookedTime)
    {
        $this->bookedTime = $bookedTime;

        return $this;
    }

    public function getRequestRoute(): ?string
    {
        return $this->requestRoute;
    }

    public function setRequestRoute(?string $requestRoute): self
    {
        $this->requestRoute = $requestRoute;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $user): self
    {
        $this->createdBy = $user;

        return $this;
    }
}
