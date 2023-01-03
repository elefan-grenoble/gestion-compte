<?php

namespace AppBundle\Entity;

use AppBundle\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Membership
 *
 * @ORM\Table(name="auditlog")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AuditLogRepository")
 */
class AuditLog
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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $entityType;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     */
    private $entityId;

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
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $action;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $requestRoute;

    /**
     * @var array
     *
     * @ORM\Column(type="array")
     */
    private $eventData;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

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

    public function getEventData(): array
    {
        return $this->eventData;
    }

    public function setEventData(array $eventData): self
    {
        $this->eventData = $eventData;

        return $this;
    }

}
