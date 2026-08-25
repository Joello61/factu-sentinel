<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\PlatformAdmin\Http\PlatformAuditLogEntryView;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Audit\Repository\AuditLogEntryRepository;
use App\Shared\Security\CurrentPlatformAdministratorResolver;
use App\Shared\Security\PlatformAdminPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * GET /platform-admin/audit-events (docs/08-api-specification.md, section 38.2 ;
 * US-PLATFORMADMIN-003). Jamais mélangé avec GET /audit-events tenant (contrôleur et route
 * distincts, App\Shared\Audit\Controller\ListAuditEventsController) - cette consultation
 * elle-même est journalisée (section 17 bis : "chaque lecture ou écriture cross-tenant est
 * journalisée, sans exception").
 */
final class ListPlatformAuditEventsController
{
    private const int DEFAULT_PER_PAGE = 20;
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private readonly AuditLogEntryRepository $auditLogEntryRepository,
        private readonly AuditLogger $auditLogger,
        private readonly CurrentPlatformAdministratorResolver $currentPlatformAdministratorResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/platform-admin/audit-events', name: 'platform_admin_audit_events_list', methods: ['GET'])]
    #[IsGranted(PlatformAdminPermissionVoter::AUDIT_READ)]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('per_page', self::DEFAULT_PER_PAGE)));

        $organizationIdParam = $request->query->get('organization_id');
        $organizationId = is_string($organizationIdParam) && Uuid::isValid($organizationIdParam)
            ? Uuid::fromString($organizationIdParam)
            : null;
        $entityType = $request->query->get('entity_type');
        $since = self::parseTimestamp($request->query->get('since'));
        $until = self::parseTimestamp($request->query->get('until'));

        $result = $this->auditLogEntryRepository->paginateAll(
            $organizationId,
            \is_string($entityType) ? $entityType : null,
            $since,
            $until,
            $page,
            $perPage,
        );

        $administrator = $this->currentPlatformAdministratorResolver->getPlatformAdministrator();
        $this->auditLogger->record(
            organizationId: null,
            actorType: ActorType::PLATFORM_ADMIN,
            actorId: $administrator->getId(),
            eventType: EventType::PLATFORM_AUDIT_TRAIL_VIEWED,
            entityType: 'AuditLogEntry',
            entityId: 'list',
        );
        $this->entityManager->flush();

        return new JsonResponse([
            'data' => array_map(PlatformAuditLogEntryView::fromEntity(...), $result['items']),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_count' => $result['totalCount'],
                    'total_pages' => 0 === $result['totalCount'] ? 0 : (int) ceil($result['totalCount'] / $perPage),
                ],
            ],
        ]);
    }

    private static function parseTimestamp(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);

        return false !== $date ? $date : null;
    }
}
