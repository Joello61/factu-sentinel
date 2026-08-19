<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Support\ApiTestCase;

final class RegisterControllerTest extends ApiTestCase
{
    public function testSuccessfulRegistration(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'new-user@example.test',
            'password' => 'a-sufficiently-long-password-123',
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $this->jsonBody($client);
        self::assertSame('new-user@example.test', $body['data']['email']);
    }

    public function testRegistrationWithAlreadyUsedEmailFails(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'duplicate@example.test',
            'password' => 'a-sufficiently-long-password-123',
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'duplicate@example.test',
            'password' => 'another-long-enough-password-456',
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testRegistrationWithTooShortPasswordFails(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'short-password@example.test',
            'password' => 'tooshort',
        ]);

        self::assertResponseStatusCodeSame(422);
        $body = $this->jsonBody($client);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
    }
}
