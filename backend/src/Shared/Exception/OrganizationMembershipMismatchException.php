<?php

declare(strict_types=1);

namespace App\Shared\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Le claim `org` d'un JWT valide et correctement signé ne correspond à aucun Membership réel
 * de l'utilisateur authentifié (docs/12-roadmap.md, Phase 14 : "JWT dit organisation B"
 * n'implique jamais automatiquement "l'utilisateur appartient à B"). Cas atteignable en
 * fonctionnement normal - un membre retiré d'une organisation conserve un access token
 * signé et non expiré pendant sa courte durée de vie résiduelle (docs/backend/CLAUDE.md,
 * section 8 : un JWT n'est pas révocable une fois émis) - traité comme un échec
 * d'authentification ordinaire, jamais comme la violation d'invariant interne que représente
 * AuthenticatedIdentityWithoutOrganizationException.
 *
 * Étend AuthenticationException (Symfony) plutôt que \RuntimeException précisément pour
 * emprunter le chemin déjà en place : levée depuis un listener sur
 * lexik_jwt_authentication.on_jwt_authenticated (App\Shared\Security\TenantFilterActivationListener),
 * elle est interceptée par Symfony\Component\Security\Http\Authentication\AuthenticatorManager
 * (catch (AuthenticationException) autour de createToken()), routée vers
 * JWTAuthenticator::onAuthenticationFailure() puis Events::JWT_INVALID, déjà géré par
 * App\Shared\Security\AuthFailureEnvelopeListener - réponse 401 "AUTHENTICATION_FAILED"
 * générique sans code supplémentaire à écrire dans App\Shared\Http\ApiExceptionListener.
 */
final class OrganizationMembershipMismatchException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'Organization membership could not be verified for the presented token.';
    }
}
