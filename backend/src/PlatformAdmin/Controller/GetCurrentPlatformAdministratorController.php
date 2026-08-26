<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\Shared\Security\CurrentPlatformAdministratorResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /platform-admin/me - non documenté dans docs/08-api-specification.md section 38.2
 * (écart comblé à l'implémentation, même patron que les gaps fermés en revue de complétude
 * Phase 14, docs/12-roadmap.md) : nécessaire à la restauration de session côté frontend
 * (route isolée (platform-admin), plan Phase 15) - même rôle que GET /users/current côté
 * tenant, sans lequel le frontend ne peut jamais savoir si le cookie de refresh présent
 * correspond encore à une session valide, ni afficher l'identité de l'administrateur
 * connecté. Pas de permission dédiée : accessible à tout PlatformAdministrator authentifié
 * (ROLE_PLATFORM_ADMIN déjà exigé par l'access_control du firewall), aucune donnée
 * cross-tenant exposée ici.
 */
final class GetCurrentPlatformAdministratorController
{
    public function __construct(
        private readonly CurrentPlatformAdministratorResolver $currentPlatformAdministratorResolver,
    ) {
    }

    #[Route('/api/v1/platform-admin/me', name: 'platform_admin_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $administrator = $this->currentPlatformAdministratorResolver->getPlatformAdministrator();

        return new JsonResponse(['data' => [
            'email' => $administrator->getEmail(),
            'created_at' => $administrator->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]]);
    }
}
