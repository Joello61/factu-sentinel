<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Support\ApiTestCase;

final class DeleteCurrentUserControllerTest extends ApiTestCase
{
    private const string PASSWORD = 'a-very-long-password-1234';

    public function testDeleteAccountSucceeds(): void
    {
        $client = $this->createAuthenticatedClient('settings-delete@example.test', self::PASSWORD);

        $client->jsonRequest('DELETE', '/api/v1/users/current', ['current_password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(204);
    }

    public function testLoginFailsAfterDeletion(): void
    {
        $client = $this->createAuthenticatedClient('settings-delete-login@example.test', self::PASSWORD);
        $client->jsonRequest('DELETE', '/api/v1/users/current', ['current_password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(204);

        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        $client->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'settings-delete-login@example.test',
            'password' => self::PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAccessTokenRejectedOnNextRequestAfterDeletion(): void
    {
        $client = $this->createAuthenticatedClient('settings-delete-token@example.test', self::PASSWORD);
        $client->jsonRequest('DELETE', '/api/v1/users/current', ['current_password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(204);

        // Le même client conserve le même en-tête Authorization (jeton émis avant la
        // suppression) : la requête suivante doit être rejetée dès que le provider recharge
        // l'utilisateur (voir plan Phase 13, UserRepository::findOneByEmail exclut deletedAt).
        $client->jsonRequest('GET', '/api/v1/users/current');

        self::assertResponseStatusCodeSame(401);
    }

    public function testDeleteWithWrongCurrentPasswordFails(): void
    {
        $client = $this->createAuthenticatedClient('settings-delete-wrong-pw@example.test', self::PASSWORD);

        $client->jsonRequest('DELETE', '/api/v1/users/current', ['current_password' => 'not-the-right-password']);

        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('GET', '/api/v1/users/current');
        self::assertResponseStatusCodeSame(200);
    }

    public function testDeletedEmailBecomesReusableForRegistration(): void
    {
        $client = $this->createAuthenticatedClient('settings-delete-reuse@example.test', self::PASSWORD);
        $client->jsonRequest('DELETE', '/api/v1/users/current', ['current_password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(204);

        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'settings-delete-reuse@example.test',
            'password' => self::PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(201);
    }
}
