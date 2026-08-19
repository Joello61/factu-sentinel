<?php

declare(strict_types=1);

namespace App\Tests\Integration\Compliance;

use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Engine\Service\ComplianceFindingDraft;
use App\Compliance\Engine\Service\ComplianceResultAggregator;
use App\Compliance\Rules\Entity\RegulatoryRule;
use App\Compliance\Rules\Entity\RuleVersion;
use App\Compliance\Rules\Enum\ConfidenceLevel;
use App\Compliance\Rules\Enum\RuleCategory;
use App\Compliance\Rules\Enum\RuleStatus;
use PHPUnit\Framework\TestCase;

/**
 * Table de précédence explicite (docs/06-technical-architecture.md, section 9) : pas de
 * dépendance à la base de données, service pur.
 */
final class ComplianceResultAggregatorTest extends TestCase
{
    private ComplianceResultAggregator $aggregator;

    protected function setUp(): void
    {
        $this->aggregator = new ComplianceResultAggregator();
    }

    private function draft(ComplianceResult $result): ComplianceFindingDraft
    {
        $rule = new RegulatoryRule('test-rule', 'Test', 'Test', RuleCategory::MENTION_OBLIGATOIRE, 'FR', RuleStatus::ACTIVE);
        $version = new RuleVersion($rule, 1, new \DateTimeImmutable('2026-01-01'), [], 'NON_CONFORME', 'test', ConfidenceLevel::ELEVE, 'template');

        return new ComplianceFindingDraft($version, $result, 'message', null, null, null);
    }

    public function testAllConformeOrNonApplicableYieldsConforme(): void
    {
        $result = $this->aggregator->aggregate([$this->draft(ComplianceResult::CONFORME), $this->draft(ComplianceResult::NON_APPLICABLE)]);

        self::assertSame(ComplianceResult::CONFORME, $result);
    }

    public function testNonConformeTakesPrecedenceOverEverythingElse(): void
    {
        $result = $this->aggregator->aggregate([
            $this->draft(ComplianceResult::AVERTISSEMENT),
            $this->draft(ComplianceResult::A_VERIFIER),
            $this->draft(ComplianceResult::INCERTAIN_REGLEMENTAIRE),
            $this->draft(ComplianceResult::NON_CONFORME),
            $this->draft(ComplianceResult::CONFORME),
        ]);

        self::assertSame(ComplianceResult::NON_CONFORME, $result);
    }

    public function testIncertainReglementaireTakesPrecedenceOverAVerifierAndAvertissement(): void
    {
        $result = $this->aggregator->aggregate([
            $this->draft(ComplianceResult::AVERTISSEMENT),
            $this->draft(ComplianceResult::A_VERIFIER),
            $this->draft(ComplianceResult::INCERTAIN_REGLEMENTAIRE),
        ]);

        self::assertSame(ComplianceResult::INCERTAIN_REGLEMENTAIRE, $result);
    }

    public function testAVerifierTakesPrecedenceOverAvertissement(): void
    {
        $result = $this->aggregator->aggregate([
            $this->draft(ComplianceResult::AVERTISSEMENT),
            $this->draft(ComplianceResult::A_VERIFIER),
        ]);

        self::assertSame(ComplianceResult::A_VERIFIER, $result);
    }

    public function testNonApplicableIsAlwaysIgnored(): void
    {
        $result = $this->aggregator->aggregate([$this->draft(ComplianceResult::NON_APPLICABLE), $this->draft(ComplianceResult::NON_APPLICABLE)]);

        self::assertSame(ComplianceResult::CONFORME, $result);
    }
}
