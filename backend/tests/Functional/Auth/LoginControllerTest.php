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

    /**
     * Régression : security.yaml (login_throttling, max_attempts: 5) lève une
     * TooManyLoginAttemptsAuthenticationException à la 6e tentative, mais
     * AuthFailureEnvelopeListener écrasait silencieusement cette exception par un 401
     * générique identique à un échec d'identifiants ordinaire - le rate limiting de
     * connexion était donc actif côté Symfony sans jamais être visible ni opposable en
     * pratique (constaté lors de l'implémentation de la Phase 12,
     * docs/10-security-privacy.md section 36).
     */
    public function testRepeatedFailedLoginsAreThrottledWith429(): void
    {
        $client = static::createClient();

        for ($i = 0; $i < 5; ++$i) {
            $client->jsonRequest('POST', '/api/v1/auth/login', [
                'email' => 'throttle-login-test@example.test',
                'password' => 'wrong-password-'.$i,
            ]);
            self::assertResponseStatusCodeSame(401);
        }

        $client->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'throttle-login-test@example.test',
            'password' => 'wrong-password-again',
        ]);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');

        $body = $this->jsonBody($client);
        self::assertStringNotContainsStringIgnoringCase('password', $body['error']['message']);
        self::assertStringNotContainsStringIgnoringCase('user', $body['error']['message']);
    }
}
