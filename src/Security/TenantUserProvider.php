<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantDatabaseSwitcher;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class TenantUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private UserRepository $users,
        private TenantContext $tenantContext,
        private TenantDatabaseSwitcher $databaseSwitcher,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->databaseSwitcher->isUsingBaseDatabase()
            ? $this->loadBootstrapUser($identifier)
            : $this->users->findOneBy(['email' => mb_strtolower(trim($identifier))]);

        if ($user instanceof User) {
            return $user;
        }

        $exception = new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        $exception->setUserIdentifier($identifier);

        throw $exception;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $this->users->upgradePassword($user, $newHashedPassword);
    }

    private function loadBootstrapUser(string $identifier): ?User
    {
        $tenantSlug = $this->tenantContext->getTenantSlug();
        if (null !== $tenantSlug) {
            return $this->users->findOneByEmailAndTenantSlug($identifier, $tenantSlug);
        }

        return $this->users->findOneBy(['email' => mb_strtolower(trim($identifier))]);
    }
}
