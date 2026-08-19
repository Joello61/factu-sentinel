<?php

declare(strict_types=1);

namespace App\Tests\Functional\Organization;

use App\Tests\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class UpdateOrganizationControllerTest extends ApiTestCase
{
    public function testFirstConfigurationComputesDiagnostic(): void
    {
        $client = $this->createAuthenticatedClient('org-update-001@example.test');

        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'legal_name' => 'Atelier Test SARL',
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_FRANCHISE_EN_BASE',
                'employees_count' => 5,
                'annual_turnover' => '200000',
                'annual_balance_sheet_total' => '150000',
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];

        self::assertTrue($data['configured']);
        self::assertSame('PME_TPE_MICRO', $data['fiscal_context']['company_size_category']);
        self::assertSame('2026-09-01', $data['eligibility_diagnostic']['reception_obligation_date']);
        self::assertSame('2027-09-01', $data['eligibility_diagnostic']['emission_obligation_date']);
        self::assertStringContainsString('franchise en base', $data['eligibility_diagnostic']['explanation']);
    }

    public function testPartialUpdateMergesWithExistingContext(): void
    {
        $client = $this->createAuthenticatedClient('org-update-002@example.test');

        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_FRANCHISE_EN_BASE',
                'employees_count' => 5,
                'annual_turnover' => '200000',
                'annual_balance_sheet_total' => '150000',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        // Franchissement du seuil PME : seuls employees_count/annual_turnover changent,
        // annual_balance_sheet_total et vat_status doivent être repris de l'existant.
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => [
                'employees_count' => 300,
                'annual_turnover' => '60000000',
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame('ASSUJETTI_FRANCHISE_EN_BASE', $data['fiscal_context']['vat_status']);
        self::assertSame('GRANDE_ENTREPRISE_ETI', $data['fiscal_context']['company_size_category']);
        self::assertSame('2026-09-01', $data['eligibility_diagnostic']['emission_obligation_date']);
    }

    public function testPartialUpdateClosesPreviousFiscalContextRatherThanOverwriting(): void
    {
        $client = $this->createAuthenticatedClient('org-update-003@example.test');

        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_FRANCHISE_EN_BASE',
                'employees_count' => 5,
                'annual_turnover' => '200000',
                'annual_balance_sheet_total' => '150000',
            ],
        ]);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => ['vat_status' => 'ASSUJETTI_REDEVABLE'],
        ]);

        $organizationId = $this->jsonBody($client)['data']['id'];

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $rows = $em->getConnection()->fetchAllAssociative(
            'SELECT vat_status, effective_until FROM fiscal_contexts WHERE organization_id = ? ORDER BY recorded_at ASC',
            [$organizationId],
        );

        self::assertCount(2, $rows, 'Historisation attendue : deux versions, jamais une mise à jour en place.');
        self::assertNotNull($rows[0]['effective_until'], 'La première version doit être fermée.');
        self::assertNull($rows[1]['effective_until'], 'La seconde version doit rester la version courante.');
    }

    public function testIncompleteFirstConfigurationFailsWithValidationError(): void
    {
        $client = $this->createAuthenticatedClient('org-update-004@example.test');

        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => ['employees_count' => 5],
        ]);

        self::assertResponseStatusCodeSame(422);
        $body = $this->jsonBody($client);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
        $fields = array_column($body['error']['details'], 'field');
        self::assertContains('vat_status', $fields);
        self::assertContains('annual_turnover', $fields);
        self::assertContains('annual_balance_sheet_total', $fields);
    }

    public function testNonAssujettiProducesNullDates(): void
    {
        $client = $this->createAuthenticatedClient('org-update-005@example.test');

        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => [
                'vat_status' => 'NON_ASSUJETTI',
                'employees_count' => 2,
                'annual_turnover' => '10000',
                'annual_balance_sheet_total' => '5000',
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        $diagnostic = $this->jsonBody($client)['data']['eligibility_diagnostic'];
        self::assertNull($diagnostic['reception_obligation_date']);
        self::assertNull($diagnostic['emission_obligation_date']);
    }

    public function testAuditLogEntriesAreWrittenForBothOrganizationAndDiagnostic(): void
    {
        $client = $this->createAuthenticatedClient('org-update-006@example.test');

        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'legal_name' => 'Atelier Audit SARL',
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_FRANCHISE_EN_BASE',
                'employees_count' => 5,
                'annual_turnover' => '200000',
                'annual_balance_sheet_total' => '150000',
            ],
        ]);
        $organizationId = $this->jsonBody($client)['data']['id'];

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $events = $em->getConnection()->fetchFirstColumn(
            'SELECT event_type FROM audit_log_entries WHERE organization_id = ? ORDER BY occurred_at ASC',
            [$organizationId],
        );

        self::assertContains('ORGANIZATION_UPDATED', $events);
        self::assertContains('ELIGIBILITY_DIAGNOSTIC_COMPUTED', $events);
    }

    /**
     * Exit Criteria Phase 2 étendu à ces nouvelles ressources (docs/12-roadmap.md) :
     * la configuration fiscale d'une organisation ne doit jamais fuiter vers une autre.
     */
    public function testTenantIsolationOnFiscalContext(): void
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, 'org-update-tenant-a@example.test');
        $tokenB = $this->loginAs($client, 'org-update-tenant-b@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_FRANCHISE_EN_BASE',
                'employees_count' => 5,
                'annual_turnover' => '200000',
                'annual_balance_sheet_total' => '150000',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('GET', '/api/v1/organizations/current');
        $dataB = $this->jsonBody($client)['data'];

        self::assertArrayNotHasKey('fiscal_context', $dataB, "L'organisation B ne doit jamais voir le contexte fiscal de l'organisation A.");
    }
}
