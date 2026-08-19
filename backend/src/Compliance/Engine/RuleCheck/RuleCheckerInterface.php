<?php

declare(strict_types=1);

namespace App\Compliance\Engine\RuleCheck;

use App\Compliance\Engine\RuleEvaluationContext;

/**
 * Logique de vérification propre à une règle, invoquée uniquement quand
 * App\Compliance\Engine\Service\ComplianceRuleEvaluator a déjà confirmé l'applicabilité et
 * la confiance élevée de la RuleVersion (voir cette classe). Un RuleChecker ne connaît ni
 * Doctrine, ni l'audit, ni la persistance : service pur, comme
 * App\Compliance\Service\EligibilityDiagnosticCalculator.
 */
interface RuleCheckerInterface
{
    public function check(RuleEvaluationContext $context): RuleCheckResult;
}
