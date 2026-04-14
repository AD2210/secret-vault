<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TotpTwoFactorInterface
{
    public const string ROLE_ADMIN = 'ROLE_ADMIN';
    public const string ROLE_LEAD = 'ROLE_LEAD';
    public const string ROLE_EDITOR = 'ROLE_EDITOR';
    public const string ROLE_USER = 'ROLE_USER';

    private const array BUSINESS_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_LEAD,
        self::ROLE_EDITOR,
        self::ROLE_USER,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $totpEnabled = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'members')]
    private Collection $projects;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Project::class)]
    private Collection $projectsCreated;

    public function __construct(string $email = '', string $firstName = '', string $lastName = '')
    {
        $this->id = Uuid::v7();
        $this->email = mb_strtolower(trim($email));
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->projects = new ArrayCollection();
        $this->projectsCreated = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIdString(): string
    {
        return $this->id->toRfc4122();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getDisplayName(): string
    {
        $fullName = trim($this->firstName.' '.$this->lastName);

        return '' !== $fullName ? $fullName : $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_USER;

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique(array_filter($roles, static fn (string $role): bool => '' !== $role)));

        return $this;
    }

    public function getPrimaryRole(): string
    {
        foreach (self::BUSINESS_ROLES as $role) {
            if (in_array($role, $this->roles, true)) {
                return $role;
            }
        }

        return self::ROLE_USER;
    }

    public function setPrimaryRole(string $role): static
    {
        if (!in_array($role, self::BUSINESS_ROLES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported role "%s".', $role));
        }

        $this->roles = [$role];

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_LEAD => 'Lead',
            self::ROLE_EDITOR => 'Editeur',
            self::ROLE_USER => 'User',
        ];
    }

    public function getRoleLabel(): string
    {
        return self::roleLabels()[$this->getPrimaryRole()] ?? 'User';
    }

    public function isAdmin(): bool
    {
        return self::ROLE_ADMIN === $this->getPrimaryRole();
    }

    public function isLead(): bool
    {
        return self::ROLE_LEAD === $this->getPrimaryRole();
    }

    public function isEditor(): bool
    {
        return self::ROLE_EDITOR === $this->getPrimaryRole();
    }

    public function isStandardUser(): bool
    {
        return self::ROLE_USER === $this->getPrimaryRole();
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin() || $this->isLead();
    }

    public function canCreateProjects(): bool
    {
        return $this->isAdmin() || $this->isLead();
    }

    public function canCreateSecrets(): bool
    {
        return $this->isAdmin() || $this->isLead() || $this->isEditor();
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function prepareTotp(string $totpSecret): static
    {
        $this->totpSecret = trim($totpSecret);
        $this->totpEnabled = false;

        return $this;
    }

    public function enableTotp(): static
    {
        if (null === $this->totpSecret || '' === $this->totpSecret) {
            throw new \LogicException('TOTP secret must exist before enabling 2FA.');
        }

        $this->totpEnabled = true;

        return $this;
    }

    public function disableTotp(): static
    {
        $this->totpSecret = null;
        $this->totpEnabled = false;

        return $this;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->totpEnabled && null !== $this->totpSecret && '' !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): ?string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret || '' === $this->totpSecret) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret, 'sha1', 30, 6);
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->addMember($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            $project->removeMember($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjectsCreated(): Collection
    {
        return $this->projectsCreated;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);
        $data["\0".self::class."\0totpSecret"] = null;

        return $data;
    }

    public function eraseCredentials(): void
    {
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
