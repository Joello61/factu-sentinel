<?php

declare(strict_types=1);

namespace App\Tests\Functional\Invoicing;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class CreateInvoiceControllerTest extends ApiTestCase
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

    public function testCreateInvoiceWithMultipleVatRatesComputesServerSideAmounts(): void
    {
        $client = $this->createAuthenticatedClient('invoice-create-001@example.test');
        $customerId = $this->createCustomer($client);

        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'PRESTATION_SERVICE',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [
                ['description' => 'Prestation A', 'quantity' => '1', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20'],
                ['description' => 'Prestation B', 'quantity' => '2', 'unit_price_ht' => '50.00', 'vat_rate' => '0.055'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->jsonBody($client)['data'];

        self::assertSame('READY_FOR_ANALYSIS', $data['status']);
        self::assertSame('SAISIE_MANUELLE', $data['source']);
        self::assertSame('200.00', $data['total_amount_ht']);
        self::assertSame('225.50', $data['total_amount_ttc']);
        self::assertCount(2, $data['lines']);
        self::assertNotNull($client->getResponse()->headers->get('ETag'));
    }

    /**
     * docs/08-api-specification.md, section 27 : l'exemple de requête n'inclut jamais
     * line_amount_ht/vat/ttc, ils sont toujours calculés côté serveur. Un client malveillant
     * fournissant des montants ne doit jamais les voir repris tels quels.
     */
    public function testClientSuppliedLineAmountsAreIgnored(): void
    {
        $client = $this->createAuthenticatedClient('invoice-create-002@example.test');
        $customerId = $this->createCustomer($client);

        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [[
                'description' => 'Ligne falsifiée',
                'quantity' => '1',
                'unit_price_ht' => '10.00',
                'vat_rate' => '0.20',
                'line_amount_ht' => '999999.99',
                'line_amount_vat' => '0.00',
                'line_amount_ttc' => '999999.99',
            ]],
        ]);

        self::assertResponseStatusCodeSame(201);
        $line = $this->jsonBody($client)['data']['lines'][0];
        self::assertSame('10.00', $line['line_amount_ht']);
        self::assertSame('12.00', $line['line_amount_ttc']);
    }

    public function testUnknownCustomerIdReturns404(): void
    {
        $client = $this->createAuthenticatedClient('invoice-create-003@example.test');

        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => '00000000-0000-7000-8000-000000000000',
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Ligne', 'quantity' => '1', 'unit_price_ht' => '10.00', 'vat_rate' => '0.20']],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testOtherTenantCustomerIdReturns404(): void
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, 'invoice-create-tenant-a@example.test');
        $tokenB = $this->loginAs($client, 'invoice-create-tenant-b@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $customerAId = $this->createCustomer($client);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerAId,
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Ligne', 'quantity' => '1', 'unit_price_ht' => '10.00', 'vat_rate' => '0.20']],
        ]);

        self::assertResponseStatusCodeSame(404, 'Invoicing.customer_id ne doit jamais traverser deux organization_id (docs/07-data-model.md, section 28).');
    }

    public function testEmptyLinesFailsValidation(): void
    {
        $client = $this->createAuthenticatedClient('invoice-create-004@example.test');
        $customerId = $this->createCustomer($client);

        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testNonPositiveQuantityFailsValidation(): void
    {
        $client = $this->createAuthenticatedClient('invoice-create-005@example.test');
        $customerId = $this->createCustomer($client);

        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Ligne', 'quantity' => '0', 'unit_price_ht' => '10.00', 'vat_rate' => '0.20']],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUniqueInvoiceNumberPerOrganizationIsEnforced(): void
    {
        $client = $this->createAuthenticatedClient('invoice-create-006@example.test');
        $customerId = $this->createCustomer($client);

        $payload = [
            'customer_id' => $customerId,
            'invoice_number' => 'FA-2026-001',
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Ligne', 'quantity' => '1', 'unit_price_ht' => '10.00', 'vat_rate' => '0.20']],
        ];

        $client->jsonRequest('POST', '/api/v1/invoices', $payload);
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('POST', '/api/v1/invoices', $payload);
        self::assertResponseStatusCodeSame(409, 'Contrainte unique (organization_id, invoice_number) : voir docs/07-data-model.md section 28.');
    }
}
