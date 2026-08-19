<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Support\ApiTestCase;

final class LoginControllerTest extends ApiTestCase
{
    public function testSuccessfulLoginReturnsAccessTokenAndRefreshCookie(): void
    {
        $client = $this->createAuthenticatedClient('login-success@example.test');

        self::assertResponseStatusCodeSame(200);
        self::assertNotEmpty($client->getCookieJar()->all(), 'Expected a refresh token cookie to be set.');
    }

    public function testInvalidCredentialsFailWithNonSpecificMessage(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'does-not-exist@example.test',
            'password' => 'whatever-password-is-long-enough',
        ]);

        self::assertResponseStatusCodeSame(401);
        $body = $this->jsonBody($client);

        // US-AUTH-002 : jamais un message distinguant identifiant/mot de passe invalide.
        self::assertStringNotContainsStringIgnoringCase('password', $body['error']['message']);
        self::assertStringNotContainsStringIgnoringCase('user', $body['error']['message']);
    }
}
