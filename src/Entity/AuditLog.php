<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AuditLog
{
    public const string EVENT_SECRET_CREATED = 'secret.created';
    public const string EVENT_SECRET_UPDATED = 'secret.updated';
    public const string EVENT_SECRET_REVEAL_GRANTED = 'secret.reveal_granted';
    public const string EVENT_SECRET_COPIED = 'secret.copied';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 80)]
    private string $eventType;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $subjectType = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $subjectId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    /**
     * @var array<string, scalar|array<array-key, scalar|null>|null>
     */
    #[ORM\Column(type: 'json')]
    private array $context = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $eventType = '')
    {
        $this->id = Uuid::v7();
        $this->eventType = trim($eventType);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = trim($eventType);

        return $this;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function setSubjectType(?string $subjectType): static
    {
        $this->subjectType = null !== $subjectType ? trim($subjectType) : null;

        return $this;
    }

    public function getSubjectId(): ?string
    {
        return $this->subjectId;
    }

    public function setSubjectId(?string $subjectId): static
    {
        $this->subjectId = null !== $subjectId ? trim($subjectId) : null;

        return $this;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = null !== $ipAddress ? trim($ipAddress) : null;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = null !== $userAgent ? trim($userAgent) : null;

        return $this;
    }

    /**
     * @return array<string, scalar|array<array-key, scalar|null>|null>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array<string, scalar|array<array-key, scalar|null>|null> $context
     */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
