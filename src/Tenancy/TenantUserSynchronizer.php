<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TenantUserSynchronizer
{
    public function __construct(
        private TenantDatabaseManager $tenantDatabaseManager,
        private TenantDatabasePathResolver $pathResolver,
        private TenantDatabaseSwitcher $databaseSwitcher,
        private TenantContext $tenantContext,
        private UserRepository $users,
        private EntityManagerInterface $em,
    ) {
    }

    public function tenantDatabaseExists(string $tenantSlug): bool
    {
        return is_file($this->pathResolver->resolvePath($tenantSlug));
    }

    public function syncBootstrapUserToTenant(User $bootstrapUser, bool $createDatabase = true): void
    {
        $snapshot = $this->snapshot($bootstrapUser);
        $tenantSlug = $snapshot['tenantSlug'];
        if (null === $tenantSlug) {
            return;
        }

        if (!$createDatabase && !$this->tenantDatabaseExists($tenantSlug)) {
            return;
        }

        $this->preserveDatabaseContext(function () use ($tenantSlug, $snapshot, $createDatabase): void {
            if ($createDatabase) {
                $this->tenantDatabaseManager->ensureTenantDatabase($tenantSlug);
            } else {
                $this->databaseSwitcher->switchToTenant($tenantSlug);
            }

            $tenantUser = $this->findUserForSynchronization($snapshot);
            if (!$tenantUser instanceof User) {
                $tenantUser = new User($snapshot['email'], $snapshot['firstName'], $snapshot['lastName']);
                $this->em->persist($tenantUser);
            }

            $this->hydrateUser($tenantUser, $snapshot);
            $this->em->flush();
        });
    }

    public function syncTenantUserToBootstrap(User $tenantUser): void
    {
        $snapshot = $this->snapshot($tenantUser);
        $tenantSlug = $snapshot['tenantSlug'];
        if (null === $tenantSlug) {
            return;
        }

        $this->preserveDatabaseContext(function () use ($snapshot): void {
            $this->databaseSwitcher->resetToBaseDatabase();

            $bootstrapUser = $this->findUserForSynchronization($snapshot);
            if (!$bootstrapUser instanceof User) {
                $bootstrapUser = new User($snapshot['email'], $snapshot['firstName'], $snapshot['lastName']);
                $this->em->persist($bootstrapUser);
            }

            $this->hydrateUser($bootstrapUser, $snapshot);
            $this->em->flush();
        });
    }

    /**
     * @return array{
     *     email: string,
     *     tenantSlug: ?string,
     *     firstName: string,
     *     lastName: string,
     *     roles: array,
     *     password: string,
     *     isActive: bool,
     *     totpSecret: ?string,
     *     totpEnabled: bool,
     *     externalTenantUuid: ?string,
     *     externalUserUuid: ?string
     * }
     */
    private function snapshot(User $user): array
    {
        return [
            'email' => $user->getEmail(),
            'tenantSlug' => $user->getTenantSlug() ?? $this->tenantContext->getTenantSlug(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'password' => $user->getPassword(),
            'isActive' => $user->isActive(),
            'totpSecret' => $user->getTotpSecret(),
            'totpEnabled' => $user->isTotpAuthenticationEnabled(),
            'externalTenantUuid' => $user->getExternalTenantUuid(),
            'externalUserUuid' => $user->getExternalUserUuid(),
        ];
    }

    /**
     * @param array{
     *     email: string,
     *     tenantSlug: ?string,
     *     firstName: string,
     *     lastName: string,
     *     roles: array,
     *     password: string,
     *     isActive: bool,
     *     totpSecret: ?string,
     *     totpEnabled: bool,
     *     externalTenantUuid: ?string,
     *     externalUserUuid: ?string
     * } $snapshot
     */
    private function hydrateUser(User $user, array $snapshot): void
    {
        $user
            ->setTenantSlug($snapshot['tenantSlug'])
            ->setEmail($snapshot['email'])
            ->setFirstName($snapshot['firstName'])
            ->setLastName($snapshot['lastName'])
            ->setRoles(array_values(array_filter($snapshot['roles'], static fn (string $role): bool => 'ROLE_USER' !== $role)))
            ->setPassword($snapshot['password'])
            ->setIsActive($snapshot['isActive'])
            ->setExternalTenantUuid($snapshot['externalTenantUuid'])
            ->setExternalUserUuid($snapshot['externalUserUuid']);

        if (is_string($snapshot['totpSecret']) && '' !== $snapshot['totpSecret']) {
            $user->prepareTotp($snapshot['totpSecret']);
            if ($snapshot['totpEnabled']) {
                $user->enableTotp();
            }
        } else {
            $user->disableTotp();
        }
    }

    /**
     * @param array{
     *     email: string,
     *     tenantSlug: ?string,
     *     externalTenantUuid: ?string,
     *     externalUserUuid: ?string
     * } $snapshot
     */
    private function findUserForSynchronization(array $snapshot): ?User
    {
        $tenantUuid = $snapshot['externalTenantUuid'];
        $userUuid = $snapshot['externalUserUuid'];
        if (null !== $tenantUuid && null !== $userUuid) {
            $user = $this->users->findOneByProvisioningIdentity($tenantUuid, $userUuid);
            if ($user instanceof User) {
                return $user;
            }
        }

        return $this->users->findOneByEmailAndTenantSlug($snapshot['email'], $snapshot['tenantSlug']);
    }

    private function preserveDatabaseContext(callable $callback): void
    {
        $wasBaseDatabase = $this->databaseSwitcher->isUsingBaseDatabase();
        $currentTenantSlug = $this->databaseSwitcher->getCurrentTenantSlug();

        try {
            $callback();
        } finally {
            if ($wasBaseDatabase) {
                $this->databaseSwitcher->resetToBaseDatabase();

                return;
            }

            if (null !== $currentTenantSlug) {
                $this->databaseSwitcher->switchToTenant($currentTenantSlug);
            }
        }
    }
}
