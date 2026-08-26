<?php

declare(strict_types=1);

namespace App\Shared\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Un jeton authentifié sur le firewall `platform_admin` (backend/config/services.yaml,
 * `platform_admin_jwt.authenticator`) ne porte pas le claim obligatoire `typ: platform_admin`
 * (App\Shared\Security\PlatformAdminTypeClaimEnrichment) - ne devrait jamais se produire en
 * fonctionnement normal puisque `platform_admin_jwt.manager` l'embarque systématiquement,
 * mais vérifié explicitement par App\Shared\Security\PlatformAdminAuthenticationListener
 * (défense en profondeur, ADR-009 : jamais une confiance aveugle au seul routage par
 * pattern d'URL).
 */
final class InvalidPlatformAdminTokenException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'Platform administrator token is missing the required claim.';
    }
}
