<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Support\ApiTestCase;

final class UpdateCurrentUserControllerTest extends ApiTestCase
{
    private const string PASSWORD = 'a-very-long-password-1234';

    public function testUpdateEmailSucceedsAndRequiresReverification(): void
    {
        $client = $this->createAuthenticatedClient('settings-email@example.test', self::PASSWORD);
        $this->markEmailVerified('settings-email@example.test');

        $client->jsonRequest('PATCH', '/api/v1/users/current', [
            'email' => 'settings-email-new@example.test',
            'current_password' => self::PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client);
        self::assertSame('settings-email-new@example.test', $body['data']['email']);
        self::assertNull($body['data']['email_verified_at']);
    }

    public function testUpdateEmailWithWrongCurrentPasswordFails(): void
    {
        $client = $this->createAuthenticatedClient('settings-email-wrong-pw@example.test', self::PASSWORD);

        $client->jsonRequest('PATCH', '/api/v1/users/current', [
            'email' => 'settings-email-wrong-pw-new@example.test',
            'current_password' => 'not-the-right-password',
        ]);

        self::assertResponseStatusCodeSame(422);
        $body = $this->jsonBody($client);
        self::assertSame('current_password', $body['error']['details'][0]['field']);
    }

    public function testUpdateEmailWithDuplicateEmailFails(): void
    {
        $client = $this->createAuthenticatedClient('settings-email-owner@example.test', self::PASSWORD);

        // Deuxième compte créé via un appel HTTP réel (pas registerUser()) : évite de
        // mélanger un accès direct au conteneur (static::getContainer()) avec des cycles de
        // requête HTTP sur le même client, comme partout ailleurs dans ce fichier.
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'settings-email-taken@example.test',
            'password' => self::PASSWORD,
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('PATCH', '/api/v1/users/current', [
            'email' => 'settings-email-taken@example.test',
            'current_password' => self::PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testUpdatePasswordSucceedsAndRevokesRefreshTokens(): void
    {
        $client = $this->createAuthenticatedClient('settings-password@example.test', self::PASSWORD);

        $client->jsonRequest('PATCH', '/api/v1/users/current', [
            'current_password' => self::PASSWORD,
            'new_password' => 'a-brand-new-long-password-5678',
        ]);
        self::assertResponseStatusCodeSame(200);

        $client->jsonRequest('POST', '/api/v1/auth/refresh', [], ['HTTP_ORIGIN' => 'http://localhost:8080']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testUpdatePasswordTooShortFails(): void
    {
        $client = $this->createAuthenticatedClient('settings-password-short@example.test', self::PASSWORD);

        $client->jsonRequest('PATCH', '/api/v1/users/current', [
            'current_password' => self::PASSWORD,
            'new_password' => 'tooshort',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateWithoutCurrentPasswordFails(): void
    {
        $client = $this->createAuthenticatedClient('settings-missing-current@example.test', self::PASSWORD);

        $client->jsonRequest('PATCH', '/api/v1/users/current', [
            'email' => 'settings-missing-current-new@example.test',
        ]);

        self::assertResponseStatusCodeSame(422);
        $body = $this->jsonBody($client);
        self::assertSame('current_password', $body['error']['details'][0]['field']);
    }

    public function testUpdateWithNoChangesFails(): void
    {
        $client = $this->createAuthenticatedClient('settings-no-changes@example.test', self::PASSWORD);

        $client->jsonRequest('PATCH', '/api/v1/users/current', []);

        self::assertResponseStatusCodeSame(422);
    }
}
