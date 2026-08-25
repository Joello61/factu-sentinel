<?php

declare(strict_types=1);

namespace App\AI\Service;

/**
 * Interface exposée par le module AI pour son seul consommateur cross-tenant légitime,
 * App\PlatformAdmin\Service\PlatformHealthAggregator (US-PLATFORMADMIN-005, plan Phase 15) -
 * PlatformAdmin ne connaît jamais App\AI\Repository\AiCallLogEntryRepository directement
 * (backend/CLAUDE.md, section 3).
 */
interface AiUsageReaderInterface
{
    /** @return array{volume: int, estimatedCost: string} */
    public function getUsageLast24Hours(): array;
}
