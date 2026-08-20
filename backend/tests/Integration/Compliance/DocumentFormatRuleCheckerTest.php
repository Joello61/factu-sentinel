<?php

declare(strict_types=1);

namespace App\Tests\Integration\Compliance;

use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Engine\Service\ComplianceEngine;
use App\Customer\Entity\Customer;
use App\Customer\Enum\CustomerType;
use App\Document\Entity\Document;
use App\Document\Entity\DocumentProcessingRecord;
use App\Document\Enum\DocumentFileFormat;
use App\Invoicing\Entity\Invoice;
use App\Invoicing\Enum\InvoiceSource;
use App\Invoicing\Enum\OperationType;
use App\Organization\Entity\FiscalContext;
use App\Organization\Entity\Organization;
use App\Organization\Enum\CompanySizeCategory;
use App\Organization\Enum\VatStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * App\Compliance\Engine\RuleCheck\DocumentFormatRuleChecker (règle
 * format-facture-electronique, plan Phase 7 ; US-COMPLIANCE-005), contre le référentiel réel
 * seedé par les migrations (Phase 5 v1 + Phase 7 v2), même style que
 * App\Tests\Integration\Compliance\ComplianceEngineTest.
 */
final class DocumentFormatRuleCheckerTest extends KernelTestCase
{
    private ComplianceEngine $engine;
    private Organization $organization;
    private FiscalContext $fiscalContext;
    private Customer $customer;

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
        // SIREN renseigné : neutralise mention-siren-client pour isoler
        // format-facture-electronique dans global_result.
        $this->customer = new Customer($this->organization, CustomerType::PROFESSIONNEL_FRANCAIS, 'Client Test', '123456789', null, 'FR');
    }

    private function invoice(): Invoice
    {
        return new Invoice($this->organization, $this->customer, null, new \DateTimeImmutable('2026-08-20'), OperationType::PRESTATION_SERVICE, 'EUR', null, InvoiceSource::SAISIE_MANUELLE);
    }

    private function document(Invoice $invoice, ?DocumentFileFormat $format): Document
    {
        $document = new Document($this->organization, $invoice, 'test.pdf', 1000, 'checksum', 'ref');
        if (null !== $format) {
            $document->classify($format);
        }
        // currentProcessingRecord requis pour Document::getProcessingStatus() - non lu par
        // DocumentFormatRuleChecker, qui ne lit que getFileFormat().
        $record = new DocumentProcessingRecord($document);
        $document->setCurrentProcessingRecord($record);

        return $document;
    }

    private function findingFor(array $findings, string $ruleId): \App\Compliance\Engine\Service\ComplianceFindingDraft
    {
        foreach ($findings as $finding) {
            if ($ruleId === $finding->ruleVersion->getRule()->getId()) {
                return $finding;
            }
        }

        self::fail(sprintf('No finding for rule "%s".', $ruleId));
    }

    public function testNoDocumentIsNonApplicable(): void
    {
        $invoice = $this->invoice();

        [$findings] = $this->engine->evaluate($invoice, $this->customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-20'), null);

        $finding = $this->findingFor($findings, 'format-facture-electronique');
        self::assertSame(ComplianceResult::NON_APPLICABLE, $finding->result);
    }

    public function testFacturxIsConforme(): void
    {
        $invoice = $this->invoice();
        $document = $this->document($invoice, DocumentFileFormat::FACTURX);

        [$findings, $globalResult] = $this->engine->evaluate($invoice, $this->customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-20'), $document);

        $finding = $this->findingFor($findings, 'format-facture-electronique');
        self::assertSame(ComplianceResult::CONFORME, $finding->result);
        self::assertSame(ComplianceResult::CONFORME, $globalResult);
    }

    /**
     * US-COMPLIANCE-005 : l'explication doit apparaître explicitement même si toutes les
     * autres mentions obligatoires sont par ailleurs respectées (SIREN présent ici).
     */
    public function testPdfSimpleIsNonConformeWithExplicitMessage(): void
    {
        $invoice = $this->invoice();
        $document = $this->document($invoice, DocumentFileFormat::PDF_SIMPLE);

        [$findings, $globalResult] = $this->engine->evaluate($invoice, $this->customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-20'), $document);

        $finding = $this->findingFor($findings, 'format-facture-electronique');
        self::assertSame(ComplianceResult::NON_CONFORME, $finding->result);
        self::assertStringContainsString('même s\'il contient par ailleurs toutes les mentions obligatoires', $finding->message);
        self::assertNotEmpty($finding->correctionAction);
        self::assertSame(ComplianceResult::NON_CONFORME, $globalResult, 'Une seule règle NON_CONFORME suffit à rendre le résultat global NON_CONFORME.');
    }

    /** BR-COMPLIANCE-003 : classification pas encore déterminée -> A_VERIFIER, jamais NON_CONFORME par défaut. */
    public function testDocumentWithoutFileFormatIsDataMissing(): void
    {
        $invoice = $this->invoice();
        $document = $this->document($invoice, null);

        [$findings] = $this->engine->evaluate($invoice, $this->customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-20'), $document);

        $finding = $this->findingFor($findings, 'format-facture-electronique');
        self::assertSame(ComplianceResult::A_VERIFIER, $finding->result);
    }

    public function testUblAndCiiAreNonConforme(): void
    {
        foreach ([DocumentFileFormat::UBL, DocumentFileFormat::CII] as $format) {
            $invoice = $this->invoice();
            $document = $this->document($invoice, $format);

            [$findings] = $this->engine->evaluate($invoice, $this->customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-20'), $document);

            $finding = $this->findingFor($findings, 'format-facture-electronique');
            self::assertSame(ComplianceResult::NON_CONFORME, $finding->result, sprintf('%s doit produire NON_CONFORME.', $format->value));
        }
    }

    /**
     * Garde-fou prioritaire (plan Phase 7, revue) : le Compliance Engine ne lit jamais
     * DocumentProcessingRecord::extractedDataSummary - seule Document::fileFormat (donnée
     * structurée confirmée) peut influencer un résultat, jamais une suggestion d'extraction.
     */
    public function testExtractedDataSummaryNeverInfluencesTheResult(): void
    {
        $invoice = $this->invoice();
        $document = $this->document($invoice, DocumentFileFormat::FACTURX);
        // Suggestion contradictoire (SIREN différent de celui réellement enregistré sur
        // Customer) : ne doit avoir strictement aucun effet sur le résultat.
        $document->getCurrentProcessingRecord()->markValidated(['buyer_vat_or_siren' => '999999999']);

        [$findingsWithSuggestion, $globalResultWithSuggestion] = $this->engine->evaluate($invoice, $this->customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-20'), $document);

        $documentWithoutSuggestion = $this->document($invoice, DocumentFileFormat::FACTURX);
        [$findingsWithoutSuggestion, $globalResultWithoutSuggestion] = $this->engine->evaluate($invoice, $this->customer, $this->fiscalContext, new \DateTimeImmutable('2026-08-20'), $documentWithoutSuggestion);

        self::assertSame($globalResultWithSuggestion, $globalResultWithoutSuggestion);
        self::assertSame(
            array_map(static fn ($f) => $f->result, $findingsWithSuggestion),
            array_map(static fn ($f) => $f->result, $findingsWithoutSuggestion),
        );
        // Toujours celle réellement enregistrée (123456789), jamais la suggestion (999999999).
        $siren = $this->findingFor($findingsWithSuggestion, 'mention-siren-client');
        self::assertSame('123456789', $siren->observedValue);
    }
}
