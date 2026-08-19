<?php

declare(strict_types=1);

namespace App\Tests\Functional\Compliance;

use App\Tests\Support\ApiTestCase;

final class EligibilityDiagnosticControllerTest extends ApiTestCase
{
    public function testReturns404BeforeOrganizationIsConfigured(): void
    {
        $client = $this->createAuthenticatedClient('diagnostic-001@example.test');

        $client->jsonRequest('GET', '/api/v1/eligibility-diagnostics/current');

        self::assertResponseStatusCodeSame(404);
    }

    public function testReturnsLatestDiagnosticAfterConfiguration(): void
    {
        $client = $this->createAuthenticatedClient('diagnostic-002@example.test');

        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_REDEVABLE',
                'employees_count' => 5000,
                'annual_turnover' => '2000000000',
                'annual_balance_sheet_total' => '2100000000',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        $client->jsonRequest('GET', '/api/v1/eligibility-diagnostics/current');

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame('2026-09-01', $data['reception_obligation_date']);
        self::assertSame('2026-09-01', $data['emission_obligation_date']);
        self::assertNotEmpty($data['explanation']);
    }
}
