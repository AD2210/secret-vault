<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SecretRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SecretRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Secret
{
    public const string TYPE_SERVER = 'server';
    public const string TYPE_SSH_KEY = 'ssh_key';
    public const string TYPE_APP = 'app';
    public const string TYPE_DB = 'db';
    public const string TYPE_API = 'api';
    public const string TYPE_PASSWORD = 'password';
    public const string TYPE_SECRET = 'secret';
    public const string TYPE_FTP = 'ftp';
    public const string TYPE_OTHER = 'other';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 40, options: ['default' => self::TYPE_SECRET])]
    private string $type = self::TYPE_SECRET;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $publicSecretEncrypted = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $privateSecretEncrypted = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $payloadEncrypted = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'secrets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name = '')
    {
        $this->id = Uuid::v7();
        $this->name = trim($name);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIdString(): string
    {
        return $this->id->toRfc4122();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = trim($type);

        return $this;
    }

    public function getPublicSecretEncrypted(): ?string
    {
        return $this->publicSecretEncrypted;
    }

    public function setPublicSecretEncrypted(?string $publicSecretEncrypted): static
    {
        $this->publicSecretEncrypted = $publicSecretEncrypted;

        return $this;
    }

    public function getPrivateSecretEncrypted(): ?string
    {
        return $this->privateSecretEncrypted;
    }

    public function setPrivateSecretEncrypted(?string $privateSecretEncrypted): static
    {
        $this->privateSecretEncrypted = $privateSecretEncrypted;

        return $this;
    }

    public function getPayloadEncrypted(): ?string
    {
        return $this->payloadEncrypted;
    }

    public function setPayloadEncrypted(?string $payloadEncrypted): static
    {
        $this->payloadEncrypted = $payloadEncrypted;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
