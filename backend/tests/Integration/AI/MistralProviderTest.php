<?php

declare(strict_types=1);

namespace App\Tests\Integration\AI;

use App\AI\Exception\AiProviderUnavailableException;
use App\AI\Service\MistralProvider;
use App\PlatformAdmin\Service\PlatformHealthAggregatorInterface;
use App\Shared\Metrics\MetricsRecorder;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * App\AI\Service\MistralProvider - jamais d'appel réseau réel (backend/CLAUDE.md,
 * section 13), même principe que le reste du projet pour les dépendances externes, mais
 * mocké au niveau HTTP plutôt qu'au niveau de l'interface : ce test vérifie précisément la
 * forme de la requête envoyée au fournisseur (endpoint, authentification, absence de clé
 * "tools" - garde-fou demandé en revue du plan Phase 8), pas seulement le comportement de
 * l'appelant.
 */
final class MistralProviderTest extends TestCase
{
    public function testRequestShapeHasNoToolsKeyAndCarriesExpectedFields(): void
    {
        $capturedBody = null;
        $capturedUrl = null;
        $capturedAuthHeader = null;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody, &$capturedUrl, &$capturedAuthHeader): MockResponse {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            foreach ($options['headers'] as $header) {
                if (str_starts_with($header, 'Authorization:')) {
                    $capturedAuthHeader = $header;
                }
            }

            return new MockResponse(json_encode([
                'choices' => [['message' => ['content' => 'Texte généré.']]],
            ], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $provider = new MistralProvider($httpClient, $this->createMetricsRecorder(), 'test-api-key', 'https://api.mistral.ai', 'mistral-small-latest');

        $result = $provider->complete('system prompt', 'user prompt', 5.0);

        self::assertSame('Texte généré.', $result);
        self::assertSame('https://api.mistral.ai/v1/chat/completions', $capturedUrl);
        self::assertSame('Authorization: Bearer test-api-key', $capturedAuthHeader);
        self::assertArrayNotHasKey('tools', $capturedBody, 'Aucune clé "tools" : le fournisseur ne doit avoir aucune surface d\'appel d\'outil.');
        self::assertSame('mistral-small-latest', $capturedBody['model']);
        self::assertSame('system prompt', $capturedBody['messages'][0]['content']);
        self::assertSame('system', $capturedBody['messages'][0]['role']);
        self::assertSame('user prompt', $capturedBody['messages'][1]['content']);
        self::assertSame('user', $capturedBody['messages'][1]['role']);
    }

    public function testNon200StatusThrowsAiProviderUnavailableException(): void
    {
        $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('Internal error', ['http_code' => 500]));
        $provider = new MistralProvider($httpClient, $this->createMetricsRecorder(), 'test-api-key', 'https://api.mistral.ai', 'mistral-small-latest');

        $this->expectException(AiProviderUnavailableException::class);

        $provider->complete('system', 'user', 5.0);
    }

    public function testNetworkFailureThrowsAiProviderUnavailableException(): void
    {
        $httpClient = new MockHttpClient(function (): MockResponse {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('Connection refused.');
        });
        $provider = new MistralProvider($httpClient, $this->createMetricsRecorder(), 'test-api-key', 'https://api.mistral.ai', 'mistral-small-latest');

        $this->expectException(AiProviderUnavailableException::class);

        $provider->complete('system', 'user', 5.0);
    }

    public function testMissingContentInResponseThrowsAiProviderUnavailableException(): void
    {
        $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse(json_encode(['choices' => []], \JSON_THROW_ON_ERROR), ['http_code' => 200]));
        $provider = new MistralProvider($httpClient, $this->createMetricsRecorder(), 'test-api-key', 'https://api.mistral.ai', 'mistral-small-latest');

        $this->expectException(AiProviderUnavailableException::class);

        $provider->complete('system', 'user', 5.0);
    }

    private function createMetricsRecorder(): MetricsRecorder
    {
        $platformHealthAggregator = new class implements PlatformHealthAggregatorInterface {
            public function aggregate(): array
            {
                return [];
            }
        };

        return new MetricsRecorder(new CollectorRegistry(new InMemory()), $platformHealthAggregator);
    }
}
