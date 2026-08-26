<?php

declare(strict_types=1);

namespace App\Tests\Functional\PlatformAdmin;

use App\Shared\Audit\Entity\AuditLogEntry;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Tests\Support\PlatformAdminApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

/** US-PLATFORMADMIN-005 (docs/08-api-specification.md, section 38.2). */
final class GetPlatformHealthControllerTest extends PlatformAdminApiTestCase
{
    public function testHealthEndpointReturnsAllExpectedIndicators(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-health@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-health@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/health');
        self::assertResponseStatusCodeSame(200);

        $data = $this->jsonBody($client)['data'];
        self::assertArrayHasKey('compliance_engine_failure_rate_24h', $data);
        self::assertArrayHasKey('async_jobs_dead_letter_count', $data);
        self::assertArrayHasKey('ai_calls_volume_24h', $data);
        self::assertArrayHasKey('ai_estimated_cost_24h', $data);
        self::assertSame('ok', $data['api_health']);
    }

    /**
     * Régression Phase 16 : cet endpoint relisait des indicateurs cross-tenant sans jamais
     * l'auditer depuis la Phase 15, en violation de docs/10-security-privacy.md section 17
     * bis ("chaque lecture ou écriture cross-tenant est journalisée, sans exception"). Même
     * patron d'assertion que App\Tests\Functional\PlatformAdmin\
     * PlatformAdminOrganizationManagementTest::testSuspensionAndReactivationAreAudited() -
     * interroge une AuditLogEntry réellement persistée, jamais un mock de AuditLogger.
     */
    public function testHealthEndpointIsAudited(): void
    {
        $client = static::createClient();
        ['administrator' => $administrator, 'plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-health-audit@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-health-audit@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/health');
        self::assertResponseStatusCodeSame(200);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $entries = $em->getRepository(AuditLogEntry::class)->findBy([
            'eventType' => EventType::PLATFORM_HEALTH_VIEWED,
            'entityId' => 'summary',
            'actorId' => $administrator->getId(),
        ]);
        self::assertNotEmpty($entries);
        self::assertSame(ActorType::PLATFORM_ADMIN, $entries[0]->getActorType());
        self::assertNull($entries[0]->getOrganizationId(), 'Un événement d\'audit émis par un PlatformAdministrator reste global, jamais rattaché à une organisation.');
    }
}
