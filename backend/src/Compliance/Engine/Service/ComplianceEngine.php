<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Engine\RuleCheck\DocumentFormatRuleChecker;
use App\Compliance\Engine\RuleCheck\OperationCategoryMentionRuleChecker;
use App\Compliance\Engine\RuleCheck\RuleCheckerInterface;
use App\Compliance\Engine\RuleCheck\SirenMentionRuleChecker;
use App\Compliance\Engine\RuleEvaluationContext;
use App\Compliance\Rules\Repository\RuleVersionRepository;
use App\Compliance\Rules\RuleId;
use App\Customer\Entity\Customer;
use App\Document\Entity\Document;
use App\Invoicing\Entity\Invoice;
use App\Organization\Entity\FiscalContext;
use App\Shared\Exception\RegulatoryRuleVersionNotFoundException;

/**
 * Orchestrateur pur du Compliance Engine (docs/06-technical-architecture.md, section 8) :
 * dépend uniquement de Compliance/Rules en lecture (RuleVersionRepository), jamais
 * l'inverse (backend/CLAUDE.md, section 3). Ne persiste rien, ne connaît pas l'audit ni
 * Invoice.status -- la persistance et les transitions de statut appartiennent à
 * App\Compliance\Engine\Service\RunComplianceAnalysisService.
 *
 * Liste ordonnée fixe des règles (docs/12-roadmap.md) : garantit le déterminisme
 * (docs/09-test-strategy.md, section 11) -- même entrée + même contexte + même version de
 * règle = même résultat et même ordre de findings à chaque exécution.
 * FORMAT_FACTURE_ELECTRONIQUE a gagné un vrai RuleChecker en Phase 7
 * (DocumentFormatRuleChecker) : avant cette phase, son applicability (sources:
 * DOCUMENT_IMPORTE) ne pouvait jamais être satisfaite, donc ComplianceRuleEvaluator
 * s'arrêtait toujours à NON_APPLICABLE sans avoir besoin d'un checker.
 */
final class ComplianceEngine
{
    /** @var array<string, ?RuleCheckerInterface> */
    private array $checkersByRuleId;

    public function __construct(
        private readonly RuleVersionRepository $ruleVersionRepository,
        private readonly ComplianceRuleEvaluator $evaluator,
        private readonly ComplianceResultAggregator $aggregator,
    ) {
        $this->checkersByRuleId = [
            RuleId::MENTION_SIREN_CLIENT => new SirenMentionRuleChecker(),
            RuleId::MENTION_CATEGORIE_OPERATION => new OperationCategoryMentionRuleChecker(),
            RuleId::FORMAT_FACTURE_ELECTRONIQUE => new DocumentFormatRuleChecker(),
        ];
    }

    /**
     * @throws RegulatoryRuleVersionNotFoundException si l'une des règles Phase 5 n'a
     *                                                 aucune RuleVersion active à $at (bug
     *                                                 de configuration, jamais un résultat
     *                                                 de conformité)
     *
     * @return array{0: list<ComplianceFindingDraft>, 1: ComplianceResult}
     */
    public function evaluate(Invoice $invoice, Customer $customer, FiscalContext $fiscalContext, \DateTimeImmutable $at, ?Document $document = null): array
    {
        $context = new RuleEvaluationContext($invoice, $customer, $fiscalContext, $at, $document);

        $drafts = [];
        foreach ($this->checkersByRuleId as $ruleId => $checker) {
            $ruleVersion = $this->ruleVersionRepository->findActive($ruleId, $at);

            if (null === $ruleVersion) {
                throw new RegulatoryRuleVersionNotFoundException(
                    sprintf('No active RuleVersion for rule "%s" at %s.', $ruleId, $at->format('Y-m-d')),
                );
            }

            $drafts[] = $this->evaluator->evaluate($ruleVersion, $context, $checker);
        }

        return [$drafts, $this->aggregator->aggregate($drafts)];
    }
}
