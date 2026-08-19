<?php

declare(strict_types=1);

namespace App\Tests\Functional\Compliance;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * GET /compliance-analyses/{id} et GET /compliance-analyses/{id}/findings
 * (docs/08-api-specification.md, section 29 ; US-COMPLIANCE-003/004).
 */
final class GetComplianceAnalysisControllerTest extends ApiTestCase
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

    private function createAnalyzedInvoice(KernelBrowser $client, string $suffix): string
    {
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
        ]);
        $invoiceId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'get-key-'.$suffix]);
        self::assertResponseStatusCodeSame(200);

        return $invoiceId;
    }

    public function testGetAnalysisReturnsFindings(): void
    {
        $client = $this->createAuthenticatedClient('compliance-get-001@example.test');
        $invoiceId = $this->createAnalyzedInvoice($client, '001');

        $client->jsonRequest('GET', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId));
        $analysisId = $this->jsonBody($client)['data'][0]['id'];

        $client->jsonRequest('GET', '/api/v1/compliance-analyses/'.$analysisId);
        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame($analysisId, $data['id']);
        self::assertSame('COMPLETED', $data['status']);
        self::assertNotEmpty($data['findings']);
    }

    public function testFindingsEndpointMatchesEmbeddedFindings(): void
    {
        $client = $this->createAuthenticatedClient('compliance-get-002@example.test');
        $invoiceId = $this->createAnalyzedInvoice($client, '002');

        $client->jsonRequest('GET', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId));
        $analysisId = $this->jsonBody($client)['data'][0]['id'];

        $client->jsonRequest('GET', '/api/v1/compliance-analyses/'.$analysisId);
        $embedded = $this->jsonBody($client)['data']['findings'];

        $client->jsonRequest('GET', '/api/v1/compliance-analyses/'.$analysisId.'/findings');
        self::assertResponseStatusCodeSame(200);
        $dedicated = $this->jsonBody($client)['data'];

        self::assertSame($embedded, $dedicated);
    }

    public function testUnknownAnalysisReturns404(): void
    {
        $client = $this->createAuthenticatedClient('compliance-get-003@example.test');

        $client->jsonRequest('GET', '/api/v1/compliance-analyses/00000000-0000-7000-8000-000000000000');
        self::assertResponseStatusCodeSame(404);

        $client->jsonRequest('GET', '/api/v1/compliance-analyses/00000000-0000-7000-8000-000000000000/findings');
        self::assertResponseStatusCodeSame(404);
    }

    public function testOtherTenantAnalysisReturns404(): void
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, 'compliance-get-tenant-a@example.test');
        $tokenB = $this->loginAs($client, 'compliance-get-tenant-b@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $invoiceId = $this->createAnalyzedInvoice($client, 'a');
        $client->jsonRequest('GET', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId));
        $analysisId = $this->jsonBody($client)['data'][0]['id'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('GET', '/api/v1/compliance-analyses/'.$analysisId);
        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('GET', '/api/v1/compliance-analyses/'.$analysisId.'/findings');
        self::assertResponseStatusCodeSame(404);
    }

    public function testListReturnsAnalysesNewestFirstWithoutErasingHistory(): void
    {
        $client = $this->createAuthenticatedClient('compliance-get-004@example.test');
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
        ]);
        $invoiceId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'list-key-1']);
        self::assertResponseStatusCodeSame(200);
        $firstAnalysisId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'list-key-2']);
        self::assertResponseStatusCodeSame(200);
        $secondAnalysisId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('GET', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId));
        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client);
        self::assertCount(2, $body['data'], 'US-COMPLIANCE-006 : les deux analyses restent consultables, jamais écrasées.');
        self::assertSame($secondAnalysisId, $body['data'][0]['id'], 'La plus récente en premier.');
        self::assertSame($firstAnalysisId, $body['data'][1]['id']);
        self::assertSame(2, $body['meta']['pagination']['total_count']);
    }
}
