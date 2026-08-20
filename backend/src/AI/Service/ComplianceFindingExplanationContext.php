<?php

declare(strict_types=1);

namespace App\AI\Service;

/**
 * Contexte minimisé transmis à App\AI\Service\AIGateway::explainFinding() -
 * docs/06-technical-architecture.md, section 14-15 : "il n'a pas d'accès direct aux données
 * de Invoices, Company ou Customers en dehors du contexte explicitement transmis". Construit
 * par App\AI\Service\ExplainComplianceFindingService à partir d'un ComplianceFinding déjà
 * résolu et autorisé - jamais l'entité elle-même n'est transmise plus loin, précisément pour
 * qu'il soit structurellement impossible de traverser complianceAnalysis -> invoice ->
 * customer/organization depuis AIGateway, plutôt que de reposer sur la seule discipline de ne
 * pas le faire.
 */
final readonly class ComplianceFindingExplanationContext
{
    public function __construct(
        public string $ruleId,
        public string $ruleName,
        public string $ruleDescription,
        public int $ruleVersionNumber,
        public string $result,
        public string $message,
        public ?string $relatedField,
        public ?string $observedValue,
        public ?string $correctionAction,
        public string $sourceReference,
        public string $confidenceLevel,
    ) {
    }
}
