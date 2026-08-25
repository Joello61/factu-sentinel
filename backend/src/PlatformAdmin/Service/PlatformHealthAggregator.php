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
 * Explicitement limité au niveau applicatif (docs/08-api-specification.md, section 38.2) -
 * aucun indicateur d'infrastructure réelle (uptime, ressources serveur) tant qu'aucun
 * hébergeur n'est retenu (Phase 17).
 */
final readonly class PlatformHealthAggregator
{
    public function __construct(
        private ComplianceHealthReaderInterface $complianceHealthReader,
        private AiUsageReaderInterface $aiUsageReader,
        private Connection $connection,
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
}
