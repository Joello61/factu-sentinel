<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Controller;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Http\DashboardView;
use App\Compliance\Engine\Repository\ComplianceAnalysisRepository;
use App\Compliance\Engine\Repository\ComplianceFindingRepository;
use App\Compliance\Engine\Service\DashboardAggregator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /dashboard (docs/08-api-specification.md, section 33 ; US-DASHBOARD-001). Lecture
 * seule, ne crée ni ne modifie aucune entité. Pas de vérification email requise (comme
 * GET /invoices, GET /compliance-analyses/{id}) : réservée aux deux endpoints IA de la
 * Phase 8 (backend/CLAUDE.md, section 8/11).
 */
final class GetDashboardController
{
    public function __construct(
        private readonly ComplianceAnalysisRepository $complianceAnalysisRepository,
        private readonly ComplianceFindingRepository $complianceFindingRepository,
        private readonly DashboardAggregator $dashboardAggregator,
    ) {
    }

    #[Route('/api/v1/dashboard', name: 'dashboard_get', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $latestAnalyses = $this->complianceAnalysisRepository->findLatestCompletedPerInvoice();

        $analysisIds = array_map(
            static fn (ComplianceAnalysis $analysis) => $analysis->getId(),
            $latestAnalyses,
        );
        $findings = $this->complianceFindingRepository->findByAnalyses($analysisIds);

        $snapshot = $this->dashboardAggregator->aggregate($latestAnalyses, $findings);

        return new JsonResponse(['data' => DashboardView::fromSnapshot($snapshot)]);
    }
}
