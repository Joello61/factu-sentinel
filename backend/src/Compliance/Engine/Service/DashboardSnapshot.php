<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Enum\DashboardGlobalStatus;

/**
 * Résultat de App\Compliance\Engine\Service\DashboardAggregator::aggregate() (même rôle que
 * App\Compliance\Engine\Service\ComplianceFindingDraft pour le Compliance Engine) : porté
 * ensuite par App\Compliance\Engine\Http\DashboardView vers le format JSON de
 * GET /dashboard (docs/08-api-specification.md, section 33).
 */
final class DashboardSnapshot
{
    /**
     * @param list<ComplianceAnalysis>        $recentAnalyses
     * @param list<DashboardRecommendedAction> $recommendedActions
     */
    public function __construct(
        public readonly DashboardGlobalStatus $globalStatus,
        public readonly int $openIssuesCount,
        public readonly int $warningsCount,
        public readonly array $recentAnalyses,
        public readonly array $recommendedActions,
    ) {
    }
}
