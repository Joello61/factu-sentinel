<?php

declare(strict_types=1);

namespace App\Compliance\Engine\RuleCheck;

/**
 * Résultat interne d'un RuleChecker, jamais exposé en API (App\Compliance\Engine\Service\
 * ComplianceRuleEvaluator le traduit en ComplianceResult). Distingue explicitement
 * DATA_MISSING (une donnée nécessaire à LA vérification est absente, BR-COMPLIANCE-003)
 * de VIOLATED (la vérification a pu être menée et la règle n'est pas respectée) : ce ne
 * sont jamais la même chose, voir App\Compliance\Engine\RuleCheck\SirenMentionRuleChecker
 * pour un cas où l'absence de donnée EST la violation elle-même, pas une DATA_MISSING.
 */
enum RuleCheckOutcome
{
    case SATISFIED;
    case VIOLATED;
    case DATA_MISSING;
}
