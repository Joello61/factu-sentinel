<?php

declare(strict_types=1);

namespace App\Tests\Functional\PlatformAdmin;

use App\Tests\Support\PlatformAdminApiTestCase;

/**
 * US-PLATFORMADMIN-003 (docs/08-api-specification.md, section 38.2). Preuve explicite du
 * cross-tenant : un seul appel retourne des événements de plusieurs organisations, jamais
 * mélangé avec GET /audit-events tenant (App\Shared\Audit\Controller\ListAuditEventsController).
 */
final class PlatformCrossTenantAuditTest extends PlatformAdminApiTestCase
{
    public function testPlatformAuditTrailSpansMultipleOrganizationsInASingleCall(): void
    {
        $client = static::createClient();
        $ownerA = $this->registerUser('audit-org-a@example.test');
        $ownerB = $this->registerUser('audit-org-b@example.test');
        $organizationAId = $ownerA->getMemberships()->first()->getOrganizationId()->toRfc4122();
        $organizationBId = $ownerB->getMemberships()->first()->getOrganizationId()->toRfc4122();

        // Génère un événement d'audit réel dans chaque organisation (mise à jour du contexte
        // fiscal via l'API tenant normale, jamais une insertion directe en base).
        $tokenA = $this->loginExisting($client, 'audit-org-a@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', ['legal_name' => 'Organisation A']);
        self::assertResponseStatusCodeSame(200);

        $tokenB = $this->loginExisting($client, 'audit-org-b@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', ['legal_name' => 'Organisation B']);
        self::assertResponseStatusCodeSame(200);

        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-crossaudit@example.test');
        $adminToken = $this->loginPlatformAdministrator($client, 'admin-crossaudit@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('GET', '/api/v1/platform-admin/audit-events?per_page=100');
        self::assertResponseStatusCodeSame(200);
        $organizationIdsSeen = array_unique(array_column($this->jsonBody($client)['data'], 'organization_id'));

        self::assertContains($organizationAId, $organizationIdsSeen);
        self::assertContains($organizationBId, $organizationIdsSeen, 'Un seul appel doit couvrir plusieurs organisations - preuve explicite du cross-tenant.');
    }

    public function testFilteringByOrganizationIdRestrictsResults(): void
    {
        $client = static::createClient();
        $ownerA = $this->registerUser('audit-filter-a@example.test');
        $this->registerUser('audit-filter-b@example.test');
        $organizationAId = $ownerA->getMemberships()->first()->getOrganizationId()->toRfc4122();

        $tokenA = $this->loginExisting($client, 'audit-filter-a@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', ['legal_name' => 'Filtrage A']);

        $tokenB = $this->loginExisting($client, 'audit-filter-b@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', ['legal_name' => 'Filtrage B']);

        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-auditfilter@example.test');
        $adminToken = $this->loginPlatformAdministrator($client, 'admin-auditfilter@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('GET', '/api/v1/platform-admin/audit-events?organization_id='.$organizationAId);
        self::assertResponseStatusCodeSame(200);
        $organizationIdsSeen = array_unique(array_column($this->jsonBody($client)['data'], 'organization_id'));

        self::assertSame([$organizationAId], $organizationIdsSeen);
    }

    public function testTenantAuditEndpointNeverAcceptsAPlatformAdminToken(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-audit-crossreject@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-audit-crossreject@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/audit-events');
        self::assertResponseStatusCodeSame(401);
    }
}
