<?php

declare(strict_types=1);

namespace App\Tests\Integration\Compliance;

use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Engine\Service\ComplianceEngine;
use App\Customer\Entity\Customer;
use App\Customer\Enum\CustomerType;
use App\Invoicing\Entity\Invoice;
use App\Invoicing\Enum\InvoiceSource;
use App\Invoicing\Enum\OperationType;
use App\Organization\Entity\FiscalContext;
use App\Organization\Entity\Organization;
use App\Organization\Enum\CompanySizeCategory;
use App\Organization\Enum\VatStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Contre le référentiel réel seedé par la migration Phase 5 (docs/09-test-strategy.md,
 * section 9 : REG-002, REG-003, REG-004, REG-007 ; section 11 : déterminisme ; section 12 :
 * non-régression historique) : jamais de RuleVersion recopiée en dur ici, comme
 * App\Tests\Integration\Compliance\EligibilityDiagnosticCalculatorTest en Phase 3.
 */
final class ComplianceEngineTest extends KernelTestCase
{
    private ComplianceEngine $engine;
    private Organization $organization;
    private FiscalContext $fiscalContext;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->engine = self::getContainer()->get(ComplianceEngine::class);

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

    private function invoice(Customer $customer, ?string $vatExemptionReason = null): Invoice
    {
        return new Invoice($this->organization, $customer, null, new \DateTimeImmutable('2026-08-19'), OperationType::PRESTATION_SERVICE, 'EUR', $vatExemptionReason, InvoiceSource::SAISIE_MANUELLE);
    }

    /** REG-004 : SIREN manquant, client professionnel français -> NON_CONFORME sur SIREN. */
    public function testReg004MissingSirenIsNonConforme(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, null);
        [$drafts, $globalResult] = $this->engine->evaluate($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));

        $siren = array_values(array_filter($drafts, static fn ($d) => 'mention-siren-client' === $d->ruleVersion->getRule()->getId()))[0];
        self::assertSame(ComplianceResult::NON_CONFORME, $siren->result);
        self::assertNotEmpty($siren->correctionAction);
        self::assertSame(ComplianceResult::NON_CONFORME, $globalResult);
    }

    public function testSirenPresentIsConforme(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, '123456789');
        [$drafts, $globalResult] = $this->engine->evaluate($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));

        $siren = array_values(array_filter($drafts, static fn ($d) => 'mention-siren-client' === $d->ruleVersion->getRule()->getId()))[0];
        self::assertSame(ComplianceResult::CONFORME, $siren->result);
        self::assertSame(ComplianceResult::CONFORME, $globalResult);
    }

    /** REG-002 : client particulier -> mentions e-invoicing NON_APPLICABLE. */
    public function testReg002ParticulierCustomerIsNonApplicable(): void
    {
        $customer = $this->customer(CustomerType::PARTICULIER, null);
        [$drafts] = $this->engine->evaluate($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));

        foreach ($drafts as $draft) {
            if ('format-document-structure' === $draft->ruleVersion->getRule()->getId()) {
                continue;
            }
            self::assertSame(ComplianceResult::NON_APPLICABLE, $draft->result);
        }
    }

    /** REG-003 : client professionnel étranger -> mentions e-invoicing NON_APPLICABLE. */
    public function testReg003ProfessionnelEtrangerIsNonApplicable(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_ETRANGER, null);
        [$drafts] = $this->engine->evaluate($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));

        foreach ($drafts as $draft) {
            if ('format-document-structure' === $draft->ruleVersion->getRule()->getId()) {
                continue;
            }
            self::assertSame(ComplianceResult::NON_APPLICABLE, $draft->result);
        }
    }

    /** REG-007 : opération exonérée de TVA -> NON_APPLICABLE, jamais NON_CONFORME. */
    public function testReg007VatExemptOperationIsNonApplicable(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, null);
        $invoice = $this->invoice($customer, 'Article 261 CGI');
        [$drafts, $globalResult] = $this->engine->evaluate($invoice, $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));

        foreach ($drafts as $draft) {
            if ('format-document-structure' === $draft->ruleVersion->getRule()->getId()) {
                continue;
            }
            self::assertSame(ComplianceResult::NON_APPLICABLE, $draft->result);
        }
        self::assertSame(ComplianceResult::CONFORME, $globalResult);
    }

    /** Règle de format (Phase 5) : toujours NON_APPLICABLE en saisie manuelle (plan Phase 5). */
    public function testFormatRuleIsAlwaysNonApplicableForManualEntry(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, '123456789');
        [$drafts] = $this->engine->evaluate($this->invoice($customer), $customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-19'));

        $format = array_values(array_filter($drafts, static fn ($d) => 'format-document-structure' === $d->ruleVersion->getRule()->getId()))[0];
        self::assertSame(ComplianceResult::NON_APPLICABLE, $format->result);
    }

    public function testDeterminism(): void
    {
        $customer = $this->customer(CustomerType::PROFESSIONNEL_FRANCAIS, null);
        $invoice = $this->invoice($customer);
        $at = new \DateTimeImmutable('2026-08-19');

        [$firstDrafts, $firstGlobal] = $this->engine->evaluate($invoice, $customer, $this->fiscalContext, $at);
        [$secondDrafts, $secondGlobal] = $this->engine->evaluate($invoice, $customer, $this->fiscalContext, $at);

        self::assertSame($firstGlobal, $secondGlobal);
        self::assertCount(\count($firstDrafts), $secondDrafts);
        foreach ($firstDrafts as $i => $draft) {
            self::assertSame($draft->ruleVersion->getRule()->getId(), $secondDrafts[$i]->ruleVersion->getRule()->getId(), 'Ordre des findings stable.');
            self::assertSame($draft->result, $secondDrafts[$i]->result);
            self::assertSame($draft->message, $secondDrafts[$i]->message);
        }
    }
}
