<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Service;

use App\AI\Service\AiUsageReaderInterface;
use App\Compliance\Engine\Service\ComplianceHealthReaderInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

/**
 * GET /platform-admin/health (docs/08-api-specification.md, section 38.2 ;
 * US-PLATFORMADMIN-005). N'agrège jamais directement les tables internes d'un autre module
 * (backend/CLAUDE.md, section 3) - consomme exclusivement les interfaces exposées par
 * Compliance/Engine et AI (voir plan Phase 15). La file morte asynchrone reste une exception
 * volontaire : c'est de l'infrastructure Messenger, pas une donnée métier d'un autre module.
 *
 * Limité au niveau applicatif (docs/08-api-specification.md, section 38.2) - jamais de
 * métrique d'infrastructure hôte (CPU, disque, mémoire), qui relève du monitoring
 * auto-hébergé externe (Phase 17, docs/12-roadmap.md §41), pas de cette surface
 * authentifiée. Étendu en Phase 17 avec la connectivité Redis/Mustang (`redisReachable`/
 * `mustangReachable`), différée jusqu'ici faute d'hébergeur retenu ("aucun indicateur
 * d'infrastructure réelle tant qu'aucun hébergeur n'est retenu" - ancien commentaire de
 * cette classe, désormais caduc : OVHcloud est retenu). Vérification volontairement légère
 * (connexion TCP brute, jamais une opération applicative coûteuse) - même compromis que
 * les HEALTHCHECK Docker de backend/frontend (`backend/Dockerfile`, `frontend/Dockerfile`) :
 * prouve la joignabilité réseau, pas la santé fonctionnelle complète du service distant.
 */
final readonly class PlatformHealthAggregator implements PlatformHealthAggregatorInterface
{
    private const float CONNECT_TIMEOUT_SECONDS = 2.0;

    public function __construct(
        private ComplianceHealthReaderInterface $complianceHealthReader,
        private AiUsageReaderInterface $aiUsageReader,
        private Connection $connection,
        private string $redisUrl,
        private string $mustangBaseUrl,
    ) {
    }

    /** @return array<string, mixed> */
    public function aggregate(): array
    {
        $usage = $this->aiUsageReader->getUsageLast24Hours();

        return [
            'compliance_engine_failure_rate_24h' => $this->complianceHealthReader->getFailureRateLast24Hours(),
            'async_jobs_dead_letter_count' => $this->countDeadLetterMessages(),
            'ai_calls_volume_24h' => $usage['volume'],
            'ai_estimated_cost_24h' => $usage['estimatedCost'],
            'api_health' => $this->isDatabaseReachable() ? 'ok' : 'degraded',
            'redis_reachable' => $this->isTcpReachable($this->redisUrl),
            'mustang_reachable' => $this->isTcpReachable($this->mustangBaseUrl),
        ];
    }

    private function countDeadLetterMessages(): int
    {
        // config/packages/messenger.yaml : transport "failed" -> doctrine://default?queue_name=failed.
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queue',
            ['queue' => 'failed'],
        );
    }

    private function isDatabaseReachable(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (DbalException) {
            return false;
        }
    }

    private function isTcpReachable(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (!\is_string($host) || !\is_int($port)) {
            return false;
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, self::CONNECT_TIMEOUT_SECONDS);
        if (false === $socket) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
