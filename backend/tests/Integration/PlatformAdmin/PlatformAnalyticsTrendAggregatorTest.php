<?php

declare(strict_types=1);

namespace App\Tests\Integration\PlatformAdmin;

use App\Compliance\Engine\Enum\ComplianceResult;
use App\PlatformAdmin\Service\PlatformAnalyticsTrendAggregator;
use PHPUnit\Framework\TestCase;

/**
 * GET /platform-admin/analytics/trends (docs/08-api-specification.md, section 38.3 ;
 * US-ANALYTICS-002). Service pur, pas de dépendance à la base de données (même esprit que
 * App\Tests\Integration\Compliance\DashboardAggregatorTest pour DashboardAggregator) - les
 * requêtes cross-tenant elles-mêmes sont couvertes par
 * App\Tests\Functional\PlatformAdmin\GetPlatformAnalyticsTrendsControllerTest, pas ici.
 */
final class PlatformAnalyticsTrendAggregatorTest extends TestCase
{
    private PlatformAnalyticsTrendAggregator $aggregator;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->aggregator = new PlatformAnalyticsTrendAggregator();
        // Heure fixe arbitraire dans la journée (15:30 UTC) : le contrat de fenêtre tronque
        // toujours à 00:00:00 UTC, l'heure exacte de $now ne doit donc jamais influencer les
        // bornes calculées.
        $this->now = new \DateTimeImmutable('2026-08-26 15:30:00', new \DateTimeZone('UTC'));
    }

    public function testEmptyInputsProduceNinetyZeroBuckets(): void
    {
        $points = $this->aggregator->aggregate($this->now, [], [], []);

        self::assertCount(PlatformAnalyticsTrendAggregator::WINDOW_DAYS, $points);
        foreach ($points as $point) {
            self::assertSame(0, $point['organizations_created']);
            self::assertSame(0, $point['users_created']);
            self::assertSame(0, $point['compliance_analyses_count']);
            self::assertSame('0', $point['compliance_rate']);
        }
    }

    public function testWindowIsExactlyNinetyDaysEndingToday(): void
    {
        $points = $this->aggregator->aggregate($this->now, [], [], []);

        self::assertSame('2026-05-29', $points[0]['date'], 'Premier bucket attendu : aujourd\'hui (2026-08-26) moins 89 jours.');
        self::assertSame('2026-08-26', $points[89]['date'], 'Dernier bucket attendu : aujourd\'hui, jamais exclu ni décalé.');
    }

    public function testBucketsAreOrderedChronologicallyOldestFirst(): void
    {
        $points = $this->aggregator->aggregate($this->now, [], [], []);

        $dates = array_column($points, 'date');
        $sorted = $dates;
        sort($sorted);

        self::assertSame($sorted, $dates);
    }

    public function testDataOnASingleDayIsCountedInThatBucketOnly(): void
    {
        $day = new \DateTimeImmutable('2026-07-15 09:00:00', new \DateTimeZone('UTC'));

        $points = $this->aggregator->aggregate(
            $this->now,
            [$day, $day->modify('+2 hours')],
            [$day->modify('+5 hours')],
            [
                ['triggeredAt' => $day->modify('+1 hour'), 'globalResult' => ComplianceResult::CONFORME],
                ['triggeredAt' => $day->modify('+8 hours'), 'globalResult' => ComplianceResult::NON_CONFORME],
            ],
        );

        $byDate = self::indexByDate($points);

        self::assertSame(2, $byDate['2026-07-15']['organizations_created']);
        self::assertSame(1, $byDate['2026-07-15']['users_created']);
        self::assertSame(2, $byDate['2026-07-15']['compliance_analyses_count']);
        self::assertSame('0.5', $byDate['2026-07-15']['compliance_rate']);

        foreach ($points as $point) {
            if ('2026-07-15' === $point['date']) {
                continue;
            }
            self::assertSame(0, $point['organizations_created']);
            self::assertSame(0, $point['users_created']);
            self::assertSame(0, $point['compliance_analyses_count']);
        }
    }

    public function testComplianceRateCountsOnlyConformeAsPositiveAndOtherResultsAsNegative(): void
    {
        $day = new \DateTimeImmutable('2026-08-01 12:00:00', new \DateTimeZone('UTC'));

        $points = $this->aggregator->aggregate(
            $this->now,
            [],
            [],
            [
                ['triggeredAt' => $day, 'globalResult' => ComplianceResult::CONFORME],
                ['triggeredAt' => $day, 'globalResult' => ComplianceResult::CONFORME],
                ['triggeredAt' => $day, 'globalResult' => ComplianceResult::CONFORME],
                ['triggeredAt' => $day, 'globalResult' => ComplianceResult::NON_CONFORME],
                ['triggeredAt' => $day, 'globalResult' => ComplianceResult::A_VERIFIER],
                ['triggeredAt' => $day, 'globalResult' => ComplianceResult::AVERTISSEMENT],
                ['triggeredAt' => $day, 'globalResult' => ComplianceResult::NON_APPLICABLE],
                ['triggeredAt' => $day, 'globalResult' => ComplianceResult::INCERTAIN_REGLEMENTAIRE],
                ['triggeredAt' => $day, 'globalResult' => ComplianceResult::NON_CONFORME],
                ['triggeredAt' => $day, 'globalResult' => ComplianceResult::NON_CONFORME],
            ],
        );

        $byDate = self::indexByDate($points);

        self::assertSame(10, $byDate['2026-08-01']['compliance_analyses_count']);
        self::assertSame('0.3', $byDate['2026-08-01']['compliance_rate']);
    }

    public function testDataOutsideTheWindowIsIgnored(): void
    {
        $tooOld = new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC'));

        $points = $this->aggregator->aggregate(
            $this->now,
            [$tooOld],
            [$tooOld],
            [['triggeredAt' => $tooOld, 'globalResult' => ComplianceResult::CONFORME]],
        );

        foreach ($points as $point) {
            self::assertSame(0, $point['organizations_created']);
            self::assertSame(0, $point['users_created']);
            self::assertSame(0, $point['compliance_analyses_count']);
        }
    }

    /**
     * @param list<array{date: string, organizations_created: int, users_created: int, compliance_analyses_count: int, compliance_rate: string}> $points
     *
     * @return array<string, array{date: string, organizations_created: int, users_created: int, compliance_analyses_count: int, compliance_rate: string}>
     */
    private static function indexByDate(array $points): array
    {
        $byDate = [];
        foreach ($points as $point) {
            $byDate[$point['date']] = $point;
        }

        return $byDate;
    }
}
