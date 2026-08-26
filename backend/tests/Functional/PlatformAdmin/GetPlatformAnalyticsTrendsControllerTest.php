<?php

declare(strict_types=1);

namespace App\Tests\Functional\PlatformAdmin;

use App\PlatformAdmin\Service\PlatformAnalyticsTrendAggregator;
use App\Shared\Audit\Entity\AuditLogEntry;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Tests\Support\PlatformAdminApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * GET /platform-admin/analytics/trends (docs/08-api-specification.md, section 38.3 ;
 * US-ANALYTICS-002). Le calcul de bucket lui-même (dates, zéros explicites, formule du taux)
 * est déjà couvert de façon exhaustive et déterministe par
 * App\Tests\Integration\PlatformAdmin\PlatformAnalyticsTrendAggregatorTest (sans DB) - ce test
 * fonctionnel vérifie uniquement que la requête cross-tenant réelle atteint bien plusieurs
 * organisations, l'autorisation et l'audit. Mêmes précautions "avant/après" que
 * App\Tests\Functional\PlatformAdmin\GetPlatformAnalyticsSummaryControllerTest (pas d'isolation
 * transactionnelle entre tests).
 */
final class GetPlatformAnalyticsTrendsControllerTest extends PlatformAdminApiTestCase
{
    public function testTrendsAggregateCompletedAnalysesAcrossMultipleOrganizationsForToday(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-analytics-trends@example.test');
        $adminToken = $this->loginPlatformAdministrator($client, 'admin-analytics-trends@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('GET', '/api/v1/platform-admin/analytics/trends');
        self::assertResponseStatusCodeSame(200);
        $before = self::todayBucket($this->jsonBody($client)['data']['points']);

        // Organisation A : une analyse de conformité complétée aujourd'hui.
        $tokenA = $this->loginAs($client, 'analytics-trends-orga@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $this->markEmailVerified('analytics-trends-orga@example.test');
        $this->createAnalyzedInvoice($client, 'trends-orga');

        // Organisation B : une analyse de conformité complétée aujourd'hui, tenant distinct.
        $tokenB = $this->loginAs($client, 'analytics-trends-orgb@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $this->markEmailVerified('analytics-trends-orgb@example.test');
        $this->createAnalyzedInvoice($client, 'trends-orgb');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);
        $client->jsonRequest('GET', '/api/v1/platform-admin/analytics/trends');
        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client)['data'];
        self::assertCount(PlatformAnalyticsTrendAggregator::WINDOW_DAYS, $body['points']);
        $after = self::todayBucket($body['points']);

        self::assertSame(
            $before['compliance_analyses_count'] + 2,
            $after['compliance_analyses_count'],
            'Les analyses des deux organisations doivent apparaître dans le même bucket quotidien - preuve que l\'agrégation traverse réellement plusieurs tenants.',
        );
    }

    public function testTrendsRejectsATenantScopedToken(): void
    {
        $client = static::createClient();
        $tenantToken = $this->loginAs($client, 'analytics-trends-tenant@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tenantToken);

        $client->jsonRequest('GET', '/api/v1/platform-admin/analytics/trends');
        self::assertResponseStatusCodeSame(401);
    }

    public function testTrendsIsAudited(): void
    {
        $client = static::createClient();
        ['administrator' => $administrator, 'plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-analytics-trends-audit@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-analytics-trends-audit@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/analytics/trends');
        self::assertResponseStatusCodeSame(200);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $entries = $em->getRepository(AuditLogEntry::class)->findBy([
            'eventType' => EventType::PLATFORM_ANALYTICS_VIEWED,
            'entityId' => 'trends',
            'actorId' => $administrator->getId(),
        ]);
        self::assertNotEmpty($entries);
        self::assertSame(ActorType::PLATFORM_ADMIN, $entries[0]->getActorType());
        self::assertNull($entries[0]->getOrganizationId());
    }

    private function configureFiscalContext(KernelBrowser $client): void
    {
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_REDEVABLE',
                'employees_count' => 5,
                'annual_turnover' => '200000',
                'annual_balance_sheet_total' => '150000',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * Peu importe le globalResult obtenu (CONFORME, NON_CONFORME, ...) : la formule exacte du
     * taux de conformité est déjà couverte par PlatformAnalyticsTrendAggregatorTest, ce test
     * fonctionnel ne prouve que l'agrégation cross-tenant du volume d'analyses.
     */
    private function createAnalyzedInvoice(KernelBrowser $client, string $idempotencySuffix): void
    {
        $this->configureFiscalContext($client);

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client Test SARL',
            'siren' => '123456789',
            'country' => 'FR',
        ]);
        $customerId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'PRESTATION_SERVICE',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Prestation', 'quantity' => '1', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'analytics-trends-invoice-'.$idempotencySuffix]);
        self::assertResponseStatusCodeSame(201);
        $invoiceId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'analytics-trends-analysis-'.$idempotencySuffix]);
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * @param list<array{date: string, organizations_created: int, users_created: int, compliance_analyses_count: int, compliance_rate: string}> $points
     *
     * @return array{date: string, organizations_created: int, users_created: int, compliance_analyses_count: int, compliance_rate: string}
     */
    private static function todayBucket(array $points): array
    {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');

        foreach ($points as $point) {
            if ($today === $point['date']) {
                return $point;
            }
        }

        self::fail('Le bucket d\'aujourd\'hui doit toujours être présent dans une fenêtre de 90 jours se terminant aujourd\'hui.');
    }
}
