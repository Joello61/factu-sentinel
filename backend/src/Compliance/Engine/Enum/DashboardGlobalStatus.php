<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Enum;

/**
 * État agrégé du portefeuille de factures d'une organisation (docs/08-api-specification.md,
 * section 33, Phase 9 - GET /dashboard), jamais une réutilisation de ComplianceResult :
 * ComplianceResult répond du résultat d'un finding/d'une règle précise, DashboardGlobalStatus
 * répond d'un agrégat sur plusieurs factures. Décision produit Phase 9 (validée
 * explicitement, docs/12-roadmap.md).
 *
 * Précédence ATTENTION_REQUISE > AVERTISSEMENT > CONFORME (App\Compliance\Engine\Service\
 * DashboardAggregator::PRECEDENCE), même principe que App\Compliance\Engine\Service\
 * ComplianceResultAggregator pour ComplianceResult. AUCUNE_ANALYSE est un état particulier,
 * jamais confondu avec CONFORME : distingue explicitement « aucune facture analysée » de
 * « toutes les factures analysées sont conformes » (US-DASHBOARD-001).
 */
enum DashboardGlobalStatus: string
{
    case AUCUNE_ANALYSE = 'AUCUNE_ANALYSE';
    case CONFORME = 'CONFORME';
    case AVERTISSEMENT = 'AVERTISSEMENT';
    case ATTENTION_REQUISE = 'ATTENTION_REQUISE';
}
