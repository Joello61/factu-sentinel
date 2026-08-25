<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\Organization\Entity\Organization;
use App\Organization\Repository\OrganizationRepository;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Security\CurrentPlatformAdministratorResolver;
use App\Shared\Security\PlatformAdminPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET /platform-admin/organizations (docs/08-api-specification.md, section 38.2 ;
 * US-PLATFORMADMIN-001). Lecture cross-tenant explicite - `Organization` n'est jamais
 * elle-même tenant-scoped (section 25 data-model), donc aucun filtre à suspendre.
 *
 * Chaque consultation est journalisée (docs/10-security-privacy.md, section 17 bis :
 * "chaque lecture ou écriture cross-tenant est journalisée, sans exception" - pas seulement
 * les écritures).
 */
final class ListOrganizationsController
{
    private const int DEFAULT_PER_PAGE = 20;
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        private readonly AuditLogger $auditLogger,
        private readonly CurrentPlatformAdministratorResolver $currentPlatformAdministratorResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/platform-admin/organizations', name: 'platform_admin_organizations_list', methods: ['GET'])]
    #[IsGranted(PlatformAdminPermissionVoter::ORGANIZATIONS_READ)]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('per_page', self::DEFAULT_PER_PAGE)));
        $suspendedParam = $request->query->get('suspended');
        $suspended = null === $suspendedParam ? null : filter_var($suspendedParam, \FILTER_VALIDATE_BOOLEAN);

        $result = $this->organizationRepository->paginate($suspended, $page, $perPage);

        $administrator = $this->currentPlatformAdministratorResolver->getPlatformAdministrator();
        $this->auditLogger->record(
            organizationId: null,
            actorType: ActorType::PLATFORM_ADMIN,
            actorId: $administrator->getId(),
            eventType: EventType::PLATFORM_ORGANIZATIONS_VIEWED,
            entityType: 'Organization',
            entityId: 'list',
        );
        $this->entityManager->flush();

        return new JsonResponse([
            'data' => array_map(self::toListView(...), $result['items']),
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

    /** @return array<string, mixed> */
    public static function toListView(Organization $organization): array
    {
        return [
            'id' => $organization->getId()->toRfc4122(),
            'legal_name' => $organization->getLegalName(),
            'siren' => $organization->getSiren(),
            'country' => $organization->getCountry(),
            'created_at' => $organization->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'suspended_at' => $organization->getSuspendedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
