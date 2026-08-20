<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * L'accès à l'assistant IA (docs/06-technical-architecture.md, section 19 ;
 * docs/08-api-specification.md, section 7) exige un email vérifié. Traité comme un 403
 * catégorisé (App\Shared\Http\ApiExceptionListener), jamais un 401 (l'utilisateur est bien
 * authentifié) ni un 404 (la ressource existe, l'accès est simplement conditionné par
 * l'état du compte).
 */
final class EmailVerificationRequiredException extends \RuntimeException
{
}
