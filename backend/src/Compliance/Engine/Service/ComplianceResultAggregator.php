<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Enum\ComplianceResult;

/**
 * Agrégation du global_result d'une ComplianceAnalysis à partir de ses ComplianceFinding
 * (docs/06-technical-architecture.md, section 9 : "logique d'agrégation du statut global,
 * elle-même une règle explicite, non codée en dur"). Table de précédence nommée plutôt
 * qu'un if/else dispersé : le premier état de PRECEDENCE présent parmi les findings
 * l'emporte, NON_APPLICABLE est toujours ignoré (jamais représentatif d'un problème),
 * l'absence de tout état de PRECEDENCE retombe sur CONFORME.
 */
final class ComplianceResultAggregator
{
    /** @var list<ComplianceResult> */
    private const array PRECEDENCE = [
        ComplianceResult::NON_CONFORME,
        ComplianceResult::INCERTAIN_REGLEMENTAIRE,
        ComplianceResult::A_VERIFIER,
        ComplianceResult::AVERTISSEMENT,
    ];

    /** @param list<ComplianceFindingDraft> $drafts */
    public function aggregate(array $drafts): ComplianceResult
    {
        $results = array_map(static fn (ComplianceFindingDraft $draft): ComplianceResult => $draft->result, $drafts);

        foreach (self::PRECEDENCE as $candidate) {
            if (\in_array($candidate, $results, true)) {
                return $candidate;
            }
        }

        return ComplianceResult::CONFORME;
    }
}
