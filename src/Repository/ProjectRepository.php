<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
final class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @return list<Project>
     */
    public function findAccessibleByUser(User $user): array
    {
        $qb = $this->createQueryBuilder('p')
            ->distinct()
            ->orderBy('p.updatedAt', 'DESC');

        if (!$user->isAdmin()) {
            $qb
                ->leftJoin('p.members', 'm')
                ->addSelect('m')
                ->andWhere(':user MEMBER OF p.members OR p.createdBy = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAccessibleByUser(User $user): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)');

        if (!$user->isAdmin()) {
            $qb
                ->leftJoin('p.members', 'm')
                ->andWhere(':user MEMBER OF p.members OR p.createdBy = :user')
                ->setParameter('user', $user);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
