<?php

declare(strict_types=1);

namespace App\Organization\Repository;

use App\Organization\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 */
final class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    /**
     * GET /platform-admin/organizations (docs/08-api-specification.md, section 38.2 ;
     * US-PLATFORMADMIN-001). `Organization` n'implémente jamais `TenantScopedInterface`
     * (c'est le tenant racine, section 25 data-model) - cette lecture cross-tenant n'a donc
     * jamais besoin de suspendre `tenant_filter` (contrairement à
     * App\Identity\Repository\InvitationRepository::findOneBySelector()) : ce filtre ne
     * s'applique de toute façon jamais à cette entité, filtre actif ou non.
     *
     * @return array{items: list<Organization>, totalCount: int}
     */
    public function paginate(?bool $suspended, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('o');

        if (null !== $suspended) {
            $qb->andWhere($suspended ? 'o.suspendedAt IS NOT NULL' : 'o.suspendedAt IS NULL');
        }

        // Le clone doit précéder tout ->orderBy() : PostgreSQL rejette un ORDER BY sur une
        // colonne absente du SELECT en présence d'un COUNT() (ni agrégée, ni groupée).
        $totalCount = (int) (clone $qb)
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('o.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'totalCount' => $totalCount];
    }

    /**
     * Platform Analytics (docs/08-api-specification.md, section 38.3, Phase 16 ;
     * US-ANALYTICS-001) : total cumulé, toute l'historique - même raisonnement que
     * paginate() ci-dessus, `Organization` n'est jamais tenant-scoped, aucun filtre à
     * suspendre. Compte les organisations suspendues (App\PlatformAdmin\Service\
     * SuspendOrganizationService) : une suspension ne supprime jamais l'organisation
     * (docs/07-data-model.md, invariant Organization Phase 15), elle reste un usage réel du
     * produit.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Platform Analytics (docs/08-api-specification.md, section 38.3, Phase 16 ;
     * US-ANALYTICS-002) : dates de création des organisations créées dans la fenêtre
     * demandée, agrégées par jour côté PHP par App\PlatformAdmin\Service\
     * PlatformAnalyticsTrendAggregator (patron App\Compliance\Engine\Service\
     * DashboardAggregator - jamais un nouveau mécanisme d'agrégation), pas ici.
     *
     * @return list<\DateTimeImmutable>
     */
    public function findCreatedAtBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        // Entités complètes plutôt qu'une hydratation partielle/scalaire (volume MVP modeste,
        // docs/07-data-model.md section 38) - même choix que App\Compliance\Engine\Service\
        // DashboardAggregator, qui reçoit des entités déjà chargées plutôt qu'une projection.
        $organizations = $this->createQueryBuilder('o')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt < :until')
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (Organization $organization): \DateTimeImmutable => $organization->getCreatedAt(),
            $organizations,
        );
    }
}
