<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * App\Shared\Security\CurrentPlatformAdministratorResolver::getPlatformAdministrator()
 * appelé en dehors d'une requête authentifiée par App\Shared\Security\
 * PlatformAdminAuthenticationListener - violation d'invariant interne (jamais un état
 * atteignable par un chemin applicatif normal, tout endpoint /platform-admin/* exige déjà
 * IS_AUTHENTICATED_FULLY sur le firewall platform_admin), traitée comme une erreur 500
 * catégorisée (App\Shared\Http\ApiExceptionListener), jamais comme un 401/403.
 */
final class PlatformAdministratorNotResolvedException extends \RuntimeException
{
}
