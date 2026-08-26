<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\PlatformAdmin\Service\PlatformAnalyticsAggregator;
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
 * GET /platform-admin/analytics/summary (docs/08-api-specification.md, section 38.3 ;
 * US-ANALYTICS-001). Lecture cross-tenant explicite - journalisée sans exception
 * (docs/10-security-privacy.md, section 17 bis), même patron que
 * App\PlatformAdmin\Controller\ListOrganizationsController.
 */
final class GetPlatformAnalyticsSummaryController
{
    public function __construct(
        private readonly PlatformAnalyticsAggregator $platformAnalyticsAggregator,
        private readonly AuditLogger $auditLogger,
        private readonly CurrentPlatformAdministratorResolver $currentPlatformAdministratorResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/platform-admin/analytics/summary', name: 'platform_admin_analytics_summary', methods: ['GET'])]
    #[IsGranted(PlatformAdminPermissionVoter::ANALYTICS_READ)]
    public function __invoke(): JsonResponse
    {
        $summary = $this->platformAnalyticsAggregator->summarize();

        $administrator = $this->currentPlatformAdministratorResolver->getPlatformAdministrator();
        $this->auditLogger->record(
            organizationId: null,
            actorType: ActorType::PLATFORM_ADMIN,
            actorId: $administrator->getId(),
            eventType: EventType::PLATFORM_ANALYTICS_VIEWED,
            entityType: 'PlatformAnalytics',
            entityId: 'summary',
        );
        $this->entityManager->flush();

        return new JsonResponse(['data' => $summary]);
    }
}
