<?php

declare(strict_types=1);

namespace App\Tests\Functional\PlatformAdmin;

use App\Identity\Enum\Role;
use App\Shared\Audit\Entity\AuditLogEntry;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Tests\Support\PlatformAdminApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * GET /platform-admin/analytics/summary (docs/08-api-specification.md, section 38.3 ;
 * US-ANALYTICS-001). Le jeu de données réel n'est jamais isolé par test (pas de rollback de
 * transaction entre tests, voir le patron déjà utilisé par
 * App\Tests\Functional\PlatformAdmin\PlatformAdminOrganizationManagementTest::
 * testListAndGetReturnRealCrossTenantData()) - toutes les assertions comparent donc un
 * "avant" et un "après" plutôt qu'une valeur absolue, seule façon fiable de prouver que
 * l'agrégation traverse réellement plusieurs tenants sans dépendre de l'état laissé par
 * d'autres tests du même run.
 */
final class GetPlatformAnalyticsSummaryControllerTest extends PlatformAdminApiTestCase
{
    public function testSummaryAggregatesUsersAndOrganizationsAcrossMultipleTenants(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-analytics-summary@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-analytics-summary@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/analytics/summary');
        self::assertResponseStatusCodeSame(200);
        $before = $this->jsonBody($client)['data'];

        // Organisation A : propriétaire + 1 membre = 2 utilisateurs.
        $this->registerUser('analytics-summary-orga-owner@example.test');
        $this->addMemberToOrganization(
            'analytics-summary-orga-owner@example.test',
            'analytics-summary-orga-member@example.test',
            Role::COLLABORATOR,
        );

        // Organisation B : propriétaire + 2 membres = 3 utilisateurs.
        $this->registerUser('analytics-summary-orgb-owner@example.test');
        $this->addMemberToOrganization(
            'analytics-summary-orgb-owner@example.test',
            'analytics-summary-orgb-member1@example.test',
            Role::COLLABORATOR,
        );
        $this->addMemberToOrganization(
            'analytics-summary-orgb-owner@example.test',
            'analytics-summary-orgb-member2@example.test',
            Role::COLLABORATOR,
        );

        $client->jsonRequest('GET', '/api/v1/platform-admin/analytics/summary');
        self::assertResponseStatusCodeSame(200);
        $after = $this->jsonBody($client)['data'];

        self::assertSame(
            $before['organizations_count'] + 2,
            $after['organizations_count'],
            'Les deux nouvelles organisations doivent être comptées.',
        );
        self::assertSame(
            $before['users_count'] + 5,
            $after['users_count'],
            'Les utilisateurs des deux organisations doivent être additionnés - preuve que l\'agrégation traverse réellement plusieurs tenants, pas seulement que l\'accès est autorisé.',
        );
    }

    public function testSummaryRejectsATenantScopedToken(): void
    {
        $client = static::createClient();
        $tenantToken = $this->loginAs($client, 'analytics-summary-tenant@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tenantToken);

        $client->jsonRequest('GET', '/api/v1/platform-admin/analytics/summary');
        self::assertResponseStatusCodeSame(401);
    }

    public function testSummaryIsAudited(): void
    {
        $client = static::createClient();
        ['administrator' => $administrator, 'plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-analytics-summary-audit@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-analytics-summary-audit@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/analytics/summary');
        self::assertResponseStatusCodeSame(200);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $entries = $em->getRepository(AuditLogEntry::class)->findBy([
            'eventType' => EventType::PLATFORM_ANALYTICS_VIEWED,
            'entityId' => 'summary',
            'actorId' => $administrator->getId(),
        ]);
        self::assertNotEmpty($entries);
        self::assertSame(ActorType::PLATFORM_ADMIN, $entries[0]->getActorType());
        self::assertNull($entries[0]->getOrganizationId());
    }
}
