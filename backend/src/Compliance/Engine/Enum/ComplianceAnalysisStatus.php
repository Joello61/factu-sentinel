<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Enum;

/**
 * Cycle de vie technique de l'analyse (backend/CLAUDE.md, section 7), distinct des six
 * ComplianceResult : FAILED est un échec technique du moteur, jamais un
 * global_result NON_CONFORME, qui est une issue normale de COMPLETED. En Phase 5
 * (traitement toujours synchrone, App\Compliance\Engine\Service\RunComplianceAnalysisService),
 * une erreur technique fait échouer toute la transaction (rollback complet, y compris la
 * réservation d'idempotence) : aucune ComplianceAnalysis n'est donc jamais persistée avec
 * FAILED aujourd'hui. Ce statut est déclaré pour la fidélité au modèle documenté et pour un
 * futur traitement asynchrone (Phase 7) où un échec pourrait survenir après un commit
 * intermédiaire.
 */
enum ComplianceAnalysisStatus: string
{
    case PENDING = 'PENDING';
    case RUNNING = 'RUNNING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
