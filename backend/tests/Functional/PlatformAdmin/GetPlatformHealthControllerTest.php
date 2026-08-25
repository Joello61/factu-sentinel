<?php

declare(strict_types=1);

namespace App\Tests\Functional\PlatformAdmin;

use App\Tests\Support\PlatformAdminApiTestCase;

/** US-PLATFORMADMIN-005 (docs/08-api-specification.md, section 38.2). */
final class GetPlatformHealthControllerTest extends PlatformAdminApiTestCase
{
    public function testHealthEndpointReturnsAllExpectedIndicators(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-health@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-health@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/health');
        self::assertResponseStatusCodeSame(200);

        $data = $this->jsonBody($client)['data'];
        self::assertArrayHasKey('compliance_engine_failure_rate_24h', $data);
        self::assertArrayHasKey('async_jobs_dead_letter_count', $data);
        self::assertArrayHasKey('ai_calls_volume_24h', $data);
        self::assertArrayHasKey('ai_estimated_cost_24h', $data);
        self::assertSame('ok', $data['api_health']);
    }
}
