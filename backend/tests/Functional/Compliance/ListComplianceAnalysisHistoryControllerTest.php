<?php

declare(strict_types=1);

namespace App\Tests\Functional\Compliance;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * GET /compliance-analyses (docs/08-api-specification.md, section 29 bis ; US-HISTORY-001) :
 * historique organisation-wide, distinct de GET /invoices/{id}/compliance-analyses (déjà
 * couvert par App\Tests\Functional\Compliance\GetComplianceAnalysisControllerTest).
 */
final class ListComplianceAnalysisHistoryControllerTest extends ApiTestCase
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

    /** @return array{invoiceId: string, analysisId: string, globalResult: string} */
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
            'invoice_number' => 'HIST-'.$idempotencySuffix,
            'operation_type' => 'PRESTATION_SERVICE',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Prestation', 'quantity' => '1', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'history-invoice-'.$idempotencySuffix]);
        $invoiceId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'history-analysis-'.$idempotencySuffix]);
        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client)['data'];

        return ['invoiceId' => $invoiceId, 'analysisId' => $body['id'], 'globalResult' => $body['global_result']];
    }

    public function testListReturnsInvoiceNumberAndPaginationAcrossInvoices(): void
    {
        $client = $this->createAuthenticatedClient('history-list@example.test');
        $this->configureFiscalContext($client);

        $first = $this->createAnalyzedInvoice($client, null, 'a');
        $second = $this->createAnalyzedInvoice($client, '123456789', 'b');

        $client->jsonRequest('GET', '/api/v1/compliance-analyses');
        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client);

        self::assertSame(2, $body['meta']['pagination']['total_count']);
        self::assertSame($second['analysisId'], $body['data'][0]['id'], 'La plus récente en premier.');
        self::assertSame($first['analysisId'], $body['data'][1]['id']);
        self::assertSame('HIST-b', $body['data'][0]['invoice_number']);
        self::assertSame($second['invoiceId'], $body['data'][0]['invoice_id']);
    }

    public function testFilterByGlobalResult(): void
    {
        $client = $this->createAuthenticatedClient('history-filter@example.test');
        $this->configureFiscalContext($client);

        $nonConforme = $this->createAnalyzedInvoice($client, null, 'nc');
        $this->createAnalyzedInvoice($client, '123456789', 'ok');

        self::assertSame('NON_CONFORME', $nonConforme['globalResult']);

        $client->jsonRequest('GET', '/api/v1/compliance-analyses?global_result=NON_CONFORME');
        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client);

        self::assertSame(1, $body['meta']['pagination']['total_count']);
        self::assertSame($nonConforme['analysisId'], $body['data'][0]['id']);
    }

    public function testInvalidGlobalResultFilterIsIgnored(): void
    {
        $client = $this->createAuthenticatedClient('history-invalid-filter@example.test');
        $this->configureFiscalContext($client);
        $this->createAnalyzedInvoice($client, null, 'x');

        $client->jsonRequest('GET', '/api/v1/compliance-analyses?global_result=NOT_A_REAL_VALUE');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $this->jsonBody($client)['meta']['pagination']['total_count']);
    }

    public function testHistoryIsIsolatedPerOrganization(): void
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, 'history-tenant-a@example.test');
        $tokenB = $this->loginAs($client, 'history-tenant-b@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $this->configureFiscalContext($client);
        $this->createAnalyzedInvoice($client, null, 'tenant-a');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('GET', '/api/v1/compliance-analyses');
        self::assertResponseStatusCodeSame(200);

        self::assertSame(0, $this->jsonBody($client)['meta']['pagination']['total_count'], 'Aucune fuite cross-tenant.');
    }
}
