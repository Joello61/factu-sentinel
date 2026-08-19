<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Seul point de résolution du tenant courant pour les controllers/services applicatifs
 * (docs/08-api-specification.md, section 9 : le tenant se résout depuis la session,
 * jamais depuis l'URL ou une donnée fournie par le client).
 *
 * Lit l'attribut de requête posé par TenantFilterActivationListener juste après
 * l'authentification JWT — jamais recalculé indépendamment, pour rester la même valeur
 * que celle utilisée pour activer TenantFilter.
 */
final class CurrentOrganizationResolver
{
    public const string ATTRIBUTE = 'organization_id';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getOrganizationId(): Uuid
    {
        $value = $this->requestStack->getCurrentRequest()?->attributes->get(self::ATTRIBUTE);

        if (!$value instanceof Uuid) {
            throw new AuthenticatedIdentityWithoutOrganizationException('No organization resolved for the current request.');
        }

        return $value;
    }
}
