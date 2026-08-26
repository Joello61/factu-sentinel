<?php

declare(strict_types=1);

namespace App\Shared\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Vérification Origin/Referer sur /auth/refresh, en complément de SameSite=Lax
 * (docs/10-security-privacy.md, section 20) : seul endpoint où un cookie est transmis
 * automatiquement par le navigateur, donc le seul qui reste exposé au CSRF malgré
 * l'authentification par en-tête Authorization pour le reste de l'API.
 *
 * Phase 15 (ADR-009) : /platform-admin/auth/refresh porte exactement la même exposition
 * (cookie platform_admin_refresh_token, SameSite=Lax, backend/config/packages/security.yaml)
 * sur l'identité la plus sensible du produit - écart trouvé et fermé lors de la revue de
 * sécurité manuelle de fin de phase (skill security-review, 12-roadmap.md bilan Phase 15) :
 * ce listener n'avait jamais été étendu au second firewall.
 */
final class RefreshOriginCheckListener
{
    private const array REFRESH_PATHS = [
        '/api/v1/auth/refresh',
        '/api/v1/platform-admin/auth/refresh',
    ];

    public function __construct(
        private readonly string $frontendUrl,
    ) {
    }

    #[AsEventListener(event: 'kernel.request', priority: 10)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!\in_array($request->getPathInfo(), self::REFRESH_PATHS, true)) {
            return;
        }

        $origin = $request->headers->get('Origin') ?? $request->headers->get('Referer');

        if (null === $origin || !str_starts_with($origin, rtrim($this->frontendUrl, '/'))) {
            throw new AccessDeniedHttpException('Invalid or missing Origin for refresh request.');
        }
    }
}
