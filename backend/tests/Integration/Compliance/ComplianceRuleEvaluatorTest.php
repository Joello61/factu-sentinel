<?php

declare(strict_types=1);

namespace App\Tests\Integration\Compliance;

use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Engine\RuleCheck\RuleCheckerInterface;
use App\Compliance\Engine\RuleCheck\RuleCheckOutcome;
use App\Compliance\Engine\RuleCheck\RuleCheckResult;
use App\Compliance\Engine\RuleCheck\SirenMentionRuleChecker;
use App\Compliance\Engine\RuleEvaluationContext;
use App\Compliance\Engine\Service\ComplianceRuleEvaluator;
use App\Compliance\Rules\Entity\RegulatoryRule;
use App\Compliance\Rules\Entity\RuleVersion;
use App\Compliance\Rules\Enum\ConfidenceLevel;
use App\Compliance\Rules\Enum\RuleCategory;
use App\Compliance\Rules\Enum\RuleStatus;
use App\Customer\Entity\Customer;
use App\Customer\Enum\CustomerType;
use App\Invoicing\Entity\Invoice;
use App\Invoicing\Enum\InvoiceSource;
use App\Invoicing\Enum\OperationType;
use App\Organization\Entity\FiscalContext;
use App\Organization\Entity\Organization;
use App\Organization\Enum\CompanySizeCategory;
use App\Organization\Enum\VatStatus;
use PHPUnit\Framework\TestCase;

/**
 * Cœur générique du Compliance Engine (docs/06-technical-architecture.md, section 8-9) :
 * pas de dépendance à la base de données, service pur, entités construites directement.
 */
final class ComplianceRuleEvaluatorTest extends TestCase
{
    private ComplianceRuleEvaluator $evaluator;
    private Organization $organization;
    private FiscalContext $fiscalContext;

    protected function setUp(): void
    {
        $this->evaluator = new ComplianceRuleEvaluator();
        $this->organization = new Organization();
        $this->fiscalContext = new FiscalContext(
            $this->organization,
            VatStatus::ASSUJETTI_REDEVABLE,
            5,
            '200000',
            '150000',
            CompanySizeCategory::PME_TPE_MICRO,
            new \DateTimeImmutable('2026-01-01'),
        );
    }

    private function customer(CustomerType $type, ?string $siren): Customer
    {
        $country = CustomerType::PROFESSIONNEL_ETRANGER === $type ? 'DE' : 'FR';

        return new Customer($this->organization, $type, 'Client Test', $siren, null, $country);
    }

    private function invoice(Customer $customer, ?string $vatExemptionReason = null, InvoiceSource $source = InvoiceSource::SAISIE_MANUELLE): Invoice
    {
        return new Invoice($this->organization, $customer, null, new \DateTimeImmutable('2026-08-19'), OperationType::PRESTATION_SERVICE, 'EUR', $vatExemptionReason, $source);
    }

    private function ruleVersion(array $conditions, ?string $severity, ConfidenceLevel $confidence = ConfidenceLevel::ELEVE): RuleVersion
    {
        $rule = new RegulatoryRule('mention-siren-client', 'SIREN', 'SIREN', RuleCategory::MENTION_OBLIGATOIRE, 'FR', RuleStatus::ACTIVE);

        return new RuleVersion($rule, 1, new \DateTimeImmutable('2026-01-01'), $conditions, $severity, 'docs/02-regulatory-study.md, section 10', $confidence, 'fallback template');
    }

    /** @return array{applicability: array<string, mixed>, outcomes: array<string, mixed>} */
    private function sirenConditions(): array
    {
        return [
            'applicability' => ['customer_types' => ['PROFESSIONNEL_FRANCAIS'], 'requires_non_exempt' => true],
            'outcomes' => [
                'CONFORME' => ['message' => 'ok'],
                'NON_CONFORME' => ['message' => 'siren manquant', 'correction_action' => 'ajoutez le siren'],
                'NON_APPLICABLE' => ['message' => 'non applicable'],
            ],
        ];
    }

