<?php

declare(strict_types=1);

namespace App\Compliance\Service;

use App\Compliance\Rules\Entity\RuleVersion;
use App\Organization\Enum\CompanySizeCategory;

/**
 * Résultat de CompanySizeCategoryResolver::resolve() : porte la RuleVersion effectivement
 * utilisée, pour que EligibilityDiagnosticCalculator référence exactement la même version
 * dans l'EligibilityDiagnostic plutôt que de la recharger indépendamment (voir plan Phase 3).
 */
final readonly class CompanySizeCategoryResolution
{
    public function __construct(
        public CompanySizeCategory $category,
        public RuleVersion $ruleVersion,
    ) {
    }
}
