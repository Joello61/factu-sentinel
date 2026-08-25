<?php

declare(strict_types=1);

namespace App\Tests\Integration\MultiTenant;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Customer\Entity\Customer;
use App\Identity\Entity\Membership;
use App\Identity\Entity\User;
use App\Invoicing\Entity\Invoice;
use App\Tests\Support\PlatformAdminApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Exit Criteria de la Phase 2 (docs/12-roadmap.md) : deux comptes créés ne peuvent voir
 * aucune donnée l'un de l'autre. Cinq scénarios distincts plutôt qu'un seul, cohérent avec
 * la défense en profondeur exigée par docs/10-security-privacy.md (section 3) : session +
 * couche d'accès aux données + tests, trois couches vérifiées indépendamment.
 *
 * Étend App\Tests\Support\PlatformAdminApiTestCase depuis la Phase 15 (extension explicite
 * demandée par le plan Phase 15, même patron que TC-TENANT-008 pour GET /audit-events en
 * Phase 10) - PlatformAdminApiTestCase n'ajoute que des méthodes, jamais de comportement
 * modifié par rapport à ApiTestCase.
 */
final class TenantIsolationTest extends PlatformAdminApiTestCase
{
    /**
     * WebTestCase n'autorise qu'un seul static::createClient() par test : les scénarios
     * à deux comptes utilisent donc un seul client et basculent explicitement l'en-tête
     * Authorization entre le jeton de A et celui de B, plutôt que deux instances de
     * client.
     */
    private function tokensForTwoTenants(string $emailA, string $emailB): array
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, $emailA);
        $tokenB = $this->loginAs($client, $emailB);

        return [$client, $tokenA, $tokenB];
    }

    public function testTcTenant001ApiIsolation(): void
    {
        [$client, $tokenA, $tokenB] = $this->tokensForTwoTenants('tenant-a-001@example.test', 'tenant-b-001@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('GET', '/api/v1/organizations/current');
        $organizationA = $this->jsonBody($client)['data']['id'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('GET', '/api/v1/organizations/current');
        $organizationB = $this->jsonBody($client)['data']['id'];

        self::assertNotSame($organizationA, $organizationB);
    }

    public function testTcTenant002ClientSuppliedOrganizationIdIsIgnored(): void
    {
        [$client, $tokenA, $tokenB] = $this->tokensForTwoTenants('tenant-a-002@example.test', 'tenant-b-002@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('GET', '/api/v1/organizations/current');
        $organizationBId = $this->jsonBody($client)['data']['id'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('GET', '/api/v1/organizations/current');
        $organizationAId = $this->jsonBody($client)['data']['id'];

        // Aucun endpoint /organizations/{id} n'existe : le seul organization_id jamais
        // résolu vient du claim JWT (CurrentOrganizationResolver), jamais d'un paramètre
        // client. On le vérifie en tentant explicitement de l'injecter par la requête.
        $client->jsonRequest('GET', sprintf('/api/v1/organizations/current?organization_id=%s', $organizationBId), [], [
            'HTTP_X_ORGANIZATION_ID' => $organizationBId,
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($organizationAId, $this->jsonBody($client)['data']['id']);
    }

    public function testTcTenant003DoctrineLevelIsolationOnMembership(): void
    {
        [$client, $tokenA] = $this->tokensForTwoTenants('tenant-a-003@example.test', 'tenant-b-003@example.test');

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Simule ce que TenantFilterActivationListener fait pour une requête authentifiée
        // de A, puis vérifie directement au niveau Doctrine qu'aucune ligne de B ne fuit.
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('GET', '/api/v1/organizations/current');
        $organizationAId = $this->jsonBody($client)['data']['id'];

        $em->getFilters()->enable('tenant_filter')->setParameter('organization_id', $organizationAId, 'string');

        $memberships = $em->getRepository(Membership::class)->findAll();

        self::assertNotEmpty($memberships);
        foreach ($memberships as $membership) {
            self::assertSame($organizationAId, $membership->getOrganizationId()->toRfc4122());
        }

        $em->getFilters()->disable('tenant_filter');
    }

    public function testTcTenant004TamperedJwtOrgClaimIsRejected(): void
    {
        $client = $this->createAuthenticatedClient('tenant-tampered@example.test');

        $authorization = $client->getServerParameter('HTTP_AUTHORIZATION');
        \assert(is_string($authorization));
        $token = substr($authorization, strlen('Bearer '));

        [$header, $payload, $signature] = explode('.', $token);
        $decoded = json_decode(base64_decode($payload, true), true, flags: \JSON_THROW_ON_ERROR);
        $decoded['org'] = '00000000-0000-7000-8000-000000000000';
        $tamperedPayload = rtrim(strtr(base64_encode((string) json_encode($decoded)), '+/', '-_'), '=');
        $tamperedToken = sprintf('%s.%s.%s', $header, $tamperedPayload, $signature);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tamperedToken);
        $client->jsonRequest('GET', '/api/v1/organizations/current');

        // La signature ne correspond plus au payload modifié : rejeté à l'authentification,
        // jamais silencieusement accepté avec le organization_id falsifié.
        self::assertResponseStatusCodeSame(401);
    }

    public function testTcTenant005FailsClosedWithoutResolvableOrganization(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Invariant Phase 2 rompu délibérément : un User persisté sans aucun Membership
        // (impossible via /auth/register, qui en crée toujours exactement un). Simule
        // l'état interne impossible que TC-TENANT-005 doit couvrir.
        $user = new User('orphan-005@example.test', 'temporary');
        $user->setPassword($hasher->hashPassword($user, 'a-sufficiently-long-password-123'));
        $em->persist($user);
        $em->flush();

        $client->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'orphan-005@example.test',
            'password' => 'a-sufficiently-long-password-123',
        ]);

        // Jamais un 403 ordinaire : violation d'invariant interne (voir
        // App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException).
        self::assertResponseStatusCodeSame(500);
        $body = $this->jsonBody($client);
        self::assertSame('INTERNAL_ERROR', $body['error']['code']);
    }

    /**
     * Extension Phase 4 (docs/12-roadmap.md) de TC-TENANT-003 : Customer et Invoice, comme
     * Membership en Phase 2, ne doivent jamais fuiter au niveau Doctrine lorsque TenantFilter
     * est actif pour l'organisation A (docs/09-test-strategy.md, section 22).
     */
    public function testTcTenant006DoctrineLevelIsolationOnCustomerAndInvoice(): void
    {
        [$client, $tokenA, $tokenB] = $this->tokensForTwoTenants('tenant-a-006@example.test', 'tenant-b-006@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('GET', '/api/v1/organizations/current');
        $organizationAId = $this->jsonBody($client)['data']['id'];
        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PARTICULIER', 'name' => 'Client A', 'country' => 'FR']);
        $customerAId = $this->jsonBody($client)['data']['id'];
        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerAId,
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Ligne', 'quantity' => '1', 'unit_price_ht' => '10.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invoice-create-'.bin2hex(random_bytes(8))]);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PARTICULIER', 'name' => 'Client B', 'country' => 'FR']);
        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $this->jsonBody($client)['data']['id'],
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Ligne', 'quantity' => '1', 'unit_price_ht' => '10.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invoice-create-'.bin2hex(random_bytes(8))]);

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->getFilters()->enable('tenant_filter')->setParameter('organization_id', $organizationAId, 'string');

        $customers = $em->getRepository(Customer::class)->findAll();
        self::assertNotEmpty($customers);
        foreach ($customers as $customer) {
            self::assertSame($organizationAId, $customer->getOrganizationId()->toRfc4122());
        }

        $invoices = $em->getRepository(Invoice::class)->findAll();
        self::assertNotEmpty($invoices);
        foreach ($invoices as $invoice) {
            self::assertSame($organizationAId, $invoice->getOrganizationId()->toRfc4122());
        }

        $em->getFilters()->disable('tenant_filter');
    }

    /**
     * Extension Phase 5 (docs/12-roadmap.md) de TC-TENANT-003/006 : ComplianceAnalysis, la
     * ressource la plus sensible de cette phase (résultat de conformité), ne doit jamais
     * fuiter au niveau Doctrine lorsque TenantFilter est actif pour l'organisation A.
     */
    public function testTcTenant007DoctrineLevelIsolationOnComplianceAnalysis(): void
    {
        [$client, $tokenA, $tokenB] = $this->tokensForTwoTenants('tenant-a-007@example.test', 'tenant-b-007@example.test');
        $this->markEmailVerified('tenant-a-007@example.test');
        $this->markEmailVerified('tenant-b-007@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => ['vat_status' => 'ASSUJETTI_REDEVABLE', 'employees_count' => 5, 'annual_turnover' => '200000', 'annual_balance_sheet_total' => '150000'],
        ]);
        $organizationAId = $this->jsonBody($client)['data']['id'];
        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PROFESSIONNEL_FRANCAIS', 'name' => 'Client A', 'siren' => '123456789', 'country' => 'FR']);
        $customerAId = $this->jsonBody($client)['data']['id'];
        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerAId,
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Ligne', 'quantity' => '1', 'unit_price_ht' => '10.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invoice-create-'.bin2hex(random_bytes(8))]);
        $invoiceAId = $this->jsonBody($client)['data']['id'];
        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceAId), [], ['HTTP_IDEMPOTENCY_KEY' => 'tenant-007-a']);
        self::assertResponseStatusCodeSame(200);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => ['vat_status' => 'ASSUJETTI_REDEVABLE', 'employees_count' => 5, 'annual_turnover' => '200000', 'annual_balance_sheet_total' => '150000'],
        ]);
        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PROFESSIONNEL_FRANCAIS', 'name' => 'Client B', 'siren' => '987654321', 'country' => 'FR']);
        $customerBId = $this->jsonBody($client)['data']['id'];
        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerBId,
            'operation_type' => 'VENTE_BIEN',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Ligne', 'quantity' => '1', 'unit_price_ht' => '10.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invoice-create-'.bin2hex(random_bytes(8))]);
        $invoiceBId = $this->jsonBody($client)['data']['id'];
        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceBId), [], ['HTTP_IDEMPOTENCY_KEY' => 'tenant-007-b']);
        self::assertResponseStatusCodeSame(200);

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->getFilters()->enable('tenant_filter')->setParameter('organization_id', $organizationAId, 'string');

        $analyses = $em->getRepository(ComplianceAnalysis::class)->findAll();
        self::assertNotEmpty($analyses);
        foreach ($analyses as $analysis) {
            self::assertSame($organizationAId, $analysis->getOrganizationId()->toRfc4122());
        }

        $em->getFilters()->disable('tenant_filter');
    }

    /**
     * Phase 10 : GET /audit-events (docs/08-api-specification.md, section 39), écart
     * d'implémentation fermé cette phase. AuditLogEntry n'implémentant pas
     * TenantScopedInterface (voir son docblock), ce filtrage est manuel côté
     * App\Shared\Audit\Repository\AuditLogEntryRepository - ce test vérifie donc au niveau
     * API, pas seulement Doctrine, qu'aucune fuite cross-tenant n'est possible sur ce
     * nouvel endpoint.
     */
    public function testTcTenant008ApiIsolationOnAuditEvents(): void
    {
        [$client, $tokenA, $tokenB] = $this->tokensForTwoTenants('tenant-a-008@example.test', 'tenant-b-008@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', ['legal_name' => 'Organisation A']);
        self::assertResponseStatusCodeSame(200);

        $client->jsonRequest('GET', '/api/v1/audit-events');
        self::assertResponseStatusCodeSame(200);
        $bodyA = $this->jsonBody($client);
        self::assertGreaterThan(0, $bodyA['meta']['pagination']['total_count'], 'Organisation A doit voir ses propres événements.');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('GET', '/api/v1/audit-events');
        self::assertResponseStatusCodeSame(200);
        $bodyB = $this->jsonBody($client);
        self::assertSame(0, $bodyB['meta']['pagination']['total_count'], 'Aucun événement de A ne doit fuiter vers B.');
    }

    /**
     * Phase 15 (ADR-009) : TenantFilter ne doit jamais s'activer pour une identité
     * App\PlatformAdmin\Entity\PlatformAdministrator - vérifié ici au niveau Doctrine (pas
     * seulement au niveau HTTP, déjà couvert par App\Tests\Functional\PlatformAdmin\
     * PlatformAdminAuthenticationTest) : un PlatformAdministrator authentifié doit pouvoir
     * lire des Organization appartenant à des tenants distincts dans le même processus,
     * précisément parce qu'aucun filtre n'a jamais été activé pour sa session.
     */
    public function testTcTenant009PlatformAdminNeverActivatesTenantFilter(): void
    {
        $client = static::createClient();
        $ownerA = $this->registerUser('tenant-a-009@example.test');
        $ownerB = $this->registerUser('tenant-b-009@example.test');
        $organizationAId = $ownerA->getMemberships()->first()->getOrganizationId()->toRfc4122();
        $organizationBId = $ownerB->getMemberships()->first()->getOrganizationId()->toRfc4122();

        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('tenant-009-admin@example.test');
        $adminToken = $this->loginPlatformAdministrator($client, 'tenant-009-admin@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('GET', '/api/v1/platform-admin/organizations');
        self::assertResponseStatusCodeSame(200);
        $ids = array_column($this->jsonBody($client)['data'], 'id');

        self::assertContains($organizationAId, $ids);
        self::assertContains($organizationBId, $ids, 'Un PlatformAdministrator doit voir les organisations de plusieurs tenants dans le même appel - preuve que tenant_filter n\'a jamais été activé pour cette session.');
    }
}
