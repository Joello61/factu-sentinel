<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Service\InvitationTokenResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /invitations/{token} (public, plan Phase 14 - "Décisions à valider" #3). Réponse 404
 * uniforme pour un jeton invalide, inconnu, expiré ou révoqué - jamais de distinction
 * observable depuis cet endpoint non authentifié (éviter toute énumération), la distinction
 * fine n'étant utile qu'après authentification côté
 * App\Identity\Controller\AcceptInvitationController.
 *
 * Limité par IP (`limiter.invitation_token_access`, revue de complétude Phase 14) : seul
 * endpoint de cette phase entièrement public, jamais de clé organization_id disponible ici.
 */
final class GetInvitationPreviewController
{
    public function __construct(
        private readonly InvitationTokenResolver $invitationTokenResolver,
        #[Autowire(service: 'limiter.invitation_token_access')]
        private readonly RateLimiterFactory $rateLimiter,
    ) {
    }

    #[Route('/api/v1/invitations/{token}', name: 'invitations_preview', methods: ['GET'])]
    public function __invoke(string $token, Request $request): JsonResponse
    {
        $limit = $this->rateLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $invitation = $this->invitationTokenResolver->resolve($token);

        if (null === $invitation || !$invitation->isPending()) {
            throw new NotFoundHttpException('Cette invitation n\'existe pas ou n\'est plus disponible.');
        }

        return new JsonResponse(['data' => [
            'organization_name' => $invitation->getOrganization()->getLegalName(),
            'email' => $invitation->getEmail(),
            'role' => $invitation->getRole()->value,
            'expires_at' => $invitation->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ]]);
    }
}
