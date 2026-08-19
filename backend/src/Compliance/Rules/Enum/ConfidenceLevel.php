<?php

declare(strict_types=1);

namespace App\Compliance\Rules\Enum;

/**
 * docs/02-regulatory-study.md, section 26 ; nécessaire pour produire l'état
 * INCERTAIN_REGLEMENTAIRE en Phase 5 (BR-COMPLIANCE-004 du PRD) : non exploité par le
 * calculateur de la Phase 3, qui n'a que des règles de confiance ELEVE (voir migration de
 * seed), mais fait partie du schéma global RuleVersion dès maintenant.
 */
enum ConfidenceLevel: string
{
    case ELEVE = 'ELEVE';
    case MOYEN = 'MOYEN';
    case FAIBLE = 'FAIBLE';
}
