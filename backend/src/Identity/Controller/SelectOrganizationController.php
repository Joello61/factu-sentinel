<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Http\SelectOrganizationRequest;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * POST /auth/select-organization (docs/08-api-specification.md, section 9, plan Phase 14).
 *
 * Vérifie ici, avant toute émission de jeton, que l'appelant a réellement un Membership actif
 * sur l'organisation demandée - jamais un JWT émis "à l'aveugle" pour une organisation dont
 * l'appartenance n'a pas été vérifiée (même principe que App\Shared\Security\
 * JwtOrganizationClaimListener pour le refresh : un claim `org` n'est jamais une preuve en
 * lui-même, il doit toujours découler d'une vérification explicite).
 *
 * Réutilise `AuthenticationSuccessHandler::handleAuthenticationSuccess()` (jamais une
 * construction manuelle de la réponse) : c'est ce qui déclenche la rotation du refresh token
 * existant (`gesdinet_jwt_refresh_token`, single_use) sur cette même requête - condition
 * nécessaire pour que App\Shared\Security\PropagateOrganizationToRefreshTokenListener
 * reporte la nouvelle organisation sur le refresh token, et donc que ce choix survive à un
 * rafraîchissement ultérieur de l'access token (plan Phase 14, revue utilisateur du
 * 21/08/2026).
 */
final class SelectOrganizationController
{
    public function __construct(
        private readonly Security $security,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
    ) {
    }

    #[Route('/api/v1/auth/select-organization', name: 'auth_select_organization', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] SelectOrganizationRequest $payload): Response
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $requestedOrganizationId = Uuid::fromString($payload->organizationId);

        $hasMembership = false;
        foreach ($user->getMemberships() as $membership) {
            if ($membership->getOrganizationId()->equals($requestedOrganizationId)) {
                $hasMembership = true;
                break;
            }
        }

        if (!$hasMembership) {
            // Jamais 404 ici : l'appelant connaît déjà l'id de cette organisation via
            // GET /auth/me/organizations, ce n'est pas une information à cacher
            // (docs/08-api-specification.md, section 9).
            throw new AccessDeniedHttpException('Vous n\'êtes pas membre de cette organisation.');
        }

        $jwt = $this->jwtManager->createFromPayload($user, ['org' => $requestedOrganizationId->toRfc4122()]);

        return $this->authenticationSuccessHandler->handleAuthenticationSuccess($user, $jwt);
    }
}
