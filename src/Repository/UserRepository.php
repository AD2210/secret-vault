<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
final class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<User>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByProvisioningIdentity(string $tenantUuid, string $userUuid): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.externalTenantUuid = :tenantUuid')
            ->andWhere('u.externalUserUuid = :userUuid')
            ->setParameter('tenantUuid', $tenantUuid)
            ->setParameter('userUuid', $userUuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<User>
     */
    public function findAssignableByTenant(?string $tenantUuid): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC');

        if (null !== $tenantUuid && '' !== $tenantUuid) {
            $qb->andWhere('u.externalTenantUuid = :tenantUuid')
                ->setParameter('tenantUuid', $tenantUuid);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByEmailInTenant(string $email, ?string $tenantUuid): ?User
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', mb_strtolower(trim($email)));

        if (null !== $tenantUuid && '' !== $tenantUuid) {
            $qb->andWhere('u.externalTenantUuid = :tenantUuid')
                ->setParameter('tenantUuid', $tenantUuid);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
