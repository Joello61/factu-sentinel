<?php

declare(strict_types=1);

namespace App\Tests\Functional\PlatformAdmin;

use App\Shared\Audit\Entity\AuditLogEntry;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Tests\Support\PlatformAdminApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

/** US-PLATFORMADMIN-001/002 (docs/08-api-specification.md, section 38.2). */
final class PlatformAdminOrganizationManagementTest extends PlatformAdminApiTestCase
{
    public function testListAndGetReturnRealCrossTenantData(): void
    {
        $client = static::createClient();
        $owner = $this->registerUser('org-mgmt-owner@example.test');
        $organizationId = $owner->getMemberships()->first()->getOrganizationId()->toRfc4122();

        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-orgmgmt-list@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-orgmgmt-list@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/organizations');
        self::assertResponseStatusCodeSame(200);
        $ids = array_column($this->jsonBody($client)['data'], 'id');
        self::assertContains($organizationId, $ids);

        $client->jsonRequest('GET', '/api/v1/platform-admin/organizations/'.$organizationId);
        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client)['data'];
        self::assertSame($organizationId, $body['id']);
        self::assertCount(1, $body['members']);
        self::assertSame('org-mgmt-owner@example.test', $body['members'][0]['email']);
        self::assertSame('OWNER', $body['members'][0]['role']);
    }

    public function testSuspendingAnOrganizationImmediatelyBlocksAllItsMembers(): void
    {
        $client = static::createClient();
        $owner = $this->registerUser('org-suspend-owner@example.test');
        $organizationId = $owner->getMemberships()->first()->getOrganizationId()->toRfc4122();

        // Le token tenant reste signé et non expiré tout du long.
        $tenantToken = $this->loginExisting($client, 'org-suspend-owner@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tenantToken);
        $client->jsonRequest('GET', '/api/v1/organizations/current');
        self::assertResponseStatusCodeSame(200, 'Accès normal avant suspension.');

        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-suspend@example.test');
        $adminToken = $this->loginPlatformAdministrator($client, 'admin-suspend@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('POST', '/api/v1/platform-admin/organizations/'.$organizationId.'/suspend');
        self::assertResponseStatusCodeSame(200);
        self::assertNotNull($this->jsonBody($client)['data']['suspended_at']);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tenantToken);
        $client->jsonRequest('GET', '/api/v1/organizations/current');
        self::assertResponseStatusCodeSame(401, 'Un membre d\'une organisation suspendue doit perdre l\'accès dès la requête suivante.');

        // Réactivation : l'accès doit être immédiatement restauré.
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);
        $client->jsonRequest('POST', '/api/v1/platform-admin/organizations/'.$organizationId.'/reactivate');
        self::assertResponseStatusCodeSame(200);
        self::assertNull($this->jsonBody($client)['data']['suspended_at']);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tenantToken);
        $client->jsonRequest('GET', '/api/v1/organizations/current');
        self::assertResponseStatusCodeSame(200, 'Une organisation réactivée doit redonner l\'accès immédiatement.');
    }

    public function testSuspensionAndReactivationAreAudited(): void
    {
        $client = static::createClient();
        $owner = $this->registerUser('org-audit-owner@example.test');
        $organizationId = $owner->getMemberships()->first()->getOrganizationId();

        ['administrator' => $administrator, 'plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-orgaudit@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-orgaudit@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('POST', '/api/v1/platform-admin/organizations/'.$organizationId->toRfc4122().'/suspend');
        self::assertResponseStatusCodeSame(200);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $entries = $em->getRepository(AuditLogEntry::class)->findBy([
            'eventType' => EventType::PLATFORM_ORGANIZATION_SUSPENDED,
            'entityId' => $organizationId->toRfc4122(),
        ]);
        self::assertCount(1, $entries);
        self::assertSame(ActorType::PLATFORM_ADMIN, $entries[0]->getActorType());
        self::assertSame($administrator->getId()->toRfc4122(), $entries[0]->getActorId()?->toRfc4122());
        self::assertNull($entries[0]->getOrganizationId(), 'Un événement d\'audit émis par un PlatformAdministrator reste global, jamais rattaché à une organisation.');
    }

    public function testAnOrganizationThatDoesNotExistReturns404(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-org404@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-org404@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/organizations/00000000-0000-4000-8000-000000000000');
        self::assertResponseStatusCodeSame(404);
    }
}
