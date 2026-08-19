<?php

declare(strict_types=1);

namespace App\Compliance\Engine\RuleCheck;

use App\Compliance\Engine\RuleEvaluationContext;

/**
 * Règle mention-categorie-operation (docs/02-regulatory-study.md, section 10 ; scénarios
 * REG-002/REG-003 de docs/09-test-strategy.md, section 9, pour l'applicabilité — voir
 * App\Compliance\Engine\Service\ComplianceRuleEvaluator pour cette étape).
 *
 * Invoice::getOperationType() est un OperationType non-nullable (Types::STRING,
 * enumType: OperationType::class, colonne NOT NULL) : en saisie manuelle, cette mention
 * est donc toujours structurellement présente, jamais absente. Ce checker retourne
 * systématiquement SATISFIED : pas de branche DATA_MISSING ici (elle serait du code mort
 * tant qu'aucune valeur non-typée/absente n'est représentable, ../../CLAUDE.md racine
 * section 16). Le comportement générique App\Compliance\Engine\RuleCheck\RuleCheckOutcome::
 * DATA_MISSING -> A_VERIFIER de ComplianceRuleEvaluator reste néanmoins couvert par un
 * test dédié utilisant un RuleChecker de test, indépendant de cette règle réelle
 * (BR-COMPLIANCE-003 est une garantie du moteur, pas de chaque règle individuelle).
 *
 * Cette règle gagnera une vérification réelle (valeur détectée/ambiguë par extraction
 * documentaire) avec la Phase 7 (Document Processing), où operationType pourra
 * effectivement être incertain sur une facture importée.
 */
final class OperationCategoryMentionRuleChecker implements RuleCheckerInterface
{
    public function check(RuleEvaluationContext $context): RuleCheckResult
    {
        return new RuleCheckResult(
            RuleCheckOutcome::SATISFIED,
            'invoice.operation_type',
            $context->invoice->getOperationType()->value,
        );
    }
}
