<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Repository\ComplianceAnalysisRepository;

/**
 * **Limitation connue, documentée plutôt que masquée** (App\Compliance\Engine\Enum\
 * ComplianceAnalysisStatus, bilan Phase 5) : le traitement du Compliance Engine reste
 * synchrone à ce stade du produit - une erreur technique fait échouer toute la transaction
 * avant qu'aucune ComplianceAnalysis ne soit jamais persistée avec le statut FAILED. Ce taux
 * d'échec sera donc systématiquement "0" tant qu'un traitement asynchrone (Phase 7 du moteur
 * lui-même, distinct du traitement documentaire déjà asynchrone) n'introduit pas de scénario
 * où un échec technique peut survenir après un commit intermédiaire. Exposé quand même
 * (US-PLATFORMADMIN-005 documente explicitement cet indicateur) - jamais retiré par
 * anticipation, jamais présenté comme un indicateur actuellement significatif.
 */
final readonly class ComplianceHealthReader implements ComplianceHealthReaderInterface
{
    public function __construct(
        private ComplianceAnalysisRepository $complianceAnalysisRepository,
    ) {
    }

    public function getFailureRateLast24Hours(): string
    {
        $counts = $this->complianceAnalysisRepository->countByStatusSince(
            new \DateTimeImmutable('-24 hours'),
        );

        if (0 === $counts['total']) {
            return '0';
        }

        return (string) round($counts['failed'] / $counts['total'], 4);
    }
}
