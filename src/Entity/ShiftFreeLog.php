<?php

namespace App\Entity;

use App\Repository\ShiftFreeLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ShiftFreeLog
 *
 * @ORM\Table(name="shiftfreelog")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="App\Repository\ShiftFreeLogRepository")
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
     * @ORM\ManyToOne(targetEntity="Shift", inversedBy="freeLogs")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="SET NULL")
     */
    private $shift;

    /**
     * @var string
     *
     * @ORM\Column(name="shift_string", type="text")
     */
    private $shiftString;

    /**
     * @ORM\ManyToOne(targetEntity="Beneficiary", cascade={"remove"})
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
     */
    private $beneficiary;

    /**
     * @var bool
     *
     * @ORM\Column(name="fixe", type="boolean", options={"default" : 0}, nullable=false)
     */
    private $fixe = false;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        if (!$this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
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

    public function getShiftString(): string
    {
        return $this->shiftString;
    }

    public function setShiftString(?string $shiftString): self
    {
        $this->shiftString = $shiftString;

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

    /**
     * @return bool
     */
    public function isFixe(): ?bool {
        return $this->fixe;
    }

    /**
     * @param bool $fixe
     */
    public function setFixe(?bool $fixe): void {
        $this->fixe = $fixe;
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

    public function getCreatedAt(): ?\DateTimeImmutable
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
