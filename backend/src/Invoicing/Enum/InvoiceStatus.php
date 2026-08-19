<?php

declare(strict_types=1);

namespace App\Invoicing\Enum;

/**
 * Cycle de vie de l'analyse, jamais d'émission (docs/08-api-specification.md, section 28 ;
 * docs/07-data-model.md, section 29). ANALYZED/ANALYSIS_STALE ne sont atteignables qu'à
 * partir de la Phase 5 (Compliance Engine, inexistant à ce stade) : déclarées ici pour que
 * l'enum reflète fidèlement le modèle documenté, mais aucun code de la Phase 4 ne les
 * produit (plan Phase 4).
 */
enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case READY_FOR_ANALYSIS = 'READY_FOR_ANALYSIS';
    case ANALYZED = 'ANALYZED';
    case ANALYSIS_STALE = 'ANALYSIS_STALE';
}
