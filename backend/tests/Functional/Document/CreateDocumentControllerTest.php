<?php

declare(strict_types=1);

namespace App\Tests\Functional\Document;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * POST /documents (docs/08-api-specification.md, section 31 ; US-INVOICE-001 ; plan Phase 7).
 * "async" est 'in-memory://' en environnement de test (config/packages/messenger.yaml) : ces
 * tests vérifient l'upload synchrone (validation, statut HTTP, Idempotency-Key, régime de
 * statut de l'Invoice cible) - le traitement asynchrone réel est couvert par
 * tests/Integration/Document/ExtractDocumentContentHandlerTest.php.
 */
final class CreateDocumentControllerTest extends ApiTestCase
{
    private const string FIXTURES_DIR = __DIR__.'/../../Fixtures/Document';

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

    /** @param list<array{description: string, quantity: string, unit_price_ht: string, vat_rate: string}> $lines */
    private function createInvoice(KernelBrowser $client, string $customerId, array $lines = []): string
    {
        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'PRESTATION_SERVICE',
            'issue_date' => '2026-08-20',
            'currency' => 'EUR',
            'lines' => $lines,
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invoice-create-'.bin2hex(random_bytes(8))]);
        self::assertResponseStatusCodeSame(201);

        return $this->jsonBody($client)['data']['id'];
    }

