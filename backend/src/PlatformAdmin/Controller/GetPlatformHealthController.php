<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\PlatformAdmin\Service\PlatformHealthAggregator;
use App\Shared\Security\PlatformAdminPermissionVoter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** GET /platform-admin/health (docs/08-api-specification.md, section 38.2 ; US-PLATFORMADMIN-005). */
final class GetPlatformHealthController
{
    public function __construct(
        private readonly PlatformHealthAggregator $platformHealthAggregator,
    ) {
    }

    #[Route('/api/v1/platform-admin/health', name: 'platform_admin_health', methods: ['GET'])]
    #[IsGranted(PlatformAdminPermissionVoter::HEALTH_READ)]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['data' => $this->platformHealthAggregator->aggregate()]);
    }
}
