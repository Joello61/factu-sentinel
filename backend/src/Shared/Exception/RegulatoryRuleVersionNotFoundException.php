<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * Aucune RuleVersion active trouvée pour une RegulatoryRule à une date donnée. Ne devrait
 * jamais se produire après la migration de seed de la Phase 3 (docs/06-technical-architecture.md,
 * section 10 : au moins une version doit toujours être active) : violation d'invariant
 * interne du référentiel réglementaire, jamais une erreur utilisateur : traitée comme un
 * 500 catégorisé par App\Shared\Http\ApiExceptionListener (comportement générique déjà en
 * place pour tout \RuntimeException non listé explicitement).
 */
final class RegulatoryRuleVersionNotFoundException extends \RuntimeException
{
}
