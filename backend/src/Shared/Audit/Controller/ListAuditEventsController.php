<?php

declare(strict_types=1);

namespace App\Shared\Audit\Controller;

use App\Shared\Audit\Http\AuditLogEntryView;
use App\Shared\Audit\Repository\AuditLogEntryRepository;
use App\Shared\Security\CurrentOrganizationResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /audit-events (docs/08-api-specification.md, section 39 ; US-HISTORY-001,
 * partiellement). Écart d'implémentation fermé en Phase 10 (docs/12-roadmap.md) :
 * App\Shared\Audit\AuditLogger et AuditLogEntry existaient déjà depuis la Phase 3, jamais
 * exposés en lecture jusqu'ici.
 *
 * Tenant résolu via CurrentOrganizationResolver, jamais via un paramètre de requête
 * (docs/08-api-specification.md, section 9) - AuditLogEntry n'implémentant pas
 * TenantScopedInterface, ce filtrage est manuel dans
 * App\Shared\Audit\Repository\AuditLogEntryRepository::paginateForOrganization, pas
 * automatique via TenantFilter. Les événements globaux (organization_id NULL, par exemple
 * un futur rule_version_created) ne sont jamais exposés ici (section 39 : réservés à l'API
 * d'administration, hors périmètre de cet endpoint utilisateur).
 */
final class ListAuditEventsController
{
    private const int DEFAULT_PER_PAGE = 20;
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly AuditLogEntryRepository $auditLogEntryRepository,
    ) {
    }

    #[Route('/api/v1/audit-events', name: 'audit_events_list', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('per_page', self::DEFAULT_PER_PAGE)));

        $entityType = $request->query->get('entity_type');
        $entityId = $request->query->get('entity_id');
        $since = self::parseTimestamp($request->query->get('since'));
        $until = self::parseTimestamp($request->query->get('until'));

        $result = $this->auditLogEntryRepository->paginateForOrganization(
            $this->currentOrganizationResolver->getOrganizationId(),
            \is_string($entityType) ? $entityType : null,
            \is_string($entityId) ? $entityId : null,
            $since,
            $until,
            $page,
            $perPage,
        );

        return new JsonResponse([
            'data' => array_map(AuditLogEntryView::fromEntity(...), $result['items']),
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

    /**
     * since/until : horodatages ISO 8601 UTC (../../../../../CLAUDE.md, section 11),
     * silencieusement ignorés si absents ou mal formés - même tolérance que le filtre "from"/
     * "to" de App\Compliance\Engine\Controller\ListComplianceAnalysisHistoryController sur un
     * filtre optionnel.
     */
    private static function parseTimestamp(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);

        return false !== $date ? $date : null;
    }
}
