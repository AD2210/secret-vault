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
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $publicSecretEncrypted = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $privateSecretEncrypted = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'secrets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

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
