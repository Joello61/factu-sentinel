<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

/**
 * Interface exposée par le module Compliance/Engine pour son seul consommateur cross-tenant
 * légitime, App\PlatformAdmin\Service\PlatformHealthAggregator (US-PLATFORMADMIN-005, plan
 * Phase 15) - un module ne lit jamais directement les données internes d'un autre module
 * (backend/CLAUDE.md, section 3) ; PlatformAdmin ne connaît donc jamais
 * App\Compliance\Engine\Repository\ComplianceAnalysisRepository directement.
 */
interface ComplianceHealthReaderInterface
{
    /** Décimal en chaîne (convention API montants/ratios, ../CLAUDE.md section 11) - "0" si aucune analyse sur la période. */
    public function getFailureRateLast24Hours(): string;
}
