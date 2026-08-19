<?php

declare(strict_types=1);

namespace App\Compliance\Repository;

use App\Compliance\Entity\EligibilityDiagnostic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<EligibilityDiagnostic>
 */
final class EligibilityDiagnosticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EligibilityDiagnostic::class);
    }

    /** Dernier diagnostic calculé pour l'organisation, ou null si jamais configurée. */
    public function findLatest(Uuid $organizationId): ?EligibilityDiagnostic
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.organization = :organizationId')
            ->setParameter('organizationId', $organizationId, UuidType::NAME)
            ->orderBy('d.computedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
