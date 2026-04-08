<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Project
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 160)]
    private string $client;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $domain = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $serverIp = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sshPublicKeyEncrypted = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sshPrivateKeyEncrypted = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $serverUser = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $serverPasswordEncrypted = null;

    #[ORM\Column(options: ['default' => 22])]
    private int $sshPort = 22;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $appSecretEncrypted = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $dbNameEncrypted = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $dbUserEncrypted = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $dbPasswordEncrypted = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'projectsCreated')]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinTable(name: 'project_members')]
    private Collection $members;

    /**
     * @var Collection<int, Secret>
     */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Secret::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $secrets;

    /**
     * @var Collection<int, ProjectAccessInvitation>
     */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectAccessInvitation::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $accessInvitations;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name = '', string $client = '')
    {
        $this->id = Uuid::v7();
        $this->name = trim($name);
        $this->client = trim($client);
        $this->members = new ArrayCollection();
        $this->secrets = new ArrayCollection();
        $this->accessInvitations = new ArrayCollection();
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

    public function getClient(): string
    {
        return $this->client;
    }

    public function setClient(string $client): static
    {
        $this->client = trim($client);

        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): static
    {
        $this->domain = null !== $domain ? trim($domain) : null;

        return $this;
    }

    public function getServerIp(): ?string
    {
        return $this->serverIp;
    }

    public function setServerIp(?string $serverIp): static
    {
        $this->serverIp = null !== $serverIp ? trim($serverIp) : null;

        return $this;
    }

    public function getSshPublicKeyEncrypted(): ?string
    {
        return $this->sshPublicKeyEncrypted;
    }

    public function setSshPublicKeyEncrypted(?string $sshPublicKeyEncrypted): static
    {
        $this->sshPublicKeyEncrypted = $sshPublicKeyEncrypted;

        return $this;
    }

    public function getSshPrivateKeyEncrypted(): ?string
    {
        return $this->sshPrivateKeyEncrypted;
    }

    public function setSshPrivateKeyEncrypted(?string $sshPrivateKeyEncrypted): static
    {
        $this->sshPrivateKeyEncrypted = $sshPrivateKeyEncrypted;

        return $this;
    }

    public function getServerUser(): ?string
    {
        return $this->serverUser;
    }

    public function setServerUser(?string $serverUser): static
    {
        $this->serverUser = null !== $serverUser ? trim($serverUser) : null;

        return $this;
    }

    public function getServerPasswordEncrypted(): ?string
    {
        return $this->serverPasswordEncrypted;
    }

    public function setServerPasswordEncrypted(?string $serverPasswordEncrypted): static
    {
        $this->serverPasswordEncrypted = $serverPasswordEncrypted;

        return $this;
    }

    public function getSshPort(): int
    {
        return $this->sshPort;
    }

    public function setSshPort(int $sshPort): static
    {
        $this->sshPort = $sshPort;

        return $this;
    }

    public function getAppSecretEncrypted(): ?string
    {
        return $this->appSecretEncrypted;
    }

    public function setAppSecretEncrypted(?string $appSecretEncrypted): static
    {
        $this->appSecretEncrypted = $appSecretEncrypted;

        return $this;
    }

    public function getDbNameEncrypted(): ?string
    {
        return $this->dbNameEncrypted;
    }

    public function setDbNameEncrypted(?string $dbNameEncrypted): static
    {
        $this->dbNameEncrypted = $dbNameEncrypted;

        return $this;
    }

    public function getDbUserEncrypted(): ?string
    {
        return $this->dbUserEncrypted;
    }

    public function setDbUserEncrypted(?string $dbUserEncrypted): static
    {
        $this->dbUserEncrypted = $dbUserEncrypted;

        return $this;
    }

    public function getDbPasswordEncrypted(): ?string
    {
        return $this->dbPasswordEncrypted;
    }

    public function setDbPasswordEncrypted(?string $dbPasswordEncrypted): static
    {
        $this->dbPasswordEncrypted = $dbPasswordEncrypted;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
        }

        return $this;
    }

    public function removeMember(User $member): static
    {
        $this->members->removeElement($member);

        return $this;
    }

    public function hasMember(User $user): bool
    {
        return $this->members->contains($user);
    }

    public function isAccessibleBy(User $user): bool
    {
        return $user->isAdmin() || $this->getCreatedBy()->getId()->equals($user->getId()) || $this->hasMember($user);
    }

    public function isManageableBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isLead() && $this->isAccessibleBy($user);
    }

    /**
     * @return Collection<int, Secret>
     */
    public function getSecrets(): Collection
    {
        return $this->secrets;
    }

    /**
     * @return Collection<int, ProjectAccessInvitation>
     */
    public function getAccessInvitations(): Collection
    {
        return $this->accessInvitations;
    }

    public function addAccessInvitation(ProjectAccessInvitation $invitation): static
    {
        if (!$this->accessInvitations->contains($invitation)) {
            $this->accessInvitations->add($invitation);
        }

        return $this;
    }

    public function addSecret(Secret $secret): static
    {
        if (!$this->secrets->contains($secret)) {
            $this->secrets->add($secret);
            $secret->setProject($this);
        }

        return $this;
    }

    public function removeSecret(Secret $secret): static
    {
        if ($this->secrets->removeElement($secret) && $secret->getProject() === $this) {
            $secret->setProject(null);
        }

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
