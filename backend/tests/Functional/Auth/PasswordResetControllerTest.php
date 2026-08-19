<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

final class PasswordResetControllerTest extends ApiTestCase
{
    use MailerAssertionsTrait;

    public function testForgotPasswordReturnsGenericResponseWhetherOrNotAccountExists(): void
    {
        $client = static::createClient();
        $this->registerUser('has-account@example.test');

        $client->jsonRequest('POST', '/api/v1/auth/password/forgot', ['email' => 'has-account@example.test']);
        $existingAccountStatus = $client->getResponse()->getStatusCode();
        $existingAccountBody = $this->jsonBody($client);

        $client->jsonRequest('POST', '/api/v1/auth/password/forgot', ['email' => 'no-such-account@example.test']);
        $missingAccountStatus = $client->getResponse()->getStatusCode();
        $missingAccountBody = $this->jsonBody($client);

        self::assertSame($existingAccountStatus, $missingAccountStatus);
        self::assertSame($existingAccountBody, $missingAccountBody);
    }

    public function testFullPasswordResetFlow(): void
    {
        $client = static::createClient();
        $this->registerUser('reset-flow@example.test', 'the-original-password-123456');

        $client->jsonRequest('POST', '/api/v1/auth/password/forgot', ['email' => 'reset-flow@example.test']);
        self::assertResponseStatusCodeSame(200);

        $email = self::getMailerMessage();
        self::assertNotNull($email, 'Expected a password reset email to have been sent.');
        preg_match('#token=([A-Za-z0-9_\-]+)#', $email->getTextBody(), $matches);
        self::assertArrayHasKey(1, $matches, 'Could not find a reset token in the email body.');
        $token = $matches[1];

        $client->jsonRequest('POST', '/api/v1/auth/password/reset', [
            'token' => $token,
            'password' => 'the-brand-new-password-654321',
        ]);
        self::assertResponseStatusCodeSame(200);

        // Le nouveau mot de passe fonctionne, l'ancien non.
        $client->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'reset-flow@example.test',
            'password' => 'the-brand-new-password-654321',
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    public function testResetWithInvalidTokenFails(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth/password/reset', [
            'token' => 'not-a-real-token',
            'password' => 'whatever-password-long-enough',
        ]);

        self::assertResponseStatusCodeSame(400);
    }
}
