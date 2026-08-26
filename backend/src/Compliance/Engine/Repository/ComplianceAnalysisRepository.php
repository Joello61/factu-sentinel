<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Repository;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Enum\ComplianceAnalysisStatus;
use App\Compliance\Engine\Enum\ComplianceResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ComplianceAnalysis>
 */
final class ComplianceAnalysisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ComplianceAnalysis::class);
    }

    /**
     * Anciens et nouveaux résultats tous deux consultables (US-COMPLIANCE-006), jamais
     * écrasés : simple liste triée, pas de filtre de statut.
     *
     * @return array{items: list<ComplianceAnalysis>, totalCount: int}
     */
    public function paginateForInvoice(Uuid $invoiceId, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.invoice = :invoiceId')
            ->setParameter('invoiceId', $invoiceId, UuidType::NAME);

        // Le clone doit précéder tout ->orderBy() : PostgreSQL rejette un ORDER BY sur une
        // colonne absente du SELECT en présence d'un COUNT() (ni agrégée, ni groupée).
        $totalCount = (int) (clone $qb)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Tri secondaire sur l'id (Uuid::v7(), ordonné dans le temps) : triggeredAt est
        // stocké en TIMESTAMP(0) (précision à la seconde), insuffisant pour départager deux
        // analyses lancées dans la même seconde -- sans lui, l'ordre "plus récent en premier"
        // ne serait pas garanti stable pour ce cas.
        $items = $qb
            ->orderBy('a.triggeredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'totalCount' => $totalCount];
    }

    /**
     * Dashboard (docs/08-api-specification.md, section 33, Phase 9) : la dernière
     * ComplianceAnalysis COMPLETED de chaque Invoice de l'organisation courante, jamais
     * l'historique complet (une correction déjà appliquée et réanalysée ne doit plus compter
     * comme un problème ouvert). organization_id filtré automatiquement par TenantFilter
     * (ComplianceAnalysis implements TenantScopedInterface), jamais un filtre manuel dupliqué
     * ici.
     *
     * La clé métier de "dernière" est triggeredAt (id en simple départage, jamais
     * l'inverse -- voir paginateForInvoice ci-dessus pour le même principe) : une analyse
     * est retenue quand aucune autre analyse COMPLETED de la même facture n'est plus
     * récente qu'elle.
     *
     * @return list<ComplianceAnalysis>
     */
    public function findLatestCompletedPerInvoice(): array
    {
        // Pattern "greatest-n-per-group" classique en NOT EXISTS, jamais un GROUP BY +
        // ré-appariement manuel côté PHP : reste en DQL (contrairement à une requête SQL
        // native), donc App\Shared\Doctrine\TenantFilter continue de s'appliquer
        // automatiquement à ComplianceAnalysis pour les deux occurrences de l'entité
        // (alias a et a2), y compris dans la sous-requête (ADR-004, backend/CLAUDE.md
        // section 9 : jamais un filtre organization_id ajouté manuellement).
        $subQb = $this->createQueryBuilder('a2')
            ->select('1')
            ->andWhere('a2.invoice = a.invoice')
            ->andWhere('a2.status = :completed')
            ->andWhere('a2.triggeredAt > a.triggeredAt OR (a2.triggeredAt = a.triggeredAt AND a2.id > a.id)');

        /** @var list<ComplianceAnalysis> $items */
        $items = $this->createQueryBuilder('a')
            ->andWhere('a.status = :completed')
            ->andWhere(sprintf('NOT EXISTS (%s)', $subQb->getDQL()))
            ->setParameter('completed', ComplianceAnalysisStatus::COMPLETED)
            ->orderBy('a.triggeredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    /**
     * Historique organisation-wide, paginé (docs/08-api-specification.md, section 29 bis ;
     * US-HISTORY-001) : toutes les analyses, sans filtre par facture, contrairement à
     * paginateForInvoice ci-dessus -- anciens et nouveaux résultats tous deux consultables,
     * jamais écrasés (US-COMPLIANCE-006).
     *
     * @return array{items: list<ComplianceAnalysis>, totalCount: int}
     */
    public function paginateForOrganization(
        ?ComplianceResult $globalResult,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        int $page,
        int $perPage,
    ): array {
        // Jointure eager sur invoice (App\Compliance\Engine\Http\ComplianceAnalysisHistoryView
        // a besoin de invoice_number pour chaque ligne) : évite un N+1 sur une liste
        // organisation-wide potentiellement plus large qu'une liste déjà scopée à une seule
        // facture (paginateForInvoice ci-dessus, où ce besoin n'existe pas).
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.invoice', 'i')
            ->addSelect('i');

        if (null !== $globalResult) {
            $qb->andWhere('a.globalResult = :globalResult')->setParameter('globalResult', $globalResult);
        }

        if (null !== $from) {
            $qb->andWhere('a.triggeredAt >= :from')->setParameter('from', $from);
        }

        if (null !== $to) {
            $qb->andWhere('a.triggeredAt <= :to')->setParameter('to', $to);
        }

        // Le clone doit précéder tout ->orderBy() : PostgreSQL rejette un ORDER BY sur une
        // colonne absente du SELECT en présence d'un COUNT() (ni agrégée, ni groupée).
        $totalCount = (int) (clone $qb)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('a.triggeredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'totalCount' => $totalCount];
    }

    /**
     * Agrégat cross-tenant explicite (plan Phase 15, App\Compliance\Engine\Service\
     * ComplianceHealthReader) - `tenant_filter` reste actif par défaut pour cette entité
     * tenant-scoped, suspendu ici le temps de cette unique requête (même patron que
     * App\Identity\Repository\InvitationRepository::findOneBySelector()) - défense en
     * profondeur, le firewall `platform_admin` n'active de toute façon jamais ce filtre.
     *
     * @return array{total: int, failed: int}
     */
    public function countByStatusSince(\DateTimeImmutable $since): array
    {
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('tenant_filter');

        if ($wasEnabled) {
            $filters->suspend('tenant_filter');
        }

        try {
            $total = (int) $this->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->andWhere('a.triggeredAt >= :since')
                ->setParameter('since', $since)
                ->getQuery()
                ->getSingleScalarResult();

            $failed = (int) $this->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->andWhere('a.triggeredAt >= :since')
                ->andWhere('a.status = :status')
                ->setParameter('since', $since)
                ->setParameter('status', ComplianceAnalysisStatus::FAILED)
                ->getQuery()
                ->getSingleScalarResult();

            return ['total' => $total, 'failed' => $failed];
        } finally {
            if ($wasEnabled) {
                $filters->restore('tenant_filter');
            }
        }
    }

    /**
     * Platform Analytics (docs/08-api-specification.md, section 38.3, Phase 16 ;
     * US-ANALYTICS-001) : résumé cumulé, toute l'historique de la plateforme, tous tenants
     * confondus - jamais restreint à la dernière analyse par facture (le patron
     * App\Compliance\Engine\Service\DashboardAggregator, Phase 9, ne s'applique pas ici : ce
     * résumé mesure l'usage cumulé réel du produit, pas l'état courant du parc de factures
     * d'une organisation). `failed` n'entre ni dans `completed` ni dans `conforme` -
     * App\PlatformAdmin\Service\PlatformAnalyticsAggregator ne doit jamais l'ajouter au
     * dénominateur du taux de conformité.
     *
     * Même patron de suspension de `tenant_filter` que countByStatusSince() ci-dessus.
     *
     * @return array{completed: int, conforme: int}
     */
    public function countCompletedAndConforme(): array
    {
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('tenant_filter');

        if ($wasEnabled) {
            $filters->suspend('tenant_filter');
        }

        try {
            $completed = (int) $this->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->andWhere('a.status = :completed')
                ->setParameter('completed', ComplianceAnalysisStatus::COMPLETED)
                ->getQuery()
                ->getSingleScalarResult();

            $conforme = (int) $this->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->andWhere('a.status = :completed')
                ->andWhere('a.globalResult = :conforme')
                ->setParameter('completed', ComplianceAnalysisStatus::COMPLETED)
                ->setParameter('conforme', ComplianceResult::CONFORME)
                ->getQuery()
                ->getSingleScalarResult();

            return ['completed' => $completed, 'conforme' => $conforme];
        } finally {
            if ($wasEnabled) {
                $filters->restore('tenant_filter');
            }
        }
    }

    /**
     * Platform Analytics (docs/08-api-specification.md, section 38.3, Phase 16 ;
     * US-ANALYTICS-002) : les ComplianceAnalysis COMPLETED dont triggeredAt tombe dans la
     * fenêtre demandée, un point par jour de déclenchement - sémantique volontairement
     * différente de countCompletedAndConforme() ci-dessus (activité quotidienne, pas un
     * cumul historique). Agrégation par jour laissée à App\PlatformAdmin\Service\
     * PlatformAnalyticsTrendAggregator (classe pure, pas ici) - même séparation
     * lecture/agrégation que App\Compliance\Engine\Service\DashboardAggregator.
     *
     * Même patron de suspension de `tenant_filter` que countByStatusSince() ci-dessus.
     *
     * @return list<array{triggeredAt: \DateTimeImmutable, globalResult: ?ComplianceResult}>
     */
    public function findTriggeredAtAndResultSince(\DateTimeImmutable $since): array
    {
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('tenant_filter');

        if ($wasEnabled) {
            $filters->suspend('tenant_filter');
        }

        try {
            $analyses = $this->createQueryBuilder('a')
                ->andWhere('a.status = :completed')
                ->andWhere('a.triggeredAt >= :since')
                ->setParameter('completed', ComplianceAnalysisStatus::COMPLETED)
                ->setParameter('since', $since)
                ->getQuery()
                ->getResult();

            return array_map(
                static fn (ComplianceAnalysis $analysis): array => [
                    'triggeredAt' => $analysis->getTriggeredAt(),
                    'globalResult' => $analysis->getGlobalResult(),
                ],
                $analyses,
            );
        } finally {
            if ($wasEnabled) {
                $filters->restore('tenant_filter');
            }
        }
    }
}
