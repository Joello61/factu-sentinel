<?php

declare(strict_types=1);

namespace App\Tests\Functional\AI;

use App\Shared\Audit\Entity\AuditLogEntry;
use App\Shared\Audit\Enum\EventType;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeAIProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * POST /compliance-findings/{id}/explanations (docs/08-api-specification.md, section 35 ;
 * US-AI-001). App\AI\Service\AIProviderInterface est remplacé par
 * App\Tests\Support\FakeAIProvider en environnement de test (backend/config/services.yaml,
 * bloc when@test) : aucun appel réseau réel vers Mistral.
 */
final class ExplainComplianceFindingControllerTest extends ApiTestCase
{
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

    private function createCustomer(KernelBrowser $client, ?string $siren = null): string
    {
        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client Test SARL',
            'siren' => $siren,
            'country' => 'FR',
        ]);
        self::assertResponseStatusCodeSame(201);

        return $this->jsonBody($client)['data']['id'];
    }

    private function createInvoice(KernelBrowser $client, string $customerId): string
    {
        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'PRESTATION_SERVICE',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Prestation', 'quantity' => '1', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'ai-invoice-'.bin2hex(random_bytes(8))]);
        self::assertResponseStatusCodeSame(201);

        return $this->jsonBody($client)['data']['id'];
    }

    /** SIREN manquant -> finding NON_CONFORME garanti, utilisable pour ces tests. */
    private function createNonConformeFindingId(KernelBrowser $client): string
    {
        $this->configureFiscalContext($client);
        $customerId = $this->createCustomer($client, null);
        $invoiceId = $this->createInvoice($client, $customerId);

        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'ai-analysis-'.bin2hex(random_bytes(8))]);
        self::assertResponseStatusCodeSame(200);

        $findings = $this->jsonBody($client)['data']['findings'];
        $finding = current(array_filter($findings, static fn (array $f): bool => 'NON_CONFORME' === $f['result']));
        self::assertIsArray($finding);

        return $finding['id'];
    }

    private function fakeProvider(): FakeAIProvider
    {
        return static::getContainer()->get(FakeAIProvider::class);
    }

    public function testEmailNotVerifiedReturns403(): void
    {
        $client = $this->createAuthenticatedClient('ai-explain-001@example.test');
        // Depuis la Phase 10, POST /invoices/{id}/compliance-analyses exige lui aussi un
        // email vérifié (App\Shared\Security\EmailVerificationGuard) - vérifié le temps de
        // constituer le finding, puis remis à l'état non vérifié pour tester réellement le
        // rejet de POST /compliance-findings/{id}/explanations par un compte non vérifié,
        // qui reste l'objet précis de ce test.
        $this->markEmailVerified('ai-explain-001@example.test');
        $findingId = $this->createNonConformeFindingId($client);
        $this->markEmailUnverified('ai-explain-001@example.test');

        $client->jsonRequest('POST', sprintf('/api/v1/compliance-findings/%s/explanations', $findingId));

        self::assertResponseStatusCodeSame(403);
        self::assertSame('EMAIL_VERIFICATION_REQUIRED', $this->jsonBody($client)['error']['code']);
    }

    public function testUnknownFindingReturns404(): void
    {
        $client = $this->createAuthenticatedClient('ai-explain-002@example.test');
        $this->markEmailVerified('ai-explain-002@example.test');

        $client->jsonRequest('POST', '/api/v1/compliance-findings/00000000-0000-7000-8000-000000000000/explanations');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOtherTenantFindingReturns404(): void
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, 'ai-explain-tenant-a@example.test');
        $this->markEmailVerified('ai-explain-tenant-a@example.test');
        $tokenB = $this->loginAs($client, 'ai-explain-tenant-b@example.test');
        $this->markEmailVerified('ai-explain-tenant-b@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $findingId = $this->createNonConformeFindingId($client);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('POST', sprintf('/api/v1/compliance-findings/%s/explanations', $findingId));

        self::assertResponseStatusCodeSame(404, 'Un finding ne doit jamais être accessible depuis une autre organisation.');
    }

    public function testSuccessReturnsExplanationWithFixedSource(): void
    {
        $client = $this->createAuthenticatedClient('ai-explain-003@example.test');
        $this->markEmailVerified('ai-explain-003@example.test');
        $findingId = $this->createNonConformeFindingId($client);

        $client->jsonRequest('POST', sprintf('/api/v1/compliance-findings/%s/explanations', $findingId));

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame($findingId, $data['finding_id']);
        self::assertNotEmpty($data['explanation']);
        self::assertSame('Généré par assistance IA à partir du résultat déterministe existant', $data['source']);
    }

    public function testProviderFailureReturns503AndAuditsWithoutGeneratedText(): void
    {
        $client = $this->createAuthenticatedClient('ai-explain-004@example.test');
        $this->markEmailVerified('ai-explain-004@example.test');
        $findingId = $this->createNonConformeFindingId($client);

        // KernelBrowser reboote le noyau (donc reconstruit le conteneur) à chaque requête
        // par défaut : sans ceci, la mutation ci-dessous sur le double récupéré via le
        // conteneur courant serait perdue avant que la requête suivante ne s'exécute.
        $client->disableReboot();
        $this->fakeProvider()->shouldFail = true;

        $client->jsonRequest('POST', sprintf('/api/v1/compliance-findings/%s/explanations', $findingId));

        self::assertResponseStatusCodeSame(503);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $entries = $em->getRepository(AuditLogEntry::class)->findBy(['eventType' => EventType::COMPLIANCE_FINDING_EXPLAINED, 'entityId' => $findingId]);

        self::assertCount(1, $entries);
        $newState = $entries[0]->getNewState();
        self::assertSame(['success' => false], $newState, 'newState ne doit jamais porter le prompt ou le texte généré.');
    }

    public function testRateLimitReturns429AfterExhaustingLimiter(): void
    {
        $client = $this->createAuthenticatedClient('ai-explain-005@example.test');
        $this->markEmailVerified('ai-explain-005@example.test');
        $findingId = $this->createNonConformeFindingId($client);

        // config/packages/rate_limiter.yaml: ai_assistant = 30/heure.
        for ($i = 0; $i < 30; ++$i) {
            $client->jsonRequest('POST', sprintf('/api/v1/compliance-findings/%s/explanations', $findingId));
            self::assertResponseStatusCodeSame(200);
        }

        $client->jsonRequest('POST', sprintf('/api/v1/compliance-findings/%s/explanations', $findingId));

        self::assertResponseStatusCodeSame(429);
    }
}
