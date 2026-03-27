<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProjectAccessInvitationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProjectAccessInvitationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProjectAccessInvitation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'accessInvitations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $invitedBy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $inviteeUser = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $recipientConfirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ownerApprovedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Project $project, User $invitedBy, string $email, string $tokenHash, \DateTimeImmutable $expiresAt)
    {
        $this->id = Uuid::v7();
        $this->project = $project;
        $this->invitedBy = $invitedBy;
        $this->email = mb_strtolower(trim($email));
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIdString(): string
    {
        return $this->id->toRfc4122();
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getInvitedBy(): User
    {
        return $this->invitedBy;
    }

    public function getInviteeUser(): ?User
    {
        return $this->inviteeUser;
    }

    public function setInviteeUser(?User $inviteeUser): static
    {
        $this->inviteeUser = $inviteeUser;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRecipientConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->recipientConfirmedAt;
    }

    public function markRecipientConfirmed(?\DateTimeImmutable $at = null): static
    {
        $this->recipientConfirmedAt ??= $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function getOwnerApprovedAt(): ?\DateTimeImmutable
    {
        return $this->ownerApprovedAt;
    }

    public function approve(?\DateTimeImmutable $at = null): static
    {
        $this->ownerApprovedAt ??= $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function markAccepted(?\DateTimeImmutable $at = null): static
    {
        $this->acceptedAt ??= $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(?\DateTimeImmutable $at = null): static
    {
        $this->revokedAt ??= $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt < ($now ?? new \DateTimeImmutable());
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isRecipientConfirmed(): bool
    {
        return null !== $this->recipientConfirmedAt;
    }

    public function isApproved(): bool
    {
        return null !== $this->ownerApprovedAt;
    }

    public function isAccepted(): bool
    {
        return null !== $this->acceptedAt;
    }

    public function getStatusLabel(): string
    {
        if ($this->isRevoked()) {
            return 'Révoquée';
        }

        if ($this->isAccepted()) {
            return 'Acceptée';
        }

        if ($this->isExpired()) {
            return 'Expirée';
        }

        if ($this->isRecipientConfirmed() && !$this->isApproved()) {
            return 'En attente de validation propriétaire';
        }

        if (!$this->isRecipientConfirmed()) {
            return 'Invitation envoyée';
        }

        return 'En attente d’activation';
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
