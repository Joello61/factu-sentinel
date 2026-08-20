<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Http;

use App\Compliance\Engine\Entity\ComplianceFinding;

/**
 * docs/08-api-specification.md, section 48 : chaque finding expose le bloc `rule`
 * (id, version, source_reference, confidence_level, effective_from, effective_until)
 * permettant de tracer la règle et sa version exacte appliquées -- jamais uniquement un
 * résultat brut (BR-COMPLIANCE-002). effective_from/effective_until sont nécessaires au
 * niveau 3 du Compliance Finding UI (docs/11-frontend-design-system.md, section 29 :
 * "quand la règle s'applique").
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
                // Toujours présente, y compris quand null (une RuleVersion encore active
                // n'a pas de date de fin) : la clé ne doit jamais être omise côté JSON.
                'effective_from' => $ruleVersion->getEffectiveFrom()->format('Y-m-d'),
                'effective_until' => $ruleVersion->getEffectiveUntil()?->format('Y-m-d'),
            ],
        ];
    }
}
