<?php

declare(strict_types=1);

namespace App\Tests\Functional\PlatformAdmin;

use App\Tests\Support\PlatformAdminApiTestCase;

/** GET /platform-admin/me - écart comblé à l'implémentation (voir le contrôleur). */
final class GetCurrentPlatformAdministratorControllerTest extends PlatformAdminApiTestCase
{
    public function testReturnsTheAuthenticatedAdministratorsEmail(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-me@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-me@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/me');
        self::assertResponseStatusCodeSame(200);
        self::assertSame('admin-me@example.test', $this->jsonBody($client)['data']['email']);
    }

    public function testRejectsATenantToken(): void
    {
        $client = static::createClient();
        $this->registerUser('me-tenant@example.test');
        $token = $this->loginExisting($client, 'me-tenant@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/me');
        self::assertResponseStatusCodeSame(401);
    }
}
