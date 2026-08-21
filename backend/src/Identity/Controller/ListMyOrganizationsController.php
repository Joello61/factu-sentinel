<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /auth/me/organizations (docs/08-api-specification.md, section 9, plan Phase 14) -
 * nécessaire pour construire l'écran de sélection d'organisation avant/après
 * POST /auth/select-organization. Lit `$user->getMemberships()` déjà chargé par le firewall
 * JWT stateless (rechargé à chaque requête, docs/12-roadmap.md bilan Phase 13) - jamais une
 * requête séparée.
 */
final class ListMyOrganizationsController
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    #[Route('/api/v1/auth/me/organizations', name: 'auth_me_organizations', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $data = [];
        foreach ($user->getMemberships() as $membership) {
            $data[] = [
                'organization_id' => $membership->getOrganizationId()->toRfc4122(),
                'legal_name' => $membership->getOrganization()->getLegalName(),
                'role' => $membership->getRole()->value,
            ];
        }

        return new JsonResponse(['data' => $data]);
    }
}
