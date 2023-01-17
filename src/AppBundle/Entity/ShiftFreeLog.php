<?php

namespace AppBundle\Entity;

use AppBundle\Repository\ShiftFreeLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ShiftFreeLog
 *
 * @ORM\Table(name="shiftfreelog")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ShiftFreeLogRepository")
 */
class ShiftFreeLog
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
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="created_at", type="datetime_immutable")
     */
    private $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(referencedColumnName="id")
     */
    private $createdBy;

    /**
     * @ORM\ManyToOne(targetEntity="Shift", cascade={"remove"})
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
     */
    private $shift;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", cascade={"remove"})
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
     */
    private $beneficiary;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $reason;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $requestRoute;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getShift(): ?Shift
    {
        return $this->shift;
    }

    public function setShift(?Shift $shift): self
    {
        $this->shift = $shift;

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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;

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

}
