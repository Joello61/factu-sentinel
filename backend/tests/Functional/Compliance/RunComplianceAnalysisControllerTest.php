<?php

declare(strict_types=1);

namespace App\Tests\Functional\Compliance;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * POST /invoices/{id}/compliance-analyses (docs/08-api-specification.md, section 29-30 ;
 * US-COMPLIANCE-002). Toujours 200 OK en Phase 5 (saisie manuelle = toujours synchrone).
 */
final class RunComplianceAnalysisControllerTest extends ApiTestCase
{
    private function configureFiscalContext(KernelBrowser $client): void
    {
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_REDEVABLE',
                'employees_count' => 5,
                'annual_turnover' => '200000',
                'annual_balance_sheet_total' => '150000',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    private function createCustomer(KernelBrowser $client, string $customerType = 'PROFESSIONNEL_FRANCAIS', ?string $siren = '123456789'): string
    {
        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => $customerType,
            'name' => 'Client Test SARL',
            'siren' => $siren,
            'country' => 'PROFESSIONNEL_ETRANGER' === $customerType ? 'DE' : 'FR',
        ]);
        self::assertResponseStatusCodeSame(201);

        return $this->jsonBody($client)['data']['id'];
    }

    private function createInvoice(KernelBrowser $client, string $customerId, ?string $vatExemptionReason = null): string
    {
        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'PRESTATION_SERVICE',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'vat_exemption_reason' => $vatExemptionReason,
            'lines' => [['description' => 'Prestation', 'quantity' => '1', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invoice-create-'.bin2hex(random_bytes(8))]);
        self::assertResponseStatusCodeSame(201);

        return $this->jsonBody($client)['data']['id'];
    }

    private function runAnalysis(KernelBrowser $client, string $invoiceId, string $idempotencyKey): void
    {
        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => $idempotencyKey]);
    }

    public function testMissingIdempotencyKeyReturns400(): void
    {
        $client = $this->createAuthenticatedClient('compliance-run-001@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);

        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId));

        self::assertResponseStatusCodeSame(400);
    }

    public function testWithoutFiscalContextReturns409(): void
    {
        $client = $this->createAuthenticatedClient('compliance-run-002@example.test');
        // Pas de configureFiscalContext() : Customer/Invoice ne dépendent pas de FiscalContext.
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);

        $this->runAnalysis($client, $invoiceId, 'key-002');

        self::assertResponseStatusCodeSame(409);
    }

    /** REG-004 : SIREN manquant, client professionnel français -> NON_CONFORME. */
    public function testMissingSirenProducesNonConformeWithCorrectionAction(): void
    {
        $client = $this->createAuthenticatedClient('compliance-run-003@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client, 'PROFESSIONNEL_FRANCAIS', null);
        $invoiceId = $this->createInvoice($client, $customerId);

        $this->runAnalysis($client, $invoiceId, 'key-003');

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame('COMPLETED', $data['status']);
        self::assertSame('NON_CONFORME', $data['global_result']);

        $findings = $data['findings'];
        $siren = current(array_filter($findings, static fn (array $f): bool => 'mention-siren-client' === $f['rule']['id']));
        self::assertSame('NON_CONFORME', $siren['result']);
        self::assertNotEmpty($siren['correction_action']);
        self::assertSame('customer.siren', $siren['related_field']);
        self::assertSame(1, $siren['rule']['version']);
        // docs/11-frontend-design-system.md, section 29 : le niveau 3 du Compliance
        // Finding UI a besoin de la date d'entrée en vigueur de la règle appliquée.
        self::assertSame('2026-01-01', $siren['rule']['effective_from']);
        self::assertArrayHasKey('effective_until', $siren['rule'], 'La clé doit être présente même à null, jamais omise.');
        self::assertNull($siren['rule']['effective_until']);
    }

    public function testSirenPresentProducesConforme(): void
    {
        $client = $this->createAuthenticatedClient('compliance-run-004@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);

        $this->runAnalysis($client, $invoiceId, 'key-004');

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame('CONFORME', $data['global_result']);
    }

    /** REG-007 : opération exonérée de TVA -> NON_APPLICABLE, jamais NON_CONFORME. */
    public function testVatExemptOperationIsNonApplicable(): void
    {
        $client = $this->createAuthenticatedClient('compliance-run-005@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client, 'PROFESSIONNEL_FRANCAIS', null);
        $invoiceId = $this->createInvoice($client, $customerId, 'Article 261 CGI');

        $this->runAnalysis($client, $invoiceId, 'key-005');

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame('CONFORME', $data['global_result']);
        foreach ($data['findings'] as $finding) {
            self::assertSame('NON_APPLICABLE', $finding['result']);
        }
    }

    public function testIdempotencyKeyReplayReturnsSameResponseWithoutCreatingSecondAnalysis(): void
    {
        $client = $this->createAuthenticatedClient('compliance-run-006@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);

        $this->runAnalysis($client, $invoiceId, 'replay-key-006');
        self::assertResponseStatusCodeSame(200);
        $first = $this->jsonBody($client);

        $this->runAnalysis($client, $invoiceId, 'replay-key-006');
        self::assertResponseStatusCodeSame(200);
        $second = $this->jsonBody($client);

        self::assertSame($first, $second, 'La réponse rejouée doit être strictement identique.');

        $client->jsonRequest('GET', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId));
        self::assertCount(1, $this->jsonBody($client)['data'], 'Une seule ComplianceAnalysis doit avoir été créée.');
    }

    public function testUnknownInvoiceReturns404(): void
    {
        $client = $this->createAuthenticatedClient('compliance-run-007@example.test');
        $this->configureFiscalContext($client);

        $this->runAnalysis($client, '00000000-0000-7000-8000-000000000000', 'key-007');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOtherTenantInvoiceReturns404(): void
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, 'compliance-run-tenant-a@example.test');
        $tokenB = $this->loginAs($client, 'compliance-run-tenant-b@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $this->configureFiscalContext($client);
        $this->runAnalysis($client, $invoiceId, 'key-008');

        self::assertResponseStatusCodeSame(404, 'Invoicing.compliance-analyses ne doit jamais traverser deux organization_id.');
    }

    public function testInvoiceStatusBecomesAnalyzed(): void
    {
        $client = $this->createAuthenticatedClient('compliance-run-009@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);

        $this->runAnalysis($client, $invoiceId, 'key-009');
        self::assertResponseStatusCodeSame(200);

        $client->jsonRequest('GET', '/api/v1/invoices/'.$invoiceId);
        self::assertSame('ANALYZED', $this->jsonBody($client)['data']['status']);
    }
}
