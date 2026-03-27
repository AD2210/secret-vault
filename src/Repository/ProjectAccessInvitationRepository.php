<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectAccessInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectAccessInvitation>
 */
final class ProjectAccessInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectAccessInvitation::class);
    }

    public function findOneByPlainToken(string $plainToken): ?ProjectAccessInvitation
    {
        return $this->findOneBy(['tokenHash' => hash('sha256', $plainToken)]);
    }

    public function hasPendingInvitation(Project $project, string $email): bool
    {
        return null !== $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.email = :email')
            ->andWhere('i.revokedAt IS NULL')
            ->andWhere('i.acceptedAt IS NULL')
            ->setParameter('project', $project)
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
