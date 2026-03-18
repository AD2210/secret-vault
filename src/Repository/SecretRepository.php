<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Secret;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Secret>
 */
final class SecretRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Secret::class);
    }

    public function countAccessibleByUser(User $user): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.id)')
            ->join('s.project', 'p');

        if (!$user->isAdmin()) {
            $qb
                ->leftJoin('p.members', 'm')
                ->andWhere('m = :user OR p.createdBy = :user')
                ->setParameter('user', $user);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
