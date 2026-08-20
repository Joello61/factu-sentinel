<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Http;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Service\DashboardRecommendedAction;
use App\Compliance\Engine\Service\DashboardSnapshot;

/**
 * GET /dashboard (docs/08-api-specification.md, section 33) : agrège des données déjà
 * exposées ailleurs (compliance-analyses, compliance-findings) sous une forme pré-calculée,
 * jamais une extraction brute de la base.
 */
final class DashboardView
{
    /**
     * @return array<string, mixed>
     */
    public static function fromSnapshot(DashboardSnapshot $snapshot): array
    {
        return [
            'global_status' => $snapshot->globalStatus->value,
            'open_issues_count' => $snapshot->openIssuesCount,
            'warnings_count' => $snapshot->warningsCount,
            'recent_analyses' => array_map(
                static fn (ComplianceAnalysis $analysis): array => [
                    'id' => $analysis->getId()->toRfc4122(),
                    'invoice_id' => $analysis->getInvoice()->getId()->toRfc4122(),
                    'global_result' => $analysis->getGlobalResult()?->value,
                    'triggered_at' => $analysis->getTriggeredAt()->format(\DateTimeInterface::ATOM),
                ],
                $snapshot->recentAnalyses,
            ),
            'recommended_actions' => array_map(
                static fn (DashboardRecommendedAction $action): array => [
                    'message' => $action->message,
                    'related_analysis_id' => $action->relatedAnalysis->getId()->toRfc4122(),
                ],
                $snapshot->recommendedActions,
            ),
        ];
    }
}
