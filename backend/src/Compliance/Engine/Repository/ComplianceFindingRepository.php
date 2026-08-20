<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Repository;

use App\Compliance\Engine\Entity\ComplianceFinding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ComplianceFinding>
 */
final class ComplianceFindingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ComplianceFinding::class);
    }

    /**
     * Pas de filtre organization_id ici : ComplianceFinding n'est pas TenantScopedInterface
     * (scoping hérité via ComplianceAnalysis, comme InvoiceLine via Invoice). L'appelant
     * doit toujours avoir résolu $complianceAnalysisId via
     * App\Compliance\Engine\Repository\ComplianceAnalysisRepository (tenant-scoped) avant
     * d'appeler cette méthode -- jamais un id de finding accepté directement depuis une
     * requête client sans être passé par cette résolution.
     *
     * @return list<ComplianceFinding>
     */
    public function findByAnalysis(Uuid $complianceAnalysisId): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.complianceAnalysis = :analysisId')
            ->setParameter('analysisId', $complianceAnalysisId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Première résolution directe d'un ComplianceFinding par son propre id (Phase 8,
     * POST /compliance-findings/{id}/explanations) : contrairement à findByAnalysis(),
     * l'appelant n'a pas déjà résolu une ComplianceAnalysis tenant-scoped en amont. La
     * jointure vers ComplianceAnalysis (TenantScopedInterface) suffit à appliquer le filtre
     * global App\Shared\Doctrine\TenantFilter sur l'alias joint 'a' - aucun filtre manuel
     * supplémentaire nécessaire. Un finding absent ou appartenant à une autre organisation
     * retourne indistinctement null (backend/CLAUDE.md, section 6 : toujours 404, jamais 403).
     */
    public function findByIdWithTenantCheck(Uuid $id): ?ComplianceFinding
    {
        return $this->createQueryBuilder('f')
            ->innerJoin('f.complianceAnalysis', 'a')
            ->andWhere('f.id = :id')
            ->setParameter('id', $id, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
