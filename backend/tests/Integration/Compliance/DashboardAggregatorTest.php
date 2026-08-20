<?php

declare(strict_types=1);

namespace App\Tests\Integration\Compliance;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Entity\ComplianceFinding;
use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Engine\Enum\DashboardGlobalStatus;
use App\Compliance\Engine\Service\DashboardAggregator;
use App\Compliance\Rules\Entity\RegulatoryRule;
use App\Compliance\Rules\Entity\RuleVersion;
use App\Compliance\Rules\Enum\ConfidenceLevel;
use App\Compliance\Rules\Enum\RuleCategory;
use App\Compliance\Rules\Enum\RuleStatus;
use App\Customer\Entity\Customer;
use App\Customer\Enum\CustomerType;
use App\Invoicing\Entity\Invoice;
use App\Invoicing\Enum\InvoiceSource;
use App\Invoicing\Enum\OperationType;
use App\Organization\Entity\Organization;
use PHPUnit\Framework\TestCase;

/**
 * Bucketing du Dashboard (docs/08-api-specification.md, section 33, décision produit
 * Phase 9) : service pur, pas de dépendance à la base de données (même esprit que
 * App\Tests\Integration\Compliance\ComplianceResultAggregatorTest pour
 * ComplianceResultAggregator). Prend en entrée la "dernière analyse COMPLETED par facture"
 * déjà résolue par App\Compliance\Engine\Repository\ComplianceAnalysisRepository::
 * findLatestCompletedPerInvoice() -- cette sélection elle-même est couverte par
 * App\Tests\Functional\Compliance\GetDashboardControllerTest, pas ici.
 */
final class DashboardAggregatorTest extends TestCase
{
    private DashboardAggregator $aggregator;
    private Organization $organization;
    private RuleVersion $ruleVersion;

    protected function setUp(): void
    {
        $this->aggregator = new DashboardAggregator();
        $this->organization = new Organization();

        $rule = new RegulatoryRule('test-rule', 'Test', 'Test', RuleCategory::MENTION_OBLIGATOIRE, 'FR', RuleStatus::ACTIVE);
        $this->ruleVersion = new RuleVersion($rule, 1, new \DateTimeImmutable('2026-01-01'), [], 'NON_CONFORME', 'test', ConfidenceLevel::ELEVE, 'template');
    }

    private function invoice(): Invoice
    {
        $customer = new Customer($this->organization, CustomerType::PROFESSIONNEL_FRANCAIS, 'Client Test', '123456789', null, 'FR');

        return new Invoice($this->organization, $customer, null, new \DateTimeImmutable('2026-08-19'), OperationType::PRESTATION_SERVICE, 'EUR', null, InvoiceSource::SAISIE_MANUELLE);
    }

    private function completedAnalysis(): ComplianceAnalysis
    {
        $analysis = new ComplianceAnalysis($this->organization, $this->invoice());
        $analysis->markCompleted(ComplianceResult::CONFORME, new \DateTimeImmutable());

        return $analysis;
    }

    private function finding(ComplianceAnalysis $analysis, ComplianceResult $result, ?string $correctionAction = 'Corriger le champ concerné.'): ComplianceFinding
    {
        return new ComplianceFinding($analysis, $this->ruleVersion, $result, 'message', 'invoice.field', null, $correctionAction);
    }

    public function testNoAnalysesYieldsAucuneAnalyse(): void
    {
        $snapshot = $this->aggregator->aggregate([], []);

        self::assertSame(DashboardGlobalStatus::AUCUNE_ANALYSE, $snapshot->globalStatus);
        self::assertSame(0, $snapshot->openIssuesCount);
        self::assertSame(0, $snapshot->warningsCount);
        self::assertSame([], $snapshot->recentAnalyses);
        self::assertSame([], $snapshot->recommendedActions);
    }

    public function testOnlyConformeYieldsConforme(): void
    {
        $analysis = $this->completedAnalysis();
        $findings = [$this->finding($analysis, ComplianceResult::CONFORME, null)];

        $snapshot = $this->aggregator->aggregate([$analysis], $findings);

        self::assertSame(DashboardGlobalStatus::CONFORME, $snapshot->globalStatus);
        self::assertSame(0, $snapshot->openIssuesCount);
        self::assertSame(0, $snapshot->warningsCount);
        self::assertSame([], $snapshot->recommendedActions);
    }

