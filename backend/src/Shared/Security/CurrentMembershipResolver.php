<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Identity\Entity\Membership;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Résout le Membership actif (donc le Role, docs/04-product-requirements.md section 21.1)
 * pour la requête HTTP courante - même patron que CurrentOrganizationResolver, dont il est
 * le complément depuis la Phase 14 (DEC-009). Lit l'attribut posé par
 * TenantFilterActivationListener juste après authentification, qui a déjà revalidé que ce
 * Membership existe réellement pour l'utilisateur porté par le token (jamais recalculé
 * indépendamment ici).
 */
final class CurrentMembershipResolver
{
    public const string ATTRIBUTE = 'current_membership';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getMembership(): Membership
    {
        $value = $this->requestStack->getCurrentRequest()?->attributes->get(self::ATTRIBUTE);

        if (!$value instanceof Membership) {
            throw new AuthenticatedIdentityWithoutOrganizationException('No Membership resolved for the current request.');
        }

        return $value;
    }
}
