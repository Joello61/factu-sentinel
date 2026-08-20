<?php

declare(strict_types=1);

namespace App\Tests\Functional\Invoicing;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * PATCH /invoices/{id} (docs/08-api-specification.md, section 21, 27-28 ; plan Phase 4,
 * décision D3 : verrouillage optimiste natif Doctrine, exposé en ETag/If-Match).
 */
final class UpdateInvoiceControllerTest extends ApiTestCase
{
    private function createCustomer(KernelBrowser $client): string
    {
        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client Test SARL',
            'siren' => '123456789',
            'country' => 'FR',
        ]);
        self::assertResponseStatusCodeSame(201);

        return $this->jsonBody($client)['data']['id'];
    }

    /** @return array{0: string, 1: string} [invoiceId, etag] */
    private function createInvoice(KernelBrowser $client, string $customerId): array
    {
        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Ligne 1', 'quantity' => '1', 'unit_price_ht' => '10.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invoice-create-'.bin2hex(random_bytes(8))]);
        self::assertResponseStatusCodeSame(201);

        $id = $this->jsonBody($client)['data']['id'];
        $etag = $client->getResponse()->headers->get('ETag');
        self::assertIsString($etag);

        return [$id, $etag];
    }

    public function testUpdateWithoutIfMatchReturns409(): void
    {
        $client = $this->createAuthenticatedClient('invoice-update-001@example.test');
        $customerId = $this->createCustomer($client);
        [$id] = $this->createInvoice($client, $customerId);

        $client->jsonRequest('PATCH', '/api/v1/invoices/'.$id, ['invoice_number' => 'FA-001']);

        self::assertResponseStatusCodeSame(409);
    }

    public function testUpdateWithStaleIfMatchReturns409(): void
    {
        $client = $this->createAuthenticatedClient('invoice-update-002@example.test');
        $customerId = $this->createCustomer($client);
        [$id, $etag] = $this->createInvoice($client, $customerId);

        $client->jsonRequest('PATCH', '/api/v1/invoices/'.$id, ['invoice_number' => 'FA-001'], ['HTTP_IF_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(200);

        // Rejoue le même ETag, désormais périmé (la première PATCH a incrémenté la version).
        $client->jsonRequest('PATCH', '/api/v1/invoices/'.$id, ['invoice_number' => 'FA-002'], ['HTTP_IF_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testUpdateWithValidIfMatchSucceedsAndIncrementsVersion(): void
    {
        $client = $this->createAuthenticatedClient('invoice-update-003@example.test');
        $customerId = $this->createCustomer($client);
        [$id, $etag] = $this->createInvoice($client, $customerId);

        $client->jsonRequest('PATCH', '/api/v1/invoices/'.$id, ['invoice_number' => 'FA-2026-042'], ['HTTP_IF_MATCH' => $etag]);

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame('FA-2026-042', $data['invoice_number']);

        $newEtag = $client->getResponse()->headers->get('ETag');
        self::assertNotSame($etag, $newEtag, "La version doit être incrémentée après une modification réussie.");
    }

    public function testReplacingLinesRecomputesTotals(): void
    {
        $client = $this->createAuthenticatedClient('invoice-update-004@example.test');
        $customerId = $this->createCustomer($client);
        [$id, $etag] = $this->createInvoice($client, $customerId);

        $client->jsonRequest('PATCH', '/api/v1/invoices/'.$id, [
            'lines' => [
                ['description' => 'Nouvelle ligne', 'quantity' => '2', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20'],
            ],
        ], ['HTTP_IF_MATCH' => $etag]);

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertCount(1, $data['lines']);
        self::assertSame('200.00', $data['total_amount_ht']);
        self::assertSame('240.00', $data['total_amount_ttc']);
    }

    public function testUpdateUnknownInvoiceReturns404(): void
    {
        $client = $this->createAuthenticatedClient('invoice-update-005@example.test');

        $client->jsonRequest(
            'PATCH',
            '/api/v1/invoices/00000000-0000-7000-8000-000000000000',
            ['invoice_number' => 'FA-001'],
            ['HTTP_IF_MATCH' => 'W/"1"'],
        );

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * US-COMPLIANCE-006bis (Phase 5) : modifier une facture ANALYZED la rend obsolète.
     * Ne peut être exercé qu'après une vraie ComplianceAnalysis (Phase 5), donc configuré
     * ici via PATCH /organizations/current + POST .../compliance-analyses plutôt que
     * simulé -- Invoice::markAnalyzed() n'est jamais appelée ailleurs qu'à l'issue d'une
     * analyse réelle.
     */
    public function testModifyingAnAnalyzedInvoiceMarksItStale(): void
    {
        $client = $this->createAuthenticatedClient('invoice-update-006@example.test');
        $this->markEmailVerified('invoice-update-006@example.test');
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_REDEVABLE',
                'employees_count' => 5,
                'annual_turnover' => '200000',
                'annual_balance_sheet_total' => '150000',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        $customerId = $this->createCustomer($client);
        [$id, $etag] = $this->createInvoice($client, $customerId);

        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $id), [], ['HTTP_IDEMPOTENCY_KEY' => 'stale-key-006']);
        self::assertResponseStatusCodeSame(200);

        $client->jsonRequest('GET', '/api/v1/invoices/'.$id);
        self::assertSame('ANALYZED', $this->jsonBody($client)['data']['status']);
        $analyzedEtag = $client->getResponse()->headers->get('ETag');
        self::assertIsString($analyzedEtag);

        $client->jsonRequest('PATCH', '/api/v1/invoices/'.$id, ['invoice_number' => 'FA-STALE-001'], ['HTTP_IF_MATCH' => $analyzedEtag]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('ANALYSIS_STALE', $this->jsonBody($client)['data']['status']);
    }

    public function testModifyingADraftInvoiceNeverProducesAnalysisStale(): void
    {
        $client = $this->createAuthenticatedClient('invoice-update-007@example.test');
        $customerId = $this->createCustomer($client);
        [$id, $etag] = $this->createInvoice($client, $customerId);

        $client->jsonRequest('PATCH', '/api/v1/invoices/'.$id, ['invoice_number' => 'FA-007'], ['HTTP_IF_MATCH' => $etag]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('READY_FOR_ANALYSIS', $this->jsonBody($client)['data']['status']);
    }
}
