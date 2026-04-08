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
        $projects = $this->createQueryBuilder('p')
            ->leftJoin('p.members', 'm')
            ->addSelect('m')
            ->addSelect('creator')
            ->leftJoin('p.createdBy', 'creator')
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        if ($user->isAdmin()) {
            return $projects;
        }

        return array_values(array_filter(
            $projects,
            static fn (Project $project): bool => $project->getCreatedBy()->getId()->equals($user->getId()) || $project->hasMember($user),
        ));
    }

    public function countAccessibleByUser(User $user): int
    {
        return count($this->findAccessibleByUser($user));
    }

    /**
     * @return list<Project>
     */
    public function findManageableByUser(User $user): array
    {
        if ($user->isAdmin()) {
            return $this->createQueryBuilder('p')
                ->orderBy('p.client', 'ASC')
                ->addOrderBy('p.name', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return array_values(array_filter(
            $this->findAccessibleByUser($user),
            static fn (Project $project): bool => $project->isManageableBy($user),
        ));
    }
}