    public function testOnlyAvertissementYieldsAvertissementWithoutOpenIssues(): void
    {
        $analysis = $this->completedAnalysis();
        $findings = [$this->finding($analysis, ComplianceResult::AVERTISSEMENT)];

        $snapshot = $this->aggregator->aggregate([$analysis], $findings);

        self::assertSame(DashboardGlobalStatus::AVERTISSEMENT, $snapshot->globalStatus);
        self::assertSame(0, $snapshot->openIssuesCount);
        self::assertSame(1, $snapshot->warningsCount);
        // AVERTISSEMENT n'est jamais une action recommandée (bucket "problème" uniquement).
        self::assertSame([], $snapshot->recommendedActions);
    }

    public function testMixOfResultsYieldsAttentionRequiseWithExactBucketing(): void
    {
        $analysis = $this->completedAnalysis();
        $findings = [
            $this->finding($analysis, ComplianceResult::NON_CONFORME),
            $this->finding($analysis, ComplianceResult::A_VERIFIER),
            $this->finding($analysis, ComplianceResult::INCERTAIN_REGLEMENTAIRE),
            $this->finding($analysis, ComplianceResult::AVERTISSEMENT),
            $this->finding($analysis, ComplianceResult::CONFORME, null),
            $this->finding($analysis, ComplianceResult::NON_APPLICABLE, null),
        ];

        $snapshot = $this->aggregator->aggregate([$analysis], $findings);

        self::assertSame(DashboardGlobalStatus::ATTENTION_REQUISE, $snapshot->globalStatus);
        self::assertSame(3, $snapshot->openIssuesCount, 'NON_CONFORME + A_VERIFIER + INCERTAIN_REGLEMENTAIRE.');
        self::assertSame(1, $snapshot->warningsCount, 'AVERTISSEMENT seul.');
    }

    public function testRecommendedActionsIgnoreEmptyOrNullCorrectionAction(): void
    {
        $analysis = $this->completedAnalysis();
        $findings = [
            $this->finding($analysis, ComplianceResult::NON_CONFORME, null),
            $this->finding($analysis, ComplianceResult::NON_CONFORME, '   '),
            $this->finding($analysis, ComplianceResult::NON_CONFORME, 'Renseigner le SIREN du client.'),
        ];

        $snapshot = $this->aggregator->aggregate([$analysis], $findings);

        self::assertSame(3, $snapshot->openIssuesCount);
        self::assertCount(1, $snapshot->recommendedActions, 'Un finding sans action concrète n\'est jamais transformé en action recommandée.');
        self::assertSame('Renseigner le SIREN du client.', $snapshot->recommendedActions[0]->message);
    }

    public function testRecommendedActionsAreDeduplicatedByMessage(): void
    {
        $analysisA = $this->completedAnalysis();
        $analysisB = $this->completedAnalysis();

        $findings = [
            $this->finding($analysisA, ComplianceResult::NON_CONFORME, 'Renseigner le SIREN du client.'),
            $this->finding($analysisB, ComplianceResult::A_VERIFIER, 'Renseigner le SIREN du client.'),
        ];

        $snapshot = $this->aggregator->aggregate([$analysisA, $analysisB], $findings);

        self::assertCount(1, $snapshot->recommendedActions, 'Même libellé, jamais dupliqué même sur deux factures distinctes.');
    }

    public function testRecentAnalysesAndRecommendedActionsAreCappedAtFive(): void
    {
        $analyses = [];
        $findings = [];
        for ($i = 0; $i < 7; ++$i) {
            $analysis = $this->completedAnalysis();
            $analyses[] = $analysis;
            $findings[] = $this->finding($analysis, ComplianceResult::NON_CONFORME, sprintf('Action %d.', $i));
        }

        $snapshot = $this->aggregator->aggregate($analyses, $findings);

        self::assertCount(5, $snapshot->recentAnalyses);
        self::assertCount(5, $snapshot->recommendedActions);
        self::assertSame(7, $snapshot->openIssuesCount, 'Le comptage porte sur tous les findings, jamais uniquement sur les 5 retenus pour affichage.');
    }

    public function testRecentAnalysesAreOrderedMostRecentFirst(): void
    {
        $older = $this->completedAnalysis();
        usleep(1000);
        $newer = $this->completedAnalysis();

        $snapshot = $this->aggregator->aggregate([$older, $newer], []);

        self::assertSame($newer->getId(), $snapshot->recentAnalyses[0]->getId());
        self::assertSame($older->getId(), $snapshot->recentAnalyses[1]->getId());
    }
}
