<?php

declare(strict_types=1);

namespace App\Compliance\Rules\Enum;

/**
 * docs/07-data-model.md, section 15 cite plusieurs catégories possibles (mention_obligatoire,
 * eligibilite, qualification_operation, format, ...). ELIGIBILITE (Phase 3) et, depuis la
 * Phase 5, MENTION_OBLIGATOIRE (mention-siren-client, mention-categorie-operation) et FORMAT
 * (format-document-structure) ont une règle réelle. QUALIFICATION_OPERATION n'est
 * volontairement pas ajoutée : l'applicabilité e-invoicing/e-reporting est portée par
 * conditions.applicability de chaque RuleVersion concernée (Compliance/Engine), pas par une
 * règle distincte qui produirait son propre ComplianceFinding (voir plan Phase 5) — une
 * catégorie n'est ajoutée qu'avec une règle réelle qui l'utilise.
 */
enum RuleCategory: string
{
    case ELIGIBILITE = 'ELIGIBILITE';
    case MENTION_OBLIGATOIRE = 'MENTION_OBLIGATOIRE';
    case FORMAT = 'FORMAT';
}
