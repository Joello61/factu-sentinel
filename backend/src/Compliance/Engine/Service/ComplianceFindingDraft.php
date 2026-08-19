<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Rules\Entity\RuleVersion;

/**
 * Résultat d'une règle avant persistance (même rôle que
 * App\Invoicing\Service\CalculatedInvoiceLine) : App\Compliance\Engine\Service\
 * ComplianceEngine ne peut pas construire de ComplianceFinding directement, celui-ci
 * exigeant une ComplianceAnalysis déjà persistée en constructeur. App\Compliance\Engine\
 * Service\RunComplianceAnalysisService transforme chaque draft en entité une fois la
 * ComplianceAnalysis créée.
 */
final class ComplianceFindingDraft
{
    public function __construct(
        public readonly RuleVersion $ruleVersion,
        public readonly ComplianceResult $result,
        public readonly string $message,
        public readonly ?string $relatedField,
        public readonly ?string $observedValue,
        public readonly ?string $correctionAction,
    ) {
    }
}
