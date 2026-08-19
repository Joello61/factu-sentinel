<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

final class VerifyEmailControllerTest extends ApiTestCase
{
    use MailerAssertionsTrait;

    public function testEmailVerificationDoesNotBlockBasicAccountUsage(): void
    {
        $client = $this->createAuthenticatedClient('unverified@example.test');

        // Non bloquant pour l'usage de base (docs/08-api-specification.md, section 7) :
        // un compte non vérifié peut consulter GET /users/current.
        $client->jsonRequest('GET', '/api/v1/users/current');

        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client);
        self::assertNull($body['data']['email_verified_at']);
    }

    public function testValidVerificationLinkMarksEmailAsVerified(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'verify-me@example.test',
            'password' => 'a-sufficiently-long-password-123',
        ]);
        self::assertResponseStatusCodeSame(201);

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        preg_match('#(https?://\S+/verify-email/\S+)#', $email->getTextBody(), $matches);
        self::assertArrayHasKey(1, $matches, 'Could not find a verification link in the email body.');
        $frontendLink = $matches[1];

        // Le frontend rappelle l'API avec le même chemin/paramètres que ce qui a été signé
        // (voir App\Identity\Mailer\VerifyEmailMailer) : on reproduit ce même appel ici.
        $path = parse_url($frontendLink, \PHP_URL_PATH);
        $query = parse_url($frontendLink, \PHP_URL_QUERY);
        \assert(is_string($path) && is_string($query));
        $userId = basename($path);

        $client->jsonRequest('GET', sprintf('/api/v1/auth/verify-email/%s?%s', $userId, $query));

        self::assertResponseStatusCodeSame(200);
    }

    public function testInvalidVerificationLinkFails(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'bad-link@example.test',
            'password' => 'a-sufficiently-long-password-123',
        ]);
        self::assertResponseStatusCodeSame(201);
        $userId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('GET', sprintf('/api/v1/auth/verify-email/%s?expires=9999999999&signature=invalid', $userId));

        self::assertResponseStatusCodeSame(400);
    }
}
