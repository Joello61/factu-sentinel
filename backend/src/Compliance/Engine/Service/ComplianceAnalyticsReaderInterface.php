<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Enum\ComplianceResult;

/**
 * Interface exposée par le module Compliance/Engine pour son seul consommateur cross-tenant
 * légitime, App\PlatformAdmin\Service\PlatformAnalyticsAggregator /
 * App\PlatformAdmin\Controller\GetPlatformAnalyticsTrendsController (US-ANALYTICS-001/002,
 * plan Phase 16) - un module ne lit jamais directement les données internes d'un autre module
 * (backend/CLAUDE.md, section 3) ; PlatformAdmin ne connaît donc jamais
 * App\Compliance\Engine\Repository\ComplianceAnalysisRepository directement. Interface
 * séparée de App\Compliance\Engine\Service\ComplianceHealthReaderInterface (Phase 15) : la
 * santé applicative (monitoring opérationnel) et les analytics (usage produit) restent deux
 * préoccupations distinctes, chacune avec son propre consommateur unique.
 */
interface ComplianceAnalyticsReaderInterface
{
    /**
     * Résumé (US-ANALYTICS-001) : toutes les ComplianceAnalysis COMPLETED de toute
     * l'historique de la plateforme, tous tenants confondus - jamais restreint à la dernière
     * analyse par facture (patron App\Compliance\Engine\Service\DashboardAggregator, Phase 9,
     * hors de propos ici : ce résumé mesure l'usage cumulé réel du produit, pas l'état
     * courant du parc de factures). `completed`/`conforme` excluent tous deux les analyses
     * FAILED - ne jamais les ajouter au dénominateur du taux de conformité.
     *
     * @return array{completed: int, conforme: int}
     */
    public function getCompletedAndConformeCounts(): array;

    /**
     * Tendances (US-ANALYTICS-002) : les ComplianceAnalysis COMPLETED dont triggeredAt tombe
     * dans la fenêtre demandée, un point par jour d'après leur date de déclenchement -
     * sémantique volontairement différente de getCompletedAndConformeCounts() ci-dessus
     * (cumul historique vs activité quotidienne) ; ne jamais fusionner les deux méthodes ni
     * appliquer ici la logique "dernière analyse par facture" de DashboardAggregator.
     *
     * @return list<array{triggeredAt: \DateTimeImmutable, globalResult: ?ComplianceResult}>
     */
    public function getCompletedAnalysesSince(\DateTimeImmutable $since): array;
}
