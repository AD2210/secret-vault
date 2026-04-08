<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
final class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function hasSeenFingerprint(User $user, ?string $ipAddress, ?string $userAgent, \DateTimeImmutable $since): bool
    {
        if (null === $ipAddress || '' === trim($ipAddress) || null === $userAgent || '' === trim($userAgent)) {
            return true;
        }

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.actor = :actor')
            ->andWhere('a.ipAddress = :ipAddress')
            ->andWhere('a.userAgent = :userAgent')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('actor', $user)
            ->setParameter('ipAddress', trim($ipAddress))
            ->setParameter('userAgent', trim($userAgent))
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
