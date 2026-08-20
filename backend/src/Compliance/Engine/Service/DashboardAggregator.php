<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Entity\ComplianceFinding;
use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Engine\Enum\DashboardGlobalStatus;

/**
 * Agrégation du Dashboard (docs/08-api-specification.md, section 33, Phase 9 - décision
 * produit validée) à partir de la dernière ComplianceAnalysis COMPLETED de chaque Invoice
 * (App\Compliance\Engine\Repository\ComplianceAnalysisRepository::findLatestCompletedPerInvoice())
 * et de leurs ComplianceFinding. Vue de lecture pure : ne recalcule jamais
 * ComplianceAnalysis::globalResult (déjà figé par App\Compliance\Engine\Service\
 * ComplianceResultAggregator au moment de l'analyse), ne modifie jamais aucune entité,
 * n'introduit aucune nouvelle règle de recommandation métier au-delà du bucketing déjà
 * décidé.
 *
 * Bucketing (décision produit Phase 9) : NON_CONFORME/A_VERIFIER/INCERTAIN_REGLEMENTAIRE
 * comptent comme "problème" (openIssuesCount) -- les trois états qui appellent une action ou
 * une clarification de l'utilisateur ; AVERTISSEMENT compte seul comme "avertissement"
 * (warningsCount) ; CONFORME/NON_APPLICABLE ne comptent dans aucun des deux buckets.
 */
final class DashboardAggregator
{
    private const int MAX_RECENT_ANALYSES = 5;
    private const int MAX_RECOMMENDED_ACTIONS = 5;

    /** @var list<ComplianceResult> */
    private const array PROBLEM_RESULTS = [
        ComplianceResult::NON_CONFORME,
        ComplianceResult::A_VERIFIER,
        ComplianceResult::INCERTAIN_REGLEMENTAIRE,
    ];

    /** @var list<ComplianceResult> */
    private const array WARNING_RESULTS = [
        ComplianceResult::AVERTISSEMENT,
    ];

    /**
     * @param list<ComplianceAnalysis> $latestAnalyses la dernière ComplianceAnalysis COMPLETED de chaque Invoice
     * @param list<ComplianceFinding>  $findings        l'ensemble des findings de ces analyses (App\Compliance\Engine\Repository\ComplianceFindingRepository::findByAnalyses())
     */
    public function aggregate(array $latestAnalyses, array $findings): DashboardSnapshot
    {
        if ([] === $latestAnalyses) {
            return new DashboardSnapshot(DashboardGlobalStatus::AUCUNE_ANALYSE, 0, 0, [], []);
        }

        $openIssuesCount = 0;
        $warningsCount = 0;
        foreach ($findings as $finding) {
            if (\in_array($finding->getResult(), self::PROBLEM_RESULTS, true)) {
                ++$openIssuesCount;
            } elseif (\in_array($finding->getResult(), self::WARNING_RESULTS, true)) {
                ++$warningsCount;
            }
        }

        $globalStatus = match (true) {
            $openIssuesCount > 0 => DashboardGlobalStatus::ATTENTION_REQUISE,
            $warningsCount > 0 => DashboardGlobalStatus::AVERTISSEMENT,
            default => DashboardGlobalStatus::CONFORME,
        };

        $sortedAnalyses = $latestAnalyses;
        usort($sortedAnalyses, self::compareAnalysesMostRecentFirst(...));

        $recentAnalyses = \array_slice($sortedAnalyses, 0, self::MAX_RECENT_ANALYSES);

        return new DashboardSnapshot(
            $globalStatus,
            $openIssuesCount,
            $warningsCount,
            $recentAnalyses,
            $this->buildRecommendedActions($sortedAnalyses, $findings),
        );
    }

    /**
     * @param list<ComplianceAnalysis> $sortedAnalyses analyses déjà triées, plus récentes d'abord
     * @param list<ComplianceFinding>  $findings
     *
     * @return list<DashboardRecommendedAction>
     */
    private function buildRecommendedActions(array $sortedAnalyses, array $findings): array
    {
        // Ordre déterministe des analyses (déjà trié par l'appelant), puis des findings au
        // sein d'une même analyse par id (Uuid::v7(), ordonné dans le temps) : jamais l'ordre
        // de retour non garanti d'une requête SQL sans ORDER BY explicite sur ce critère.
        $findingsByAnalysisId = [];
        foreach ($findings as $finding) {
            $findingsByAnalysisId[$finding->getComplianceAnalysis()->getId()->toRfc4122()][] = $finding;
        }

        $seenMessages = [];
        $actions = [];
        foreach ($sortedAnalyses as $analysis) {
            $analysisFindings = $findingsByAnalysisId[$analysis->getId()->toRfc4122()] ?? [];
            usort(
                $analysisFindings,
                static fn (ComplianceFinding $a, ComplianceFinding $b): int => $a->getId()->toRfc4122() <=> $b->getId()->toRfc4122(),
            );

            foreach ($analysisFindings as $finding) {
                if (\count($actions) >= self::MAX_RECOMMENDED_ACTIONS) {
                    return $actions;
                }

                if (!\in_array($finding->getResult(), self::PROBLEM_RESULTS, true)) {
                    continue;
                }

                $correctionAction = $finding->getCorrectionAction();
                if (null === $correctionAction || '' === trim($correctionAction)) {
                    continue;
                }

                if (isset($seenMessages[$correctionAction])) {
                    continue;
                }
                $seenMessages[$correctionAction] = true;

                $actions[] = new DashboardRecommendedAction($correctionAction, $analysis);
            }
        }

        return $actions;
    }

    private static function compareAnalysesMostRecentFirst(ComplianceAnalysis $a, ComplianceAnalysis $b): int
    {
        return $b->getTriggeredAt() <=> $a->getTriggeredAt() ?: $b->getId()->toRfc4122() <=> $a->getId()->toRfc4122();
    }
}
