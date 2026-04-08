<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserInvitation>
 */
final class UserInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserInvitation::class);
    }

    public function findOneByPlainToken(string $plainToken): ?UserInvitation
    {
        return $this->findOneBy(['tokenHash' => hash('sha256', $plainToken)]);
    }

    public function hasPendingInvitationForEmail(string $email): bool
    {
        return null !== $this->createQueryBuilder('i')
            ->andWhere('i.email = :email')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.revokedAt IS NULL')
            ->andWhere('i.expiresAt >= :now')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<UserInvitation>
     */
    public function findPendingVisibleToManager(User $manager): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.invitedBy', 'inviter')->addSelect('inviter')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.revokedAt IS NULL')
            ->andWhere('i.expiresAt >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('i.createdAt', 'DESC');

        if (!$manager->isAdmin()) {
            $qb->andWhere('i.invitedBy = :manager')
                ->setParameter('manager', $manager);
        }

        return $qb->getQuery()->getResult();
    }
}
