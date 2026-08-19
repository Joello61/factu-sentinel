<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * Un utilisateur authentifié n'a aucun organization_id résolvable (docs/07-data-model.md :
 * un User a exactement un Membership actif au MVP - décision Phase 2). Ce n'est pas un
 * refus d'autorisation ordinaire mais une violation d'invariant interne : traité comme une
 * erreur 500 catégorisée (App\Shared\Http\ApiExceptionListener), jamais comme un 403, et
 * ne doit jamais laisser passer une requête Doctrine tenant-scoped sans filtre actif.
 */
final class AuthenticatedIdentityWithoutOrganizationException extends \RuntimeException
{
}
