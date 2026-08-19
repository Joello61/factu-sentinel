<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Http;

use App\Compliance\Engine\Entity\ComplianceFinding;

/**
 * docs/08-api-specification.md, section 48 : chaque finding expose le bloc `rule`
 * (id, version, source_reference, confidence_level) permettant de tracer la règle et sa
 * version exacte appliquées -- jamais uniquement un résultat brut (BR-COMPLIANCE-002).
 */
final class ComplianceFindingView
{
    /** @return array<string, mixed> */
    public static function fromEntity(ComplianceFinding $finding): array
    {
        $ruleVersion = $finding->getRuleVersion();

        return [
            'id' => $finding->getId()->toRfc4122(),
            'result' => $finding->getResult()->value,
            'message' => $finding->getMessage(),
            'related_field' => $finding->getRelatedField(),
            'observed_value' => $finding->getObservedValue(),
            'correction_action' => $finding->getCorrectionAction(),
            'rule' => [
                'id' => $ruleVersion->getRule()->getId(),
                'version' => $ruleVersion->getVersionNumber(),
                'source_reference' => $ruleVersion->getSourceReference(),
                'confidence_level' => $ruleVersion->getConfidenceLevel()->value,
            ],
        ];
    }
}
