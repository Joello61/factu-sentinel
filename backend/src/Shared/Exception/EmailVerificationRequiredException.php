<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * Levée par App\Shared\Security\EmailVerificationGuard pour toute fonctionnalité sensible
 * (upload, analyses persistantes, usage de l'IA - docs/10-security-privacy.md, section 12)
 * exigeant un email vérifié. Traité comme un 403 catégorisé
 * (App\Shared\Http\ApiExceptionListener), jamais un 401 (l'utilisateur est bien
 * authentifié) ni un 404 (la ressource existe, l'accès est simplement conditionné par
 * l'état du compte).
 */
final class EmailVerificationRequiredException extends \RuntimeException
{
}
