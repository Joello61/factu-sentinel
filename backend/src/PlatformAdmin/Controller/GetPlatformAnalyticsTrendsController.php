<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\Compliance\Engine\Service\ComplianceAnalyticsReaderInterface;
use App\Identity\Repository\UserRepository;
use App\Organization\Repository\OrganizationRepository;
use App\PlatformAdmin\Service\PlatformAnalyticsTrendAggregator;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Security\CurrentPlatformAdministratorResolver;
use App\Shared\Security\PlatformAdminPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET /platform-admin/analytics/trends (docs/08-api-specification.md, section 38.3 ;
 * US-ANALYTICS-002). Fenêtre fixe de App\PlatformAdmin\Service\PlatformAnalyticsTrendAggregator::
 * WINDOW_DAYS jours (90, UTC) - pas de `?since`/`?until` au MVP de cette phase. Le contrôleur
 * assemble les listes brutes (entités globales lues directement, ComplianceAnalysis lu via
 * l'interface cross-tenant dédiée) puis délègue tout le calcul de buckets à l'agrégateur pur -
 * même séparation lecture/agrégation que App\Compliance\Engine\Controller\GetDashboardController.
 * Lecture cross-tenant explicite - journalisée sans exception (docs/10-security-privacy.md,
 * section 17 bis).
 */
final class GetPlatformAnalyticsTrendsController
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        private readonly UserRepository $userRepository,
        private readonly ComplianceAnalyticsReaderInterface $complianceAnalyticsReader,
        private readonly PlatformAnalyticsTrendAggregator $platformAnalyticsTrendAggregator,
        private readonly AuditLogger $auditLogger,
        private readonly CurrentPlatformAdministratorResolver $currentPlatformAdministratorResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/platform-admin/analytics/trends', name: 'platform_admin_analytics_trends', methods: ['GET'])]
    #[IsGranted(PlatformAdminPermissionVoter::ANALYTICS_READ)]
    public function __invoke(): JsonResponse
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $windowStart = PlatformAnalyticsTrendAggregator::windowStart($now);
        $windowEnd = PlatformAnalyticsTrendAggregator::windowEnd($now);

        $points = $this->platformAnalyticsTrendAggregator->aggregate(
            $now,
            $this->organizationRepository->findCreatedAtBetween($windowStart, $windowEnd),
            $this->userRepository->findCreatedAtBetween($windowStart, $windowEnd),
            $this->complianceAnalyticsReader->getCompletedAnalysesSince($windowStart),
        );

        $administrator = $this->currentPlatformAdministratorResolver->getPlatformAdministrator();
        $this->auditLogger->record(
            organizationId: null,
            actorType: ActorType::PLATFORM_ADMIN,
            actorId: $administrator->getId(),
            eventType: EventType::PLATFORM_ANALYTICS_VIEWED,
            entityType: 'PlatformAnalytics',
            entityId: 'trends',
        );
        $this->entityManager->flush();

        return new JsonResponse([
            'data' => ['points' => $points],
            'meta' => ['window_days' => PlatformAnalyticsTrendAggregator::WINDOW_DAYS],
        ]);
    }
}