    private function readyInvoiceLines(): array
    {
        return [['description' => 'Prestation', 'quantity' => '1', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20']];
    }

    /**
     * POST /invoices exige au moins une ligne (App\Invoicing\Http\CreateInvoiceRequest,
     * Assert\Count(min: 1)) - DRAFT n'est donc atteignable qu'en repassant par PATCH avec
     * "lines: []" (App\Invoicing\Http\UpdateInvoiceInput, sans cette contrainte), qui
     * redéclenche Invoice::refreshReadinessStatus().
     */
    private function createDraftInvoice(KernelBrowser $client, string $customerId): string
    {
        $invoiceId = $this->createInvoice($client, $customerId, $this->readyInvoiceLines());

        $client->jsonRequest('PATCH', sprintf('/api/v1/invoices/%s', $invoiceId), ['lines' => []], [
            'HTTP_IF_MATCH' => $this->currentEtag($client, $invoiceId),
        ]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('DRAFT', $this->jsonBody($client)['data']['status']);

        return $invoiceId;
    }

    private function currentEtag(KernelBrowser $client, string $invoiceId): string
    {
        $client->request('GET', sprintf('/api/v1/invoices/%s', $invoiceId));
        $etag = $client->getResponse()->headers->get('ETag');
        \assert(is_string($etag));

        return $etag;
    }

    private function upload(KernelBrowser $client, string $invoiceId, string $fixture, string $idempotencyKey, ?string $mimeType = 'application/pdf'): void
    {
        $file = new UploadedFile(self::FIXTURES_DIR.'/'.$fixture, $fixture, $mimeType, null, true);

        $client->request(
            'POST',
            '/api/v1/documents',
            ['invoice_id' => $invoiceId],
            ['file' => $file],
            ['HTTP_IDEMPOTENCY_KEY' => $idempotencyKey],
        );
    }

    public function testMissingIdempotencyKeyReturns400(): void
    {
        $client = $this->createAuthenticatedClient('doc-create-001@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId, $this->readyInvoiceLines());

        $file = new UploadedFile(self::FIXTURES_DIR.'/pdf-simple.pdf', 'pdf-simple.pdf', 'application/pdf', null, true);
        $client->request('POST', '/api/v1/documents', ['invoice_id' => $invoiceId], ['file' => $file]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testUploadOnDraftInvoiceIsAccepted(): void
    {
        $client = $this->createAuthenticatedClient('doc-create-002@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createDraftInvoice($client, $customerId);

        $this->upload($client, $invoiceId, 'pdf-simple.pdf', 'doc-key-draft-001');

        self::assertResponseStatusCodeSame(202);
        $data = $this->jsonBody($client)['data'];
        self::assertSame('UPLOADED', $data['processing_status']);
        self::assertSame($invoiceId, $data['invoice_id']);
    }

    /**
     * Corrigé après revue (plan Phase 7, décision 1) : READY_FOR_ANALYSIS est le parcours
     * nominal de docs/11-frontend-design-system.md section 33 (étape Documents après
     * Lignes/Totaux) - protège explicitement contre une régression vers "DRAFT only".
     */
    public function testUploadOnReadyForAnalysisInvoiceIsAccepted(): void
    {
        $client = $this->createAuthenticatedClient('doc-create-003@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId, $this->readyInvoiceLines());

        $this->upload($client, $invoiceId, 'pdf-simple.pdf', 'doc-key-ready-001');

        self::assertResponseStatusCodeSame(202);
    }

    public function testUploadOnAnalyzedInvoiceIsRejected(): void
    {
        $client = $this->createAuthenticatedClient('doc-create-004@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId, $this->readyInvoiceLines());

        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'analysis-key-001']);
        self::assertResponseStatusCodeSame(200);

        $this->upload($client, $invoiceId, 'pdf-simple.pdf', 'doc-key-analyzed-001');

        self::assertResponseStatusCodeSame(409);
    }

    public function testCrossTenantInvoiceReturns404(): void
    {
        // Organisation A : crée l'Invoice cible.
        $client = $this->createAuthenticatedClient('doc-create-005-a@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId, $this->readyInvoiceLines());

        // Organisation B, sur le même client de test (createAuthenticatedClient() ne peut
        // être appelé qu'une fois par test, voir App\Tests\Support\ApiTestCase::loginAs()) :
        // tente d'uploader sur l'Invoice de A.
        $tokenB = $this->loginAs($client, 'doc-create-005-b@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);

        $this->upload($client, $invoiceId, 'pdf-simple.pdf', 'doc-key-tenant-001');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOversizedFileReturns413(): void
    {
        $client = $this->createAuthenticatedClient('doc-create-006@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId, $this->readyInvoiceLines());

        $oversizedPath = sys_get_temp_dir().'/factusentinel-oversized.pdf';
        file_put_contents($oversizedPath, str_repeat('A', 21 * 1024 * 1024));

        $file = new UploadedFile($oversizedPath, 'oversized.pdf', 'application/pdf', null, true);
        $client->request('POST', '/api/v1/documents', ['invoice_id' => $invoiceId], ['file' => $file], ['HTTP_IDEMPOTENCY_KEY' => 'doc-key-oversized-001']);

        self::assertResponseStatusCodeSame(413);

        unlink($oversizedPath);
    }

    /** SEC-DOC-001 : extension falsifiée, détectée par inspection du contenu réel. */
    public function testSpoofedExtensionReturns422(): void
    {
        $client = $this->createAuthenticatedClient('doc-create-007@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId, $this->readyInvoiceLines());

        $spoofedPath = sys_get_temp_dir().'/factusentinel-spoofed.pdf';
        file_put_contents($spoofedPath, 'this is definitely not a PDF file');

        $file = new UploadedFile($spoofedPath, 'not-a-pdf.pdf', 'application/pdf', null, true);
        $client->request('POST', '/api/v1/documents', ['invoice_id' => $invoiceId], ['file' => $file], ['HTTP_IDEMPOTENCY_KEY' => 'doc-key-spoofed-001']);

        self::assertResponseStatusCodeSame(422);

        unlink($spoofedPath);
    }

    public function testIdempotencyKeyReplayReturnsSameDocumentOnce(): void
    {
        $client = $this->createAuthenticatedClient('doc-create-008@example.test');
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId, $this->readyInvoiceLines());

        $this->upload($client, $invoiceId, 'pdf-simple.pdf', 'doc-key-replay-001');
        self::assertResponseStatusCodeSame(202);
        $firstDocumentId = $this->jsonBody($client)['data']['id'];

        $this->upload($client, $invoiceId, 'pdf-simple.pdf', 'doc-key-replay-001');
        self::assertResponseStatusCodeSame(202);
        $secondDocumentId = $this->jsonBody($client)['data']['id'];

        self::assertSame($firstDocumentId, $secondDocumentId);
    }

    public function testMissingInvoiceIdReturns422(): void
    {
        $client = $this->createAuthenticatedClient('doc-create-009@example.test');
        $this->configureFiscalContext($client);

        $file = new UploadedFile(self::FIXTURES_DIR.'/pdf-simple.pdf', 'pdf-simple.pdf', 'application/pdf', null, true);
        $client->request('POST', '/api/v1/documents', [], ['file' => $file], ['HTTP_IDEMPOTENCY_KEY' => 'doc-key-no-invoice-001']);

        self::assertResponseStatusCodeSame(422);
    }
}
