<?php

declare(strict_types=1);

namespace App\Tests\Functional\PlatformAdmin;

use App\Tests\Support\PlatformAdminApiTestCase;

/** US-PLATFORMADMIN-004 (docs/08-api-specification.md, section 38.2) - un cas par valeur de target_type. */
final class PlatformNotificationSegmentationTest extends PlatformAdminApiTestCase
{
    private function idempotencyKey(): string
    {
        return 'platform-notif-'.bin2hex(random_bytes(8));
    }

    public function testTargetTypeUserNotifiesOnlyThatUser(): void
    {
        $client = static::createClient();
        $recipient = $this->registerUser('notif-user-target@example.test');
        $other = $this->registerUser('notif-user-other@example.test');

        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-notifuser@example.test');
        $adminToken = $this->loginPlatformAdministrator($client, 'admin-notifuser@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('POST', '/api/v1/platform-admin/notifications', [
            'target_type' => 'USER',
            'target_id' => $recipient->getId()->toRfc4122(),
            'message' => 'Message individuel.',
        ], ['HTTP_IDEMPOTENCY_KEY' => $this->idempotencyKey()]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, $this->jsonBody($client)['data']['estimated_recipient_count']);

        $recipientToken = $this->loginExisting($client, 'notif-user-target@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$recipientToken);
        $client->jsonRequest('GET', '/api/v1/notifications');
        self::assertCount(1, $this->jsonBody($client)['data']);
        self::assertSame('Message individuel.', $this->jsonBody($client)['data'][0]['message']);

        $otherToken = $this->loginExisting($client, 'notif-user-other@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$otherToken);
        $client->jsonRequest('GET', '/api/v1/notifications');
        self::assertSame([], $this->jsonBody($client)['data']);
    }

    public function testTargetTypeOrganizationNotifiesAllItsMembersOnly(): void
    {
        $client = static::createClient();
        $owner = $this->registerUser('notif-org-owner@example.test');
        $organizationId = $owner->getMemberships()->first()->getOrganizationId()->toRfc4122();
        $otherOwner = $this->registerUser('notif-org-other-owner@example.test');

        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-notiforg@example.test');
        $adminToken = $this->loginPlatformAdministrator($client, 'admin-notiforg@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('POST', '/api/v1/platform-admin/notifications', [
            'target_type' => 'ORGANIZATION',
            'target_id' => $organizationId,
            'message' => 'Message organisation.',
        ], ['HTTP_IDEMPOTENCY_KEY' => $this->idempotencyKey()]);
        self::assertResponseStatusCodeSame(201);

        $ownerToken = $this->loginExisting($client, 'notif-org-owner@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$ownerToken);
        $client->jsonRequest('GET', '/api/v1/notifications');
        self::assertCount(1, $this->jsonBody($client)['data']);

        $otherToken = $this->loginExisting($client, 'notif-org-other-owner@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$otherToken);
        $client->jsonRequest('GET', '/api/v1/notifications');
        self::assertSame([], $this->jsonBody($client)['data']);
    }

    public function testTargetTypeAllNotifiesEveryUserAcrossOrganizations(): void
    {
        $client = static::createClient();
        $this->registerUser('notif-all-a@example.test');
        $this->registerUser('notif-all-b@example.test');

        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-notifall@example.test');
        $adminToken = $this->loginPlatformAdministrator($client, 'admin-notifall@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('POST', '/api/v1/platform-admin/notifications', [
            'target_type' => 'ALL',
            'message' => 'Diffusion globale.',
        ], ['HTTP_IDEMPOTENCY_KEY' => $this->idempotencyKey()]);
        self::assertResponseStatusCodeSame(201);
        self::assertGreaterThanOrEqual(2, $this->jsonBody($client)['data']['estimated_recipient_count']);

        foreach (['notif-all-a@example.test', 'notif-all-b@example.test'] as $email) {
            $token = $this->loginExisting($client, $email);
            $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
            $client->jsonRequest('GET', '/api/v1/notifications');
            self::assertCount(1, $this->jsonBody($client)['data'], "{$email} doit recevoir la diffusion globale.");
        }
    }

    public function testTargetTypeSegmentNotifiesOnlyMatchingOrganizations(): void
    {
        $client = static::createClient();
        $matchingOwner = $this->registerUser('notif-segment-match@example.test');
        $nonMatchingOwner = $this->registerUser('notif-segment-nomatch@example.test');

        $matchingToken = $this->loginExisting($client, 'notif-segment-match@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$matchingToken);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'legal_name' => 'Segment Match',
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_REDEVABLE',
                'employees_count' => 3,
                'annual_turnover' => 150000,
                'annual_balance_sheet_total' => 80000,
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey('fiscal_context', $this->jsonBody($client)['data']);

        $nonMatchingToken = $this->loginExisting($client, 'notif-segment-nomatch@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$nonMatchingToken);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'legal_name' => 'Segment No Match',
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_FRANCHISE_EN_BASE',
                'employees_count' => 1,
                'annual_turnover' => 20000,
                'annual_balance_sheet_total' => 10000,
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-notifsegment@example.test');
        $adminToken = $this->loginPlatformAdministrator($client, 'admin-notifsegment@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('POST', '/api/v1/platform-admin/notifications', [
            'target_type' => 'SEGMENT',
            'target_criteria' => ['vat_status' => ['ASSUJETTI_REDEVABLE']],
            'message' => 'Message segmenté.',
        ], ['HTTP_IDEMPOTENCY_KEY' => $this->idempotencyKey()]);
        self::assertResponseStatusCodeSame(201);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$matchingToken);
        $client->jsonRequest('GET', '/api/v1/notifications');
        self::assertCount(1, $this->jsonBody($client)['data'], $matchingOwner->getEmail().' correspond au segment.');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$nonMatchingToken);
        $client->jsonRequest('GET', '/api/v1/notifications');
        self::assertSame([], $this->jsonBody($client)['data'], $nonMatchingOwner->getEmail().' ne correspond pas au segment.');
    }

    public function testMissingIdempotencyKeyIsRejected(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-notifnokey@example.test');
        $adminToken = $this->loginPlatformAdministrator($client, 'admin-notifnokey@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('POST', '/api/v1/platform-admin/notifications', [
            'target_type' => 'ALL',
            'message' => 'Sans clé.',
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testUnauthenticatedRequestsAreRejected(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/platform-admin/notifications', [
            'target_type' => 'ALL',
            'message' => 'Ne devrait jamais partir.',
        ], ['HTTP_IDEMPOTENCY_KEY' => $this->idempotencyKey()]);
        self::assertResponseStatusCodeSame(401, 'Aucune requête non authentifiée sur /platform-admin/* ne doit jamais être acceptée.');
    }
}
