<?php

declare(strict_types=1);

namespace App\AI\Service;

use App\AI\Exception\AiProviderUnavailableException;
use App\Shared\Metrics\MetricsRecorder;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implémentation Mistral de AIProviderInterface (docs/06-technical-architecture.md, ADR-007 :
 * fournisseur IA retenu). Même style que App\Document\Service\MustangValidatorClient : appel
 * HTTP direct (symfony/http-client, aucun SDK PHP officiel Mistral n'existe - vérifié sur
 * docs.mistral.ai au moment de l'implémentation), non-200 ou exception réseau -> exception
 * typée, jamais propagée telle quelle vers l'appelant.
 *
 * Aucune clé "tools" dans le corps de la requête : le fournisseur n'a structurellement aucune
 * surface d'appel d'outil/fonction (docs/10-security-privacy.md, section 31).
 */
final class MistralProvider implements AIProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly MetricsRecorder $metricsRecorder,
        private readonly string $mistralApiKey,
        private readonly string $mistralBaseUrl,
        private readonly string $mistralModel,
    ) {
    }

    public function complete(string $systemPrompt, string $userPrompt, float $timeoutSeconds): string
    {
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->mistralBaseUrl.'/v1/chat/completions', [
                'auth_bearer' => $this->mistralApiKey,
                'timeout' => $timeoutSeconds,
                'json' => [
                    'model' => $this->mistralModel,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'response_format' => ['type' => 'text'],
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new AiProviderUnavailableException('Mistral returned a non-200 status.');
            }

            $body = $response->toArray(throw: false);
            $content = $body['choices'][0]['message']['content'] ?? null;

            if (!is_string($content) || '' === trim($content)) {
                throw new AiProviderUnavailableException('Mistral response did not contain usable content.');
            }

            $this->metricsRecorder->recordAiCall('success', microtime(true) - $startedAt);

            return $content;
        } catch (HttpClientExceptionInterface $exception) {
            $this->metricsRecorder->recordAiCall('error', microtime(true) - $startedAt);

            throw new AiProviderUnavailableException('Mistral provider unavailable.', previous: $exception);
        } catch (AiProviderUnavailableException $exception) {
            $this->metricsRecorder->recordAiCall('error', microtime(true) - $startedAt);

            throw $exception;
        }
    }
}
