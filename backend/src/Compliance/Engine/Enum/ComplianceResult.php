<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Enum;

/**
 * Les six états de résultat d'un ComplianceFinding (docs/04-product-requirements.md,
 * section 10 ; docs/05-user-stories.md, section 8), jamais un binaire conforme/non
 * conforme (principe "Pourquoi, jamais seulement si", ../../CLAUDE.md section 9).
 */
enum ComplianceResult: string
{
    case CONFORME = 'CONFORME';
    case NON_CONFORME = 'NON_CONFORME';
    case AVERTISSEMENT = 'AVERTISSEMENT';
    case NON_APPLICABLE = 'NON_APPLICABLE';
    case A_VERIFIER = 'A_VERIFIER';
    case INCERTAIN_REGLEMENTAIRE = 'INCERTAIN_REGLEMENTAIRE';
}
