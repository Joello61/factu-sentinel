<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Service;

use App\Compliance\Engine\Enum\ComplianceResult;

/**
 * GET /platform-admin/analytics/trends (docs/08-api-specification.md, section 38.3 ;
 * US-ANALYTICS-002). Service pur, pas de dépendance à la base de données - même esprit que
 * App\Compliance\Engine\Service\DashboardAggregator (Phase 9) : le contrôleur récupère les
 * listes brutes via App\Organization\Repository\OrganizationRepository::findCreatedAtBetween(),
 * App\Identity\Repository\UserRepository::findCreatedAtBetween() et
 * App\Compliance\Engine\Service\ComplianceAnalyticsReaderInterface::getCompletedAnalysesSince(),
 * cette classe se contente de les répartir en buckets quotidiens déterministes.
 *
 * Contrat de fenêtre fixe (pas de `?since`/`?until` au MVP de cette phase) : exactement
 * WINDOW_DAYS buckets, en UTC (../../CLAUDE.md section 11 : "horodatages ISO 8601 UTC" -
 * aucune autre convention de fuseau horaire n'existe dans ce projet, jamais Europe/Paris
 * introduit silencieusement ici). `windowStart()` = aujourd'hui à 00:00:00 UTC moins
 * (WINDOW_DAYS - 1) jours ; `windowEnd()` (exclusif) = demain à 00:00:00 UTC. Un jour sans
 * aucune donnée produit un bucket à zéro explicite, jamais un jour absent de la liste -
 * indispensable pour qu'un graphique d'évolution ne donne jamais une impression trompeuse de
 * continuité (docs/11-frontend-design-system.md, section 48).
 */
final class PlatformAnalyticsTrendAggregator
{
    public const int WINDOW_DAYS = 90;

    public static function windowStart(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return self::truncateToUtcDay($now)->modify(sprintf('-%d days', self::WINDOW_DAYS - 1));
    }

    /** Borne exclusive - demain à 00:00:00 UTC. */
    public static function windowEnd(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return self::truncateToUtcDay($now)->modify('+1 day');
    }

    /**
     * @param list<\DateTimeImmutable>                                                   $organizationCreatedAt
     * @param list<\DateTimeImmutable>                                                   $userCreatedAt
     * @param list<array{triggeredAt: \DateTimeImmutable, globalResult: ?ComplianceResult}> $completedAnalyses
     *
     * @return list<array{date: string, organizations_created: int, users_created: int, compliance_analyses_count: int, compliance_rate: string}>
     */
    public function aggregate(\DateTimeImmutable $now, array $organizationCreatedAt, array $userCreatedAt, array $completedAnalyses): array
    {
        $buckets = self::emptyBuckets($now);

        foreach ($organizationCreatedAt as $createdAt) {
            $day = self::bucketKey($createdAt);
            if (isset($buckets[$day])) {
                ++$buckets[$day]['organizations_created'];
            }
        }

        foreach ($userCreatedAt as $createdAt) {
            $day = self::bucketKey($createdAt);
            if (isset($buckets[$day])) {
                ++$buckets[$day]['users_created'];
            }
        }

        foreach ($completedAnalyses as $analysis) {
            $day = self::bucketKey($analysis['triggeredAt']);
            if (!isset($buckets[$day])) {
                continue;
            }

            ++$buckets[$day]['analyses_count'];
            if (ComplianceResult::CONFORME === $analysis['globalResult']) {
                ++$buckets[$day]['conforme_count'];
            }
        }

        return array_map(
            static fn (string $date, array $bucket): array => [
                'date' => $date,
                'organizations_created' => $bucket['organizations_created'],
                'users_created' => $bucket['users_created'],
                'compliance_analyses_count' => $bucket['analyses_count'],
                'compliance_rate' => PlatformAnalyticsAggregator::complianceRate($bucket['analyses_count'], $bucket['conforme_count']),
            ],
            array_keys($buckets),
            $buckets,
        );
    }

    /**
     * @return array<string, array{organizations_created: int, users_created: int, analyses_count: int, conforme_count: int}>
     */
    private static function emptyBuckets(\DateTimeImmutable $now): array
    {
        $buckets = [];
        $cursor = self::windowStart($now);
        $end = self::windowEnd($now);

        while ($cursor < $end) {
            $buckets[$cursor->format('Y-m-d')] = [
                'organizations_created' => 0,
                'users_created' => 0,
                'analyses_count' => 0,
                'conforme_count' => 0,
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $buckets;
    }

    private static function bucketKey(\DateTimeImmutable $at): string
    {
        return self::truncateToUtcDay($at)->format('Y-m-d');
    }

    private static function truncateToUtcDay(\DateTimeImmutable $at): \DateTimeImmutable
    {
        $utc = $at->setTimezone(new \DateTimeZone('UTC'));

        return $utc->setTime(0, 0, 0);
    }
}
