<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Engine\RuleCheck\RuleCheckerInterface;
use App\Compliance\Engine\RuleCheck\RuleCheckOutcome;
use App\Compliance\Engine\RuleEvaluationContext;
use App\Compliance\Rules\Entity\RuleVersion;
use App\Compliance\Rules\Enum\ConfidenceLevel;

/**
 * Cœur générique de l'évaluation d'une règle (docs/06-technical-architecture.md, section
 * 8-9). Un seul endroit applique BR-COMPLIANCE-002/003/004 pour TOUTE règle, jamais
 * dupliqué dans chaque RuleChecker :
 *   1. applicabilité (conditions['applicability']) -> NON_APPLICABLE si non applicable ;
 *   2. confiance (RuleVersion.confidenceLevel != ELEVE) -> INCERTAIN_REGLEMENTAIRE
 *      (BR-COMPLIANCE-004) ;
 *   3. RuleCheckerInterface::check() -> DATA_MISSING = A_VERIFIER (BR-COMPLIANCE-003),
 *      SATISFIED = CONFORME, VIOLATED = RuleVersion.severity (l'état produit en cas de
 *      non-respect, docs/07-data-model.md section 16 -- jamais recalculé/deviné ici).
 *
 * Pur : ne persiste rien, ne connaît pas l'audit. Message/correction_action sont lus dans
 * RuleVersion.conditions['outcomes'][result], avec repli sur explanation_template si la clé
 * est absente (garde-fou, ne devrait pas arriver pour les règles seedées de cette phase).
 */
final class ComplianceRuleEvaluator
{
    public function evaluate(RuleVersion $ruleVersion, RuleEvaluationContext $context, ?RuleCheckerInterface $checker): ComplianceFindingDraft
    {
        $conditions = $ruleVersion->getConditions();

        /** @var array<string, mixed> $applicability */
        $applicability = \is_array($conditions['applicability'] ?? null) ? $conditions['applicability'] : [];

        if (!$this->isApplicable($applicability, $context)) {
            return $this->draft($ruleVersion, ComplianceResult::NON_APPLICABLE, null, null);
        }

        if (ConfidenceLevel::ELEVE !== $ruleVersion->getConfidenceLevel()) {
            return $this->draft($ruleVersion, ComplianceResult::INCERTAIN_REGLEMENTAIRE, null, null);
        }

        if (null === $checker) {
            return $this->draft($ruleVersion, ComplianceResult::NON_APPLICABLE, null, null);
        }

        $checkResult = $checker->check($context);

        $result = match ($checkResult->outcome) {
            RuleCheckOutcome::DATA_MISSING => ComplianceResult::A_VERIFIER,
            RuleCheckOutcome::SATISFIED => ComplianceResult::CONFORME,
            RuleCheckOutcome::VIOLATED => ComplianceResult::from((string) $ruleVersion->getSeverity()),
        };

        return $this->draft($ruleVersion, $result, $checkResult->relatedField, $checkResult->observedValue);
    }

    /** @param array<string, mixed> $applicability */
    private function isApplicable(array $applicability, RuleEvaluationContext $context): bool
    {
        /** @var list<string>|null $customerTypes */
        $customerTypes = $applicability['customer_types'] ?? null;
        if (\is_array($customerTypes) && !\in_array($context->customer->getCustomerType()->value, $customerTypes, true)) {
            return false;
        }

        if (true === ($applicability['requires_non_exempt'] ?? false) && null !== $context->invoice->getVatExemptionReason()) {
            return false;
        }

        /** @var list<string>|null $sources */
        $sources = $applicability['sources'] ?? null;
        if (\is_array($sources) && !\in_array($context->invoice->getSource()->value, $sources, true)) {
            return false;
        }

        // Phase 7 (docs/12-roadmap.md) : distinct de "sources" ci-dessus - une Invoice reste
        // toujours InvoiceSource::SAISIE_MANUELLE sous la décision produit retenue (plan
        // Phase 7, décision 1, corrigée) : un document est rattaché après coup à une facture
        // créée normalement dans l'éditeur, il ne change jamais Invoice.source. La bonne
        // condition d'applicabilité pour format-facture-electronique est donc "un Document
        // est-il présent", jamais "source vaut DOCUMENT_IMPORTE" (valeur qu'aucun chemin de
        // code ne produit plus - corrigé pendant l'implémentation, avant tout usage réel).
        if (true === ($applicability['requires_document'] ?? false) && null === $context->document) {
            return false;
        }

        return true;
    }

    private function draft(RuleVersion $ruleVersion, ComplianceResult $result, ?string $relatedField, ?string $observedValue): ComplianceFindingDraft
    {
        $conditions = $ruleVersion->getConditions();
        /** @var array<string, mixed> $outcomes */
        $outcomes = \is_array($conditions['outcomes'] ?? null) ? $conditions['outcomes'] : [];
        /** @var array<string, mixed> $outcome */
        $outcome = \is_array($outcomes[$result->value] ?? null) ? $outcomes[$result->value] : [];

        $message = \is_string($outcome['message'] ?? null) ? $outcome['message'] : $ruleVersion->getExplanationTemplate();
        $correctionAction = \is_string($outcome['correction_action'] ?? null) ? $outcome['correction_action'] : null;

        return new ComplianceFindingDraft($ruleVersion, $result, $message, $relatedField, $observedValue, $correctionAction);
    }
}
