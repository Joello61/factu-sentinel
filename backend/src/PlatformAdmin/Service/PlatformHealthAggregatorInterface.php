<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Service;

/**
 * Interface exposée par PlatformAdmin pour son seul consommateur cross-module légitime,
 * App\Shared\Metrics\MetricsRecorder (Phase 18, étape 2) - même raisonnement que
 * ComplianceHealthReaderInterface/AiUsageReaderInterface (Phase 15), en sens inverse :
 * MetricsRecorder ne connaît jamais App\PlatformAdmin\Service\PlatformHealthAggregator
 * directement (backend/CLAUDE.md, section 3).
 */
interface PlatformHealthAggregatorInterface
{
    /** @return array<string, mixed> */
    public function aggregate(): array;
}
