<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\Identity\Entity\Membership;
use App\Identity\Repository\MembershipRepository;
use App\Organization\Repository\OrganizationRepository;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Security\CurrentPlatformAdministratorResolver;
use App\Shared\Security\PlatformAdminPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/** GET /platform-admin/organizations/{id} (docs/08-api-specification.md, section 38.2 ; US-PLATFORMADMIN-001). */
final class GetOrganizationController
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly AuditLogger $auditLogger,
        private readonly CurrentPlatformAdministratorResolver $currentPlatformAdministratorResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/platform-admin/organizations/{id}', name: 'platform_admin_organizations_get', methods: ['GET'])]
    #[IsGranted(PlatformAdminPermissionVoter::ORGANIZATIONS_READ)]
    public function __invoke(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Cette organisation n\'existe pas.');
        }

        $organization = $this->organizationRepository->find(Uuid::fromString($id));
        if (null === $organization) {
            throw new NotFoundHttpException('Cette organisation n\'existe pas.');
        }

        $members = $this->membershipRepository->findAllForOrganization($organization->getId());

        $administrator = $this->currentPlatformAdministratorResolver->getPlatformAdministrator();
        $this->auditLogger->record(
            organizationId: null,
            actorType: ActorType::PLATFORM_ADMIN,
            actorId: $administrator->getId(),
            eventType: EventType::PLATFORM_ORGANIZATIONS_VIEWED,
            entityType: 'Organization',
            entityId: $organization->getId()->toRfc4122(),
        );
        $this->entityManager->flush();

        return new JsonResponse(['data' => [
            ...ListOrganizationsController::toListView($organization),
            'members' => array_map(self::toMemberView(...), $members),
        ]]);
    }

    /** @return array<string, mixed> */
    private static function toMemberView(Membership $membership): array
    {
        return [
            'user_id' => $membership->getUser()->getId()->toRfc4122(),
            'email' => $membership->getUser()->getEmail(),
            'role' => $membership->getRole()->value,
        ];
    }
}
