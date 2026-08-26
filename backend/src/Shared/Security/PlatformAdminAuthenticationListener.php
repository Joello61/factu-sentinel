<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\PlatformAdmin\Entity\PlatformAdministrator;
use App\Shared\Exception\InvalidPlatformAdminTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Miroir de App\Shared\Security\TenantFilterActivationListener, côté firewall
 * `platform_admin` (ADR-009). Écoute le même événement global
 * (`lexik_jwt_authentication.on_jwt_authenticated`, dispatché par le même
 * `EventDispatcherInterface` partagé quel que soit le firewall) mais ne réagit jamais à un
 * jeton `App\Identity\Entity\User` - TenantFilterActivationListener s'en charge exclusivement
 * (voir cette classe pour la réciproque).
 *
 * `tenant_filter` n'est **jamais** activé ici, intentionnellement : ADR-009 exige que ce rôle
 * traverse l'isolation tenant de façon explicite (repositories cross-tenant dédiés,
 * App\PlatformAdmin\Repository\*), jamais via le mécanisme automatique réservé aux entités
 * tenant-scoped.
 */
final class PlatformAdminAuthenticationListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_authenticated')]
    public function onJwtAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $administrator = $event->getToken()->getUser();
        if (!$administrator instanceof PlatformAdministrator) {
            return;
        }

        $claim = $event->getPayload()[PlatformAdminTypeClaimEnrichment::CLAIM] ?? null;
        if (PlatformAdminTypeClaimEnrichment::VALUE !== $claim) {
            throw new InvalidPlatformAdminTokenException('Authenticated token carries no "typ: platform_admin" claim.');
        }

        $this->requestStack->getCurrentRequest()?->attributes->set(
            CurrentPlatformAdministratorResolver::ATTRIBUTE,
            $administrator,
        );
    }
}