    public function testViolatedMapsToRuleVersionSeverity(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, null);
        $context = new RuleEvaluationContext($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));
        $ruleVersion = $this->ruleVersion($this->sirenConditions(), 'NON_CONFORME');

        $draft = $this->evaluator->evaluate($ruleVersion, $context, new SirenMentionRuleChecker());

        self::assertSame(ComplianceResult::NON_CONFORME, $draft->result);
        self::assertSame('siren manquant', $draft->message);
        self::assertSame('ajoutez le siren', $draft->correctionAction);
        self::assertSame('customer.siren', $draft->relatedField);
    }

    public function testSatisfiedMapsToConforme(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, '123456789');
        $context = new RuleEvaluationContext($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));
        $ruleVersion = $this->ruleVersion($this->sirenConditions(), 'NON_CONFORME');

        $draft = $this->evaluator->evaluate($ruleVersion, $context, new SirenMentionRuleChecker());

        self::assertSame(ComplianceResult::CONFORME, $draft->result);
        self::assertSame('ok', $draft->message);
        self::assertNull($draft->correctionAction);
    }

    /** REG-002/REG-003 : client non concerné par les mentions e-invoicing. */
    public function testNonMatchingCustomerTypeIsNonApplicable(): void
    {
        $customer = $this->customer(CustomerType::PARTICULIER, null);
        $context = new RuleEvaluationContext($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));
        $ruleVersion = $this->ruleVersion($this->sirenConditions(), 'NON_CONFORME');

        $draft = $this->evaluator->evaluate($ruleVersion, $context, new SirenMentionRuleChecker());

        self::assertSame(ComplianceResult::NON_APPLICABLE, $draft->result);
        self::assertSame('non applicable', $draft->message);
    }

    /** REG-007 : opération exonérée de TVA, jamais NON_CONFORME. */
    public function testVatExemptOperationIsNonApplicable(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, null);
        $invoice = $this->invoice($customer, 'Article 261 CGI');
        $context = new RuleEvaluationContext($invoice, $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));
        $ruleVersion = $this->ruleVersion($this->sirenConditions(), 'NON_CONFORME');

        $draft = $this->evaluator->evaluate($ruleVersion, $context, new SirenMentionRuleChecker());

        self::assertSame(ComplianceResult::NON_APPLICABLE, $draft->result);
    }

    /** Format rule (Phase 5) : applicability sur invoice.source, jamais satisfaite en saisie manuelle. */
    public function testSourceApplicabilityGatesFormatRule(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, '123456789');
        $invoice = $this->invoice($customer, null, InvoiceSource::SAISIE_MANUELLE);
        $context = new RuleEvaluationContext($invoice, $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));
        $ruleVersion = $this->ruleVersion(
            ['applicability' => ['sources' => ['DOCUMENT_IMPORTE']], 'outcomes' => ['NON_APPLICABLE' => ['message' => 'saisie manuelle']]],
            null,
        );

        $draft = $this->evaluator->evaluate($ruleVersion, $context, null);

        self::assertSame(ComplianceResult::NON_APPLICABLE, $draft->result);
        self::assertSame('saisie manuelle', $draft->message);
    }

    /** BR-COMPLIANCE-004 : confiance non élevée -> toujours INCERTAIN_REGLEMENTAIRE. */
    public function testLowConfidenceOverridesCheckResult(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, '123456789');
        $context = new RuleEvaluationContext($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));
        $ruleVersion = $this->ruleVersion($this->sirenConditions(), 'NON_CONFORME', ConfidenceLevel::MOYEN);

        $draft = $this->evaluator->evaluate($ruleVersion, $context, new SirenMentionRuleChecker());

        self::assertSame(ComplianceResult::INCERTAIN_REGLEMENTAIRE, $draft->result);
    }

    /**
     * BR-COMPLIANCE-003 : une donnée manquante (au sens où le RuleChecker ne peut pas
     * mener sa vérification) produit toujours A_VERIFIER, jamais NON_CONFORME par défaut.
     * Garantie du moteur générique, pas de chaque règle individuelle (voir
     * App\Compliance\Engine\RuleCheck\OperationCategoryMentionRuleChecker : aucune des 3
     * règles Phase 5 ne peut réellement produire DATA_MISSING, ce test le prouve donc au
     * niveau de l'évaluateur avec un RuleChecker de test dédié).
     */
    public function testDataMissingMapsToAVerifier(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, '123456789');
        $context = new RuleEvaluationContext($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));
        $ruleVersion = $this->ruleVersion($this->sirenConditions(), 'NON_CONFORME');

        $checker = new class implements RuleCheckerInterface {
            public function check(RuleEvaluationContext $context): RuleCheckResult
            {
                return new RuleCheckResult(RuleCheckOutcome::DATA_MISSING);
            }
        };

        $draft = $this->evaluator->evaluate($ruleVersion, $context, $checker);

        self::assertSame(ComplianceResult::A_VERIFIER, $draft->result);
    }

    public function testMissingOutcomeMessageFallsBackToExplanationTemplate(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, '123456789');
        $context = new RuleEvaluationContext($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));
        $ruleVersion = $this->ruleVersion(
            ['applicability' => ['customer_types' => ['PROFESSIONNEL_FRANCAIS']], 'outcomes' => []],
            'NON_CONFORME',
        );

        $draft = $this->evaluator->evaluate($ruleVersion, $context, new SirenMentionRuleChecker());

        self::assertSame('fallback template', $draft->message);
    }
}
