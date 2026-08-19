<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Support\ApiTestCase;

final class LogoutControllerTest extends ApiTestCase
{
    public function testLogoutInvalidatesTheRefreshToken(): void
    {
        $client = $this->createAuthenticatedClient('logout@example.test');

        $client->jsonRequest('POST', '/api/v1/auth/logout');
        self::assertResponseIsSuccessful();

        // Le refresh token a été révoqué côté serveur (invalidate_token_on_logout,
        // config/packages/security.yaml) : un rafraîchissement après logout échoue.
        $client->jsonRequest('POST', '/api/v1/auth/refresh', [], ['HTTP_ORIGIN' => 'http://localhost:8080']);
        self::assertResponseStatusCodeSame(401);
    }
}
