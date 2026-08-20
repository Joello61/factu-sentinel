<?php

declare(strict_types=1);

namespace App\Tests\Functional\Compliance;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * GET /dashboard (docs/08-api-specification.md, section 33 ; US-DASHBOARD-001).
 */
final class GetDashboardControllerTest extends ApiTestCase
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

    /** @return array{invoiceId: string, analysisId: string} */
    private function createAnalyzedInvoice(KernelBrowser $client, ?string $siren, string $idempotencySuffix): array
    {
        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client Test SARL',
            'siren' => $siren,
            'country' => 'FR',
        ]);
        $customerId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'PRESTATION_SERVICE',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Prestation', 'quantity' => '1', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'dashboard-invoice-'.$idempotencySuffix]);
        $invoiceId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'dashboard-analysis-'.$idempotencySuffix]);
        self::assertResponseStatusCodeSame(200);
        $analysisId = $this->jsonBody($client)['data']['id'];

        return ['invoiceId' => $invoiceId, 'analysisId' => $analysisId];
    }

    public function testEmptyOrganizationReturnsAucuneAnalyse(): void
    {
        $client = $this->createAuthenticatedClient('dashboard-empty@example.test');

        $client->jsonRequest('GET', '/api/v1/dashboard');
        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];

        self::assertSame('AUCUNE_ANALYSE', $data['global_status']);
        self::assertSame(0, $data['open_issues_count']);
        self::assertSame(0, $data['warnings_count']);
        self::assertSame([], $data['recent_analyses']);
        self::assertSame([], $data['recommended_actions']);
    }

    public function testInvoiceWithMissingSirenYieldsAttentionRequiseWithRecommendedAction(): void
    {
        $client = $this->createAuthenticatedClient('dashboard-attention@example.test');
        $this->markEmailVerified('dashboard-attention@example.test');
        $this->configureFiscalContext($client);

        // SIREN manquant sur un client professionnel français -> NON_CONFORME (REG-004).
        $this->createAnalyzedInvoice($client, null, 'attention');

        $client->jsonRequest('GET', '/api/v1/dashboard');
        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];

        self::assertSame('ATTENTION_REQUISE', $data['global_status']);
        self::assertGreaterThan(0, $data['open_issues_count']);
        self::assertNotEmpty($data['recommended_actions']);
        self::assertArrayHasKey('related_analysis_id', $data['recommended_actions'][0]);
    }

    public function testInvoiceWithoutIssuesYieldsConforme(): void
    {
        $client = $this->createAuthenticatedClient('dashboard-conforme@example.test');
        $this->markEmailVerified('dashboard-conforme@example.test');
        $this->configureFiscalContext($client);

        // SIREN présent, client professionnel français -> pas de NON_CONFORME sur cette règle.
        $this->createAnalyzedInvoice($client, '123456789', 'conforme');

        $client->jsonRequest('GET', '/api/v1/dashboard');
        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];

        self::assertContains($data['global_status'], ['CONFORME', 'AVERTISSEMENT'], 'Jamais ATTENTION_REQUISE sans problème réel.');
    }

    public function testReanalyzingAnInvoiceOnlyCountsTheLatestAnalysis(): void
    {
        $client = $this->createAuthenticatedClient('dashboard-reanalyze@example.test');
        $this->markEmailVerified('dashboard-reanalyze@example.test');
        $this->configureFiscalContext($client);

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client Test SARL',
            'siren' => null,
            'country' => 'FR',
        ]);
        $customerId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'PRESTATION_SERVICE',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Prestation', 'quantity' => '1', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'dashboard-reanalyze-invoice']);
        $invoiceId = $this->jsonBody($client)['data']['id'];

        // Première analyse : SIREN manquant -> NON_CONFORME.
        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'dashboard-reanalyze-1']);
        self::assertResponseStatusCodeSame(200);

        // Correction, puis nouvelle analyse : plus de problème sur cette facture.
        $client->jsonRequest('PATCH', '/api/v1/customers/'.$customerId, ['siren' => '123456789']);
        self::assertResponseStatusCodeSame(200);
        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'dashboard-reanalyze-2']);
        self::assertResponseStatusCodeSame(200);

        $client->jsonRequest('GET', '/api/v1/dashboard');
        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];

        self::assertSame(0, $data['open_issues_count'], 'Une correction déjà réanalysée ne doit plus compter comme un problème ouvert.');
    }

    public function testDashboardIsIsolatedPerOrganization(): void
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, 'dashboard-tenant-a@example.test');
        $this->markEmailVerified('dashboard-tenant-a@example.test');
        $tokenB = $this->loginAs($client, 'dashboard-tenant-b@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $this->configureFiscalContext($client);
        $this->createAnalyzedInvoice($client, null, 'tenant-a');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('GET', '/api/v1/dashboard');
        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];

        self::assertSame('AUCUNE_ANALYSE', $data['global_status'], 'Le problème de l\'organisation A ne doit jamais fuiter vers B.');
    }
}
