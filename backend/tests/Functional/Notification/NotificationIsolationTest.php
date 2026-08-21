<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Identity\Enum\Role;
use App\Tests\Support\ApiTestCase;

/**
 * Invariant non négociable (plan Phase 14, revue utilisateur du 21/08/2026) :
 * `organization_id` seul ne doit jamais suffire à autoriser la lecture d'une notification -
 * `recipient_user_id` filtre systématiquement en plus.
 */
final class NotificationIsolationTest extends ApiTestCase
{
    public function testAMemberNeverSeesANotificationAddressedToAnotherMemberOfTheSameOrganization(): void
    {
        $client = $this->createAuthenticatedClient('owner-notif-iso@example.test');
        $recipientA = $this->addMemberToOrganization('owner-notif-iso@example.test', 'recipient-a@example.test', Role::COLLABORATOR);
        $this->addMemberToOrganization('owner-notif-iso@example.test', 'recipient-b@example.test', Role::COLLABORATOR);

        $client->jsonRequest('POST', '/api/v1/organizations/current/notifications', [
            'recipient_ids' => [$recipientA->getId()->toRfc4122()],
            'message' => 'Message pour A uniquement.',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'notify-'.bin2hex(random_bytes(8))]);
        self::assertResponseStatusCodeSame(201);

        // B, membre de la même organisation (et même OWNER lui-même), ne doit jamais voir la
        // notification adressée à A.
        foreach (['owner-notif-iso@example.test', 'recipient-b@example.test'] as $email) {
            $token = $this->loginExisting($client, $email);
            $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
            $client->jsonRequest('GET', '/api/v1/notifications');
            self::assertResponseStatusCodeSame(200);
            self::assertSame([], $this->jsonBody($client)['data'], "{$email} ne doit voir aucune notification qui ne lui est pas adressée.");
        }

        $tokenA = $this->loginExisting($client, 'recipient-a@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('GET', '/api/v1/notifications');
        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $this->jsonBody($client)['data']);
        self::assertSame('Message pour A uniquement.', $this->jsonBody($client)['data'][0]['message']);
    }

    public function testAMemberCannotMarkAsReadANotificationAddressedToAnotherMember(): void
    {
        $client = $this->createAuthenticatedClient('owner-notif-mark@example.test');
        $recipientA = $this->addMemberToOrganization('owner-notif-mark@example.test', 'mark-a@example.test', Role::COLLABORATOR);
        $this->addMemberToOrganization('owner-notif-mark@example.test', 'mark-b@example.test', Role::COLLABORATOR);

        $client->jsonRequest('POST', '/api/v1/organizations/current/notifications', [
            'recipient_ids' => [$recipientA->getId()->toRfc4122()],
            'message' => 'Pour A.',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'notify-'.bin2hex(random_bytes(8))]);

        $tokenA = $this->loginExisting($client, 'mark-a@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('GET', '/api/v1/notifications');
        $notificationId = $this->jsonBody($client)['data'][0]['id'];

        $tokenB = $this->loginExisting($client, 'mark-b@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('PATCH', '/api/v1/notifications/'.$notificationId.'/read');

        self::assertResponseStatusCodeSame(404, 'Jamais accessible à un autre membre, même de la même organisation.');
    }

    public function testSendingToARecipientOutsideTheOrganizationIsRejectedWith422NeverPerRecipient403(): void
    {
        $client = $this->createAuthenticatedClient('owner-notif-outside@example.test');

        $client->jsonRequest('POST', '/api/v1/organizations/current/notifications', [
            'recipient_ids' => ['00000000-0000-4000-8000-000000000000'],
            'message' => 'Ne devrait jamais partir.',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'notify-'.bin2hex(random_bytes(8))]);

        self::assertResponseStatusCodeSame(422);
    }
}
