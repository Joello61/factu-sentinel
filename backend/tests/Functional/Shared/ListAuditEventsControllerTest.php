<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Tests\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * GET /audit-events (docs/08-api-specification.md, section 39). Écart d'implémentation
 * fermé en Phase 10 - App\Shared\Audit\AuditLogger et AuditLogEntry existaient déjà depuis
 * la Phase 3, jamais exposés en lecture jusqu'ici. L'isolation cross-tenant est couverte
 * séparément par App\Tests\Integration\MultiTenant\TenantIsolationTest::testTcTenant008ApiIsolationOnAuditEvents.
 */
final class ListAuditEventsControllerTest extends ApiTestCase
{
    public function testListReturnsOwnEventsNewestFirst(): void
    {
        $client = $this->createAuthenticatedClient('audit-list-001@example.test');

        $client->jsonRequest('PATCH', '/api/v1/organizations/current', ['legal_name' => 'Nom initial']);
        self::assertResponseStatusCodeSame(200);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', ['legal_name' => 'Nom mis à jour']);
        self::assertResponseStatusCodeSame(200);

        $client->jsonRequest('GET', '/api/v1/audit-events');

        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client);

        self::assertGreaterThanOrEqual(2, $body['meta']['pagination']['total_count']);
        self::assertSame('ORGANIZATION_UPDATED', $body['data'][0]['event_type']);
        self::assertArrayHasKey('occurred_at', $body['data'][0]);
        self::assertSame('USER', $body['data'][0]['actor']['type']);
        self::assertNotNull($body['data'][0]['actor']['id']);
        self::assertGreaterThanOrEqual(
            $body['data'][1]['occurred_at'],
            $body['data'][0]['occurred_at'],
            'Le plus récent en premier.',
        );
    }

    public function testFilterByEntityType(): void
    {
        $client = $this->createAuthenticatedClient('audit-list-002@example.test');

        $client->jsonRequest('PATCH', '/api/v1/organizations/current', ['legal_name' => 'Nom Organisation']);
        self::assertResponseStatusCodeSame(200);

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client Audit SARL',
            'siren' => '123456789',
            'country' => 'FR',
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('GET', '/api/v1/audit-events?entity_type=Customer');

        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client);

        self::assertSame(1, $body['meta']['pagination']['total_count']);
        self::assertSame('CUSTOMER_CREATED', $body['data'][0]['event_type']);
        self::assertSame('Customer', $body['data'][0]['entity_type']);
    }

    public function testGlobalEventsAreNeverExposedToAnOrganization(): void
    {
        $client = $this->createAuthenticatedClient('audit-list-003@example.test');

        // App\Shared\Audit\Entity\AuditLogEntry.organizationId nullable = événement global
        // (docs/08-api-specification.md, section 39) : aucun chemin applicatif actuel n'en
        // produit (aucune API de publication de RuleVersion au MVP, voir
        // App\Tests\Functional\Compliance\RuleVersionNonRetroactivityTest) - inséré ici
        // directement pour prouver que le filtrage exclut structurellement organization_id
        // NULL, pas seulement parce qu'aucun cas réel ne l'exerce encore.
        // App\Shared\Audit\Enum\EventType::ORGANIZATION_UPDATED réutilisée uniquement pour
        // sa validité d'enum (aucun EventType global dédié n'existe encore) - seul
        // organization_id NULL importe pour ce test.
        static::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement(
            "INSERT INTO audit_log_entries (id, organization_id, actor_type, actor_id, event_type, entity_type, entity_id, previous_state, new_state, occurred_at) VALUES (gen_random_uuid(), NULL, 'SYSTEM', NULL, 'ORGANIZATION_UPDATED', 'RuleVersion', 'mention-siren-client', NULL, NULL, NOW())",
        );

        $client->jsonRequest('GET', '/api/v1/audit-events');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $this->jsonBody($client)['meta']['pagination']['total_count'], 'Un événement global ne doit jamais apparaître ici.');
    }
}
