<?php

declare(strict_types=1);

namespace App\Shared\Audit\Repository;

use App\Shared\Audit\Entity\AuditLogEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<AuditLogEntry>
 */
final class AuditLogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLogEntry::class);
    }

    /**
     * GET /audit-events (docs/08-api-specification.md, section 39 ; Phase 10). Filtrage
     * manuel par organizationId, jamais via TenantFilter : AuditLogEntry n'implémente pas
     * TenantScopedInterface (voir son docblock) et organizationId y est nullable pour les
     * événements globaux - ceux-ci sont donc exclus explicitement ici (WHERE organizationId
     * = :organizationId exclut déjà tout NULL par construction SQL, mais l'expliciter évite
     * toute ambiguïté si cette requête est un jour modifiée).
     *
     * @return array{items: list<AuditLogEntry>, totalCount: int}
     */
    public function paginateForOrganization(
        Uuid $organizationId,
        ?string $entityType,
        ?string $entityId,
        ?\DateTimeImmutable $since,
        ?\DateTimeImmutable $until,
        int $page,
        int $perPage,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.organizationId = :organizationId')
            ->setParameter('organizationId', $organizationId, 'uuid');

        if (null !== $entityType) {
            $qb->andWhere('e.entityType = :entityType')->setParameter('entityType', $entityType);
        }

        if (null !== $entityId) {
            $qb->andWhere('e.entityId = :entityId')->setParameter('entityId', $entityId);
        }

        if (null !== $since) {
            $qb->andWhere('e.occurredAt >= :since')->setParameter('since', $since);
        }

        if (null !== $until) {
            $qb->andWhere('e.occurredAt <= :until')->setParameter('until', $until);
        }

        // Le clone doit précéder tout ->orderBy() (même contrainte PostgreSQL que
        // App\Compliance\Engine\Repository\ComplianceAnalysisRepository::paginateForOrganization).
        $totalCount = (int) (clone $qb)
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'totalCount' => $totalCount];
    }

    /**
     * GET /platform-admin/audit-events (docs/08-api-specification.md, section 38.2 ;
     * US-PLATFORMADMIN-003, plan Phase 15) - lecture cross-tenant explicite, jamais bornée à
     * une seule organisation contrairement à paginateForOrganization() ci-dessus. Réservée au
     * module App\PlatformAdmin (ADR-009) - jamais appelée depuis un contexte tenant.
     *
     * @return array{items: list<AuditLogEntry>, totalCount: int}
     */
    public function paginateAll(
        ?Uuid $organizationId,
        ?string $entityType,
        ?\DateTimeImmutable $since,
        ?\DateTimeImmutable $until,
        int $page,
        int $perPage,
    ): array {
        $qb = $this->createQueryBuilder('e');

        if (null !== $organizationId) {
            $qb->andWhere('e.organizationId = :organizationId')->setParameter('organizationId', $organizationId, 'uuid');
        }

        if (null !== $entityType) {
            $qb->andWhere('e.entityType = :entityType')->setParameter('entityType', $entityType);
        }

        if (null !== $since) {
            $qb->andWhere('e.occurredAt >= :since')->setParameter('since', $since);
        }

        if (null !== $until) {
            $qb->andWhere('e.occurredAt <= :until')->setParameter('until', $until);
        }

        $totalCount = (int) (clone $qb)
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'totalCount' => $totalCount];
    }
}
