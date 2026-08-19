<?php

declare(strict_types=1);

namespace App\Compliance\Engine\RuleCheck;

/**
 * Retour d'un RuleCheckerInterface::check() : l'outcome (SATISFIED/VIOLATED/DATA_MISSING)
 * accompagné des métadonnées propres à CE contrôle (related_field, observed_value,
 * docs/07-data-model.md section 18) — chaque règle sait quel champ elle vérifie et quelle
 * valeur elle a observée, App\Compliance\Engine\Service\ComplianceRuleEvaluator n'a pas à
 * le deviner depuis l'extérieur.
 */
final class RuleCheckResult
{
    public function __construct(
        public readonly RuleCheckOutcome $outcome,
        public readonly ?string $relatedField = null,
        public readonly ?string $observedValue = null,
    ) {
    }
}
