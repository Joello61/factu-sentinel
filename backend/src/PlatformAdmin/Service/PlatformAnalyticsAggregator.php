<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Service;

use App\Compliance\Engine\Service\ComplianceAnalyticsReaderInterface;
use App\Identity\Repository\UserRepository;
use App\Organization\Repository\OrganizationRepository;

/**
 * GET /platform-admin/analytics/summary (docs/08-api-specification.md, section 38.3 ;
 * US-ANALYTICS-001). N'agrège jamais directement les tables internes d'un autre module
 * (backend/CLAUDE.md, section 3) - consomme exclusivement OrganizationRepository/UserRepository
 * (entités globales, jamais tenant-scoped - même précédent que App\PlatformAdmin\Controller\
 * ListOrganizationsController) et ComplianceAnalyticsReaderInterface (entité tenant-scoped,
 * lecture cross-tenant explicite - même patron que App\PlatformAdmin\Service\
 * PlatformHealthAggregator).
 *
 * Résumé cumulé sur toute l'historique de la plateforme, jamais restreint à une fenêtre
 * temporelle (contrairement à App\PlatformAdmin\Service\PlatformAnalyticsTrendAggregator,
 * qui mesure l'activité par jour - les deux sémantiques sont volontairement distinctes,
 * jamais interchangeables).
 */
final readonly class PlatformAnalyticsAggregator
{
    public function __construct(
        private OrganizationRepository $organizationRepository,
        private UserRepository $userRepository,
        private ComplianceAnalyticsReaderInterface $complianceAnalyticsReader,
    ) {
    }

    /** @return array<string, mixed> */
    public function summarize(): array
    {
        $counts = $this->complianceAnalyticsReader->getCompletedAndConformeCounts();

        return [
            'organizations_count' => $this->organizationRepository->countAll(),
            'users_count' => $this->userRepository->countAll(),
            'compliance_analyses_count' => $counts['completed'],
            'compliance_rate' => self::complianceRate($counts['completed'], $counts['conforme']),
        ];
    }

    /**
     * Même convention que App\Compliance\Engine\Service\ComplianceHealthReader::
     * getFailureRateLast24Hours() : chaîne décimale arrondie à 4 décimales (../../CLAUDE.md
     * section 11, montants/ratios jamais en flottant JSON), "0" si aucune analyse COMPLETED -
     * jamais une division par zéro.
     */
    public static function complianceRate(int $completed, int $conforme): string
    {
        if (0 === $completed) {
            return '0';
        }

        return (string) round($conforme / $completed, 4);
    }
}
