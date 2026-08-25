<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\Organization\Repository\OrganizationRepository;
use App\PlatformAdmin\Service\SuspendOrganizationService;
use App\Shared\Security\CurrentPlatformAdministratorResolver;
use App\Shared\Security\PlatformAdminPermissionVoter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/** POST /platform-admin/organizations/{id}/reactivate (US-PLATFORMADMIN-002). */
final class ReactivateOrganizationController
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        private readonly SuspendOrganizationService $suspendOrganizationService,
        private readonly CurrentPlatformAdministratorResolver $currentPlatformAdministratorResolver,
    ) {
    }

    #[Route('/api/v1/platform-admin/organizations/{id}/reactivate', name: 'platform_admin_organizations_reactivate', methods: ['POST'])]
    #[IsGranted(PlatformAdminPermissionVoter::ORGANIZATIONS_SUSPEND)]
    public function __invoke(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Cette organisation n\'existe pas.');
        }

        $organization = $this->organizationRepository->find(Uuid::fromString($id));
        if (null === $organization) {
            throw new NotFoundHttpException('Cette organisation n\'existe pas.');
        }

        $this->suspendOrganizationService->reactivate(
            $organization,
            $this->currentPlatformAdministratorResolver->getPlatformAdministrator(),
        );

        return new JsonResponse(['data' => ListOrganizationsController::toListView($organization)]);
    }
}
