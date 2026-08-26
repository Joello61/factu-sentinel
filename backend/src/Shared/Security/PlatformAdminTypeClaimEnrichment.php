<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\PlatformAdmin\Entity\PlatformAdministrator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\PayloadEnrichmentInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Embarque le claim obligatoire `typ: platform_admin` sur **tout** jeton émis par
 * `platform_admin_jwt.manager` (backend/config/services.yaml) - à l'émission initiale
 * (App\PlatformAdmin\Controller\ConfirmPlatformAdminMfaController) comme à chaque rotation de
 * refresh token (Gesdinet\JWTRefreshTokenBundle, qui appelle JWTManager::create() sans passer
 * par ce contrôleur) : injecté au niveau de l'enrichment plutôt qu'au seul point d'émission
 * initiale, pour qu'aucun chemin ne puisse produire un jeton `platform_admin` sans ce claim.
 *
 * Câblé exclusivement sur l'instance d'enrichment propre à `platform_admin_jwt.manager` -
 * jamais sur la chaîne globale `lexik_jwt_authentication.payload_enrichment`, qui reste
 * réservée aux jetons `App\Identity\Entity\User` (ADR-009 : deux émetteurs de jeton
 * structurellement séparés, jamais un claim supplémentaire sur un jeton par ailleurs
 * identique).
 *
 * Vérifié en contrepartie par App\Shared\Security\PlatformAdminAuthenticationListener
 * (lexik_jwt_authentication.on_jwt_authenticated) - défense en profondeur si un jeton
 * atteignait un jour ce firewall sans être passé par ce manager.
 */
final class PlatformAdminTypeClaimEnrichment implements PayloadEnrichmentInterface
{
    public const string CLAIM = 'typ';
    public const string VALUE = 'platform_admin';

    /**
     * @param array<string, mixed> $payload
     */
    public function enrich(UserInterface $user, array &$payload): void
    {
        if (!$user instanceof PlatformAdministrator) {
            return;
        }

        $payload[self::CLAIM] = self::VALUE;
    }
}
