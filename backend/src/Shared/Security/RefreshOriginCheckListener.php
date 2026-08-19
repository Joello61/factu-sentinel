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
 */
final class RefreshOriginCheckListener
{
    private const string REFRESH_PATH = '/api/v1/auth/refresh';

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
        if (self::REFRESH_PATH !== $request->getPathInfo()) {
            return;
        }

        $origin = $request->headers->get('Origin') ?? $request->headers->get('Referer');

        if (null === $origin || !str_starts_with($origin, rtrim($this->frontendUrl, '/'))) {
            throw new AccessDeniedHttpException('Invalid or missing Origin for refresh request.');
        }
    }
}
