<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\PlatformAdmin\Entity\PlatformAdministrator;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Résout le PlatformAdministrator authentifié pour la requête courante - miroir de
 * App\Shared\Security\CurrentMembershipResolver côté tenant. Lit l'attribut posé par
 * App\Shared\Security\PlatformAdminAuthenticationListener juste après authentification sur le
 * firewall `platform_admin` - jamais recalculé indépendamment ici.
 */
final class CurrentPlatformAdministratorResolver
{
    public const string ATTRIBUTE = 'current_platform_administrator';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getPlatformAdministrator(): PlatformAdministrator
    {
        $value = $this->requestStack->getCurrentRequest()?->attributes->get(self::ATTRIBUTE);

        if (!$value instanceof PlatformAdministrator) {
            // Réutilise cette exception (plutôt qu'en créer une nouvelle) : même sémantique -
            // violation d'invariant interne, jamais un état atteignable par un chemin
            // applicatif normal une fois PlatformAdminAuthenticationListener en place.
            throw new AuthenticatedIdentityWithoutOrganizationException('No PlatformAdministrator resolved for the current request.');
        }

        return $value;
    }
}
