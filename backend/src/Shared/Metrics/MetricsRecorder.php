<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use App\PlatformAdmin\Service\PlatformHealthAggregatorInterface;
use Prometheus\CollectorRegistry;

/**
 * Point d'entrée unique vers le registre Prometheus (Phase 18, étape 2) - isole
 * promphp/artprima du reste du code métier, qui n'appelle jamais directement le registre.
 * getOrRegister* est idempotent (une seule instance par métrique, jamais recréée à chaque
 * appel).
 *
 * `result`/`outcome` restent toujours des valeurs métier déjà existantes (six états de
 * ComplianceResult, ou un succès/échec technique simple) - jamais mélangés dans une même
 * métrique un résultat de conformité et une erreur technique
 * (../../../CLAUDE.md section 9 : même principe appliqué ici qu'à l'affichage).
 */
final class MetricsRecorder
{
    private const string NAMESPACE = 'factusentinel';

    public function __construct(
        private readonly CollectorRegistry $registry,
        private readonly PlatformHealthAggregatorInterface $platformHealthAggregator,
    ) {
    }

    public function recordComplianceAnalysis(string $result, float $durationSeconds): void
    {
        $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'compliance_analyses_total',
            "Nombre d'analyses de conformité terminées, par résultat global.",
            ['result'],
        )->inc([$result]);

        $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'compliance_analysis_duration_seconds',
            'Durée d\'une analyse de conformité, par résultat global.',
            ['result'],
        )->observe($durationSeconds, [$result]);
    }

    public function recordDocumentUpload(string $outcome): void
    {
        $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'document_uploads_total',
            "Nombre d'imports de documents, par issue.",
            ['outcome'],
        )->inc([$outcome]);
    }

    public function recordAiCall(string $outcome, float $durationSeconds): void
    {
        $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'ai_calls_total',
            "Nombre d'appels à l'AI Gateway, par issue.",
            ['outcome'],
        )->inc([$outcome]);

        $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'ai_call_duration_seconds',
            "Durée d'un appel à l'AI Gateway, par issue.",
            ['outcome'],
        )->observe($durationSeconds, [$outcome]);
    }

    /** $operation : "extract" ou "validate" (App\Document\Service\MustangValidatorClient). */
    public function recordMustangCall(string $operation, string $outcome, float $durationSeconds): void
    {
        $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'mustang_calls_total',
            'Nombre d\'appels au Validator Container Mustang, par opération et par issue.',
            ['operation', 'outcome'],
        )->inc([$operation, $outcome]);

        $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'mustang_call_duration_seconds',
            'Durée d\'un appel au Validator Container Mustang, par opération et par issue.',
            ['operation', 'outcome'],
        )->observe($durationSeconds, [$operation, $outcome]);
    }

    /**
     * Jauges calculées à chaque scrape (jamais accumulées) - réutilise directement
     * PlatformHealthAggregator, jamais une seconde implémentation de ce calcul
     * (backend/CLAUDE.md, section 14 : pas de duplication de logique métier).
     */
    public function recordCurrentHealthGauges(): void
    {
        $health = $this->platformHealthAggregator->aggregate();

        $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'compliance_engine_failure_rate_24h',
            "Taux d'échec du Compliance Engine sur les dernières 24h.",
        )->set((float) $health['compliance_engine_failure_rate_24h']);

        $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'async_jobs_dead_letter_count',
            'Nombre de messages Messenger en échec définitif (file "failed").',
        )->set((float) $health['async_jobs_dead_letter_count']);

        $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'ai_calls_volume_24h',
            "Volume d'appels IA sur les dernières 24h.",
        )->set((float) $health['ai_calls_volume_24h']);

        $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'ai_estimated_cost_24h_eur',
            "Coût IA estimé sur les dernières 24h, en euros.",
        )->set((float) $health['ai_estimated_cost_24h']);

        $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'redis_reachable',
            'Connectivité Redis (1 = joignable, 0 = injoignable).',
        )->set($health['redis_reachable'] ? 1.0 : 0.0);

        $this->registry->getOrRegisterGauge(
            self::NAMESPACE,
            'mustang_reachable',
            'Connectivité Mustang (1 = joignable, 0 = injoignable).',
        )->set($health['mustang_reachable'] ? 1.0 : 0.0);
    }
}
