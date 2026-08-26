<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\PlatformAdmin\Service\PlatformHealthAggregator;
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
 * GET /platform-admin/health (docs/08-api-specification.md, section 38.2 ; US-PLATFORMADMIN-005).
 *
 * Correctif Phase 16 : cet endpoint relit des indicateurs cross-tenant (taux d'échec du
 * Compliance Engine, volume/coût IA agrégés sur toute la plateforme) sans jamais avoir été
 * audité depuis la Phase 15 - écart découvert en préparant les endpoints Analytics, en
 * violation de docs/10-security-privacy.md section 17 bis ("chaque lecture ou écriture
 * cross-tenant est journalisée, sans exception"). Même patron d'audit que
 * App\PlatformAdmin\Controller\ListOrganizationsController, événement dédié
 * PLATFORM_HEALTH_VIEWED (jamais une réutilisation de PLATFORM_AUDIT_TRAIL_VIEWED, réservé à
 * la consultation de l'audit trail lui-même).
 */
final class GetPlatformHealthController
{
    public function __construct(
        private readonly PlatformHealthAggregator $platformHealthAggregator,
        private readonly AuditLogger $auditLogger,
        private readonly CurrentPlatformAdministratorResolver $currentPlatformAdministratorResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/platform-admin/health', name: 'platform_admin_health', methods: ['GET'])]
    #[IsGranted(PlatformAdminPermissionVoter::HEALTH_READ)]
    public function __invoke(): JsonResponse
    {
        $health = $this->platformHealthAggregator->aggregate();

        $administrator = $this->currentPlatformAdministratorResolver->getPlatformAdministrator();
        $this->auditLogger->record(
            organizationId: null,
            actorType: ActorType::PLATFORM_ADMIN,
            actorId: $administrator->getId(),
            eventType: EventType::PLATFORM_HEALTH_VIEWED,
            entityType: 'PlatformHealth',
            entityId: 'summary',
        );
        $this->entityManager->flush();

        return new JsonResponse(['data' => $health]);
    }
}
