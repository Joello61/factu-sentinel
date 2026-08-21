<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Tests\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

/**
 * Cycle de vie complet d'une invitation (plan Phase 14, revue utilisateur du 21/08/2026) :
 * expirée, révoquée, déjà acceptée, déjà membre, mauvais compte, jeton invalide - jamais
 * seulement le chemin heureux.
 */
final class InvitationLifecycleTest extends ApiTestCase
{
    use MailerAssertionsTrait;

    public function testFullInvitationAcceptanceFlow(): void
    {
        $client = static::createClient();
        $this->registerUser('owner-flow@example.test');
        $ownerToken = $this->loginExisting($client, 'owner-flow@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$ownerToken);

        $client->jsonRequest('POST', '/api/v1/organizations/current/invitations', [
            'email' => 'invitee-flow@example.test',
            'role' => 'COLLABORATOR',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invite-'.bin2hex(random_bytes(8))]);
        self::assertResponseStatusCodeSame(201);

        $token = $this->extractInvitationToken();

        // Aperçu public, sans authentification.
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        $client->jsonRequest('GET', '/api/v1/invitations/'.$token);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('invitee-flow@example.test', $this->jsonBody($client)['data']['email']);

        $inviteeToken = $this->loginAs($client, 'invitee-flow@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$inviteeToken);
        $client->jsonRequest('POST', '/api/v1/invitations/'.$token.'/accept');
        self::assertResponseStatusCodeSame(201);
        self::assertSame('COLLABORATOR', $this->jsonBody($client)['data']['role']);

        $client->jsonRequest('GET', '/api/v1/auth/me/organizations');
        self::assertCount(2, $this->jsonBody($client)['data'], 'Le compte invité doit désormais appartenir à deux organisations.');
    }

    public function testInvalidTokenNeverLeaksExistence(): void
    {
        $client = static::createClient();

        $client->jsonRequest('GET', '/api/v1/invitations/not-a-real-token-at-all');
        self::assertResponseStatusCodeSame(404);

        $this->registerUser('someone@example.test');
        $token = $this->loginExisting($client, 'someone@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
        $client->jsonRequest('POST', '/api/v1/invitations/not-a-real-token-at-all/accept');
        self::assertResponseStatusCodeSame(404);
    }

    public function testExpiredInvitationIsRejected(): void
    {
        $client = static::createClient();
        $this->registerUser('owner-expired@example.test');
        $ownerToken = $this->loginExisting($client, 'owner-expired@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$ownerToken);

        $client->jsonRequest('POST', '/api/v1/organizations/current/invitations', [
            'email' => 'invitee-expired@example.test',
            'role' => 'COLLABORATOR',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invite-'.bin2hex(random_bytes(8))]);
        $token = $this->extractInvitationToken();

        $this->forceInvitationExpiry('invitee-expired@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        $client->jsonRequest('GET', '/api/v1/invitations/'.$token);
        self::assertResponseStatusCodeSame(404, 'Une invitation expirée reste 404 côté aperçu public, jamais distinguée.');

        $inviteeToken = $this->loginAs($client, 'invitee-expired@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$inviteeToken);
        $client->jsonRequest('POST', '/api/v1/invitations/'.$token.'/accept');
        self::assertResponseStatusCodeSame(409, 'Authentifié, le refus doit être explicite (conflit), pas un 404 générique.');
    }

    public function testRevokedInvitationCanNeverBeAccepted(): void
    {
        $client = static::createClient();
        $this->registerUser('owner-revoked@example.test');
        $ownerToken = $this->loginExisting($client, 'owner-revoked@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$ownerToken);

        $client->jsonRequest('POST', '/api/v1/organizations/current/invitations', [
            'email' => 'invitee-revoked@example.test',
            'role' => 'COLLABORATOR',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invite-'.bin2hex(random_bytes(8))]);
        $token = $this->extractInvitationToken();
        $invitationId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('DELETE', '/api/v1/organizations/current/invitations/'.$invitationId);
        self::assertResponseStatusCodeSame(204);

        $inviteeToken = $this->loginAs($client, 'invitee-revoked@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$inviteeToken);
        $client->jsonRequest('POST', '/api/v1/invitations/'.$token.'/accept');
        self::assertResponseStatusCodeSame(409);
    }

    public function testWrongAccountCannotAcceptSomeoneElsesInvitation(): void
    {
        $client = static::createClient();
        $this->registerUser('owner-wrong-account@example.test');
        $ownerToken = $this->loginExisting($client, 'owner-wrong-account@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$ownerToken);

        $client->jsonRequest('POST', '/api/v1/organizations/current/invitations', [
            'email' => 'alice@example.test',
            'role' => 'COLLABORATOR',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invite-'.bin2hex(random_bytes(8))]);
        $token = $this->extractInvitationToken();

        $bobToken = $this->loginAs($client, 'bob@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$bobToken);
        $client->jsonRequest('POST', '/api/v1/invitations/'.$token.'/accept');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAlreadyMemberCannotAcceptADuplicateInvitation(): void
    {
        $client = static::createClient();
        $this->registerUser('owner-dup@example.test');
        $ownerToken = $this->loginExisting($client, 'owner-dup@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$ownerToken);

        $client->jsonRequest('POST', '/api/v1/organizations/current/invitations', [
            'email' => 'invitee-dup@example.test',
            'role' => 'COLLABORATOR',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invite-'.bin2hex(random_bytes(8))]);
        $firstToken = $this->extractInvitationToken();

        $inviteeToken = $this->loginAs($client, 'invitee-dup@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$inviteeToken);
        $client->jsonRequest('POST', '/api/v1/invitations/'.$firstToken.'/accept');
        self::assertResponseStatusCodeSame(201);

        // Un deuxième appel POST .../accept avec le même jeton (rejeu) doit échouer.
        $client->jsonRequest('POST', '/api/v1/invitations/'.$firstToken.'/accept');
        self::assertResponseStatusCodeSame(409);
    }

    public function testInvitationTokenAccessIsRateLimitedByIp(): void
    {
        $client = static::createClient();
        $this->registerUser('owner-rate-limit@example.test');
        $ownerToken = $this->loginExisting($client, 'owner-rate-limit@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$ownerToken);

        $client->jsonRequest('POST', '/api/v1/organizations/current/invitations', [
            'email' => 'invitee-rate-limit@example.test',
            'role' => 'COLLABORATOR',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invite-'.bin2hex(random_bytes(8))]);
        $token = $this->extractInvitationToken();

        // config/packages/rate_limiter.yaml: invitation_token_access = 20/15 minutes,
        // partagé entre GET /invitations/{token} et POST .../accept (même clé IP).
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        for ($i = 0; $i < 20; ++$i) {
            $client->jsonRequest('GET', '/api/v1/invitations/'.$token);
            self::assertResponseStatusCodeSame(200);
        }

        $client->jsonRequest('GET', '/api/v1/invitations/'.$token);

        self::assertResponseStatusCodeSame(429);
    }

    private function extractInvitationToken(): string
    {
        $email = self::getMailerMessage();
        self::assertNotNull($email, 'Expected an invitation email to have been sent.');
        preg_match('#/invitations/([A-Za-z0-9]+)#', $email->getTextBody(), $matches);
        self::assertArrayHasKey(1, $matches, 'Could not find an invitation token in the email body.');

        return $matches[1];
    }

    private function forceInvitationExpiry(string $invitedEmail): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement(
            'UPDATE invitations SET expires_at = NOW() - INTERVAL \'1 day\' WHERE email = ?',
            [$invitedEmail],
        );
        $em->clear();
    }
}
