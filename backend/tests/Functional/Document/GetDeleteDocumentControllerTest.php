<?php

declare(strict_types=1);

namespace App\Tests\Functional\Document;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * GET /documents/{id}, GET /documents/{id}/content, DELETE /documents/{id}
 * (docs/08-api-specification.md, section 31 ; US-DOCUMENT-001/002).
 */
final class GetDeleteDocumentControllerTest extends ApiTestCase
{
    private const string FIXTURES_DIR = __DIR__.'/../../Fixtures/Document';

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

    private function createInvoice(KernelBrowser $client, string $customerId): string
    {
        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'PRESTATION_SERVICE',
            'issue_date' => '2026-08-20',
            'currency' => 'EUR',
            'lines' => [['description' => 'Prestation', 'quantity' => '1', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invoice-create-'.bin2hex(random_bytes(8))]);
        self::assertResponseStatusCodeSame(201);

        return $this->jsonBody($client)['data']['id'];
    }

    private function uploadDocument(KernelBrowser $client, string $invoiceId, string $fixture = 'pdf-simple.pdf'): string
    {
        $file = new UploadedFile(self::FIXTURES_DIR.'/'.$fixture, $fixture, 'application/pdf', null, true);
        $client->request(
            'POST',
            '/api/v1/documents',
            ['invoice_id' => $invoiceId],
            ['file' => $file],
            ['HTTP_IDEMPOTENCY_KEY' => 'doc-create-'.bin2hex(random_bytes(8))],
        );
        self::assertResponseStatusCodeSame(202);

        return $this->jsonBody($client)['data']['id'];
    }

    public function testGetReturnsDocumentMetadata(): void
    {
        $client = $this->createAuthenticatedClient('doc-get-001@example.test');
        $this->markEmailVerified('doc-get-001@example.test');
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);
        $documentId = $this->uploadDocument($client, $invoiceId);

        $client->request('GET', sprintf('/api/v1/documents/%s', $documentId));

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame($documentId, $data['id']);
        self::assertSame($invoiceId, $data['invoice_id']);
        self::assertSame('pdf-simple.pdf', $data['file_name']);
    }

    public function testGetUnknownDocumentReturns404(): void
    {
        $client = $this->createAuthenticatedClient('doc-get-002@example.test');

        $client->request('GET', '/api/v1/documents/00000000-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetCrossTenantDocumentReturns404(): void
    {
        $client = $this->createAuthenticatedClient('doc-get-003-a@example.test');
        $this->markEmailVerified('doc-get-003-a@example.test');
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);
        $documentId = $this->uploadDocument($client, $invoiceId);

        $tokenB = $this->loginAs($client, 'doc-get-003-b@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);

        $client->request('GET', sprintf('/api/v1/documents/%s', $documentId));

        self::assertResponseStatusCodeSame(404, 'Un document d\'une autre organisation ne doit jamais être confirmé par un 403 (backend/CLAUDE.md, section 6).');
    }

    public function testGetContentReturnsTheOriginalFileAsOctetStream(): void
    {
        $client = $this->createAuthenticatedClient('doc-get-004@example.test');
        $this->markEmailVerified('doc-get-004@example.test');
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);
        $documentId = $this->uploadDocument($client, $invoiceId);

        $client->request('GET', sprintf('/api/v1/documents/%s/content', $documentId));

        self::assertResponseStatusCodeSame(200);
        $response = $client->getResponse();
        self::assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('pdf-simple.pdf', (string) $response->headers->get('Content-Disposition'));
        self::assertSame(
            file_get_contents(self::FIXTURES_DIR.'/pdf-simple.pdf'),
            $response->getContent(),
            'Le contenu servi doit être identique au fichier importé, jamais altéré.',
        );
    }

    public function testGetContentCrossTenantReturns404(): void
    {
        $client = $this->createAuthenticatedClient('doc-get-005-a@example.test');
        $this->markEmailVerified('doc-get-005-a@example.test');
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);
        $documentId = $this->uploadDocument($client, $invoiceId);

        $tokenB = $this->loginAs($client, 'doc-get-005-b@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);

        $client->request('GET', sprintf('/api/v1/documents/%s/content', $documentId));

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Régime de suppression mixte (docs/07-data-model.md, section 30) : le fichier physique
     * disparaît, extracted_data_summary est vidé, mais la ligne Document/DocumentProcessingRecord
     * et l'historique d'audit restent - jamais une suppression physique complète.
     */
    public function testDeleteRemovesFileButKeepsAuditableRecord(): void
    {
        $client = $this->createAuthenticatedClient('doc-delete-001@example.test');
        $this->markEmailVerified('doc-delete-001@example.test');
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);
        $documentId = $this->uploadDocument($client, $invoiceId);

        $client->request('DELETE', sprintf('/api/v1/documents/%s', $documentId));
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', sprintf('/api/v1/documents/%s', $documentId));
        self::assertResponseStatusCodeSame(404, 'Un document supprimé ne doit plus être résolvable via GET (même convention que Customer, docs/07-data-model.md section 30).');

        $client->request('GET', sprintf('/api/v1/documents/%s/content', $documentId));
        self::assertResponseStatusCodeSame(404, 'Le fichier a été physiquement supprimé : plus rien à télécharger.');
    }

    public function testDeleteUnknownDocumentReturns404(): void
    {
        $client = $this->createAuthenticatedClient('doc-delete-002@example.test');

        $client->request('DELETE', '/api/v1/documents/00000000-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteCrossTenantDocumentReturns404AndDoesNotDeleteIt(): void
    {
        $client = $this->createAuthenticatedClient('doc-delete-003-a@example.test');
        $this->markEmailVerified('doc-delete-003-a@example.test');
        $customerId = $this->createCustomer($client);
        $invoiceId = $this->createInvoice($client, $customerId);
        $documentId = $this->uploadDocument($client, $invoiceId);
        $tokenA = (string) $client->getServerParameter('HTTP_AUTHORIZATION');

        $tokenB = $this->loginAs($client, 'doc-delete-003-b@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->request('DELETE', sprintf('/api/v1/documents/%s', $documentId));
        self::assertResponseStatusCodeSame(404);

        // Toujours visible pour son organisation propriétaire : la tentative de
        // l'organisation B n'a rien supprimé.
        $client->setServerParameter('HTTP_AUTHORIZATION', $tokenA);
        $client->request('GET', sprintf('/api/v1/documents/%s', $documentId));
        self::assertResponseStatusCodeSame(200);
    }
}
