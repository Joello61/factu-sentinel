<?php

declare(strict_types=1);

namespace App\Document\Service;

use App\Shared\Metrics\MetricsRecorder;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implémentation HTTP du Validator Container (ADR-008). Le wrapper Java du conteneur
 * "mustang" (docker/mustang/) porte le signal à 3 branches directement sur le statut HTTP,
 * jamais un corps JSON à interpréter (plan Phase 7) :
 *   - POST /extract : 200 + corps XML = XML trouvé ; 204 = pas de XML embarqué (propre) ;
 *     tout le reste (5xx, timeout, connexion refusée) = indisponibilité du service.
 *   - POST /validate : 200 + corps XML = rapport de validation ; tout le reste = échec.
 *
 * $baseUrl (MUSTANG_BASE_URL, backend/.env) pointe toujours vers le réseau Docker interne,
 * jamais une adresse publique (docker-compose.yml, service "mustang" sans "ports:").
 */
final class MustangValidatorClient implements StructuredDocumentValidatorInterface
{
    // Cohérent avec le timeout de 30s appliqué côté conteneur (docker/mustang/src/
    // MustangWrapper.java) - une marge est ajoutée pour laisser le conteneur répondre avec
    // son propre 502 plutôt que de couper la connexion en premier.
    private const float TIMEOUT_SECONDS = 35.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly MetricsRecorder $metricsRecorder,
        private readonly string $mustangBaseUrl,
    ) {
    }

    public function extract(string $content): MustangExtractionResult
    {
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->mustangBaseUrl.'/extract', [
                'body' => $content,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $statusCode = $response->getStatusCode();

            if (200 === $statusCode) {
                $this->metricsRecorder->recordMustangCall('extract', 'xml_found', microtime(true) - $startedAt);

                return MustangExtractionResult::xmlFound($response->getContent());
            }

            if (204 === $statusCode) {
                $this->metricsRecorder->recordMustangCall('extract', 'no_xml', microtime(true) - $startedAt);

                return MustangExtractionResult::noXmlEmbedded();
            }

            $this->metricsRecorder->recordMustangCall('extract', 'service_error', microtime(true) - $startedAt);

            return MustangExtractionResult::serviceError();
        } catch (HttpClientExceptionInterface) {
            $this->metricsRecorder->recordMustangCall('extract', 'service_error', microtime(true) - $startedAt);

            return MustangExtractionResult::serviceError();
        }
    }

    public function validate(string $content): string
    {
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->mustangBaseUrl.'/validate', [
                'body' => $content,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException('Mustang validation report was not produced.');
            }

            $this->metricsRecorder->recordMustangCall('validate', 'success', microtime(true) - $startedAt);

            return $response->getContent();
        } catch (HttpClientExceptionInterface $exception) {
            $this->metricsRecorder->recordMustangCall('validate', 'error', microtime(true) - $startedAt);

            throw new \RuntimeException('Validator Container unavailable.', previous: $exception);
        } catch (\RuntimeException $exception) {
            $this->metricsRecorder->recordMustangCall('validate', 'error', microtime(true) - $startedAt);

            throw $exception;
        }
    }
}
