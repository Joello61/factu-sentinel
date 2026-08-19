<?php

declare(strict_types=1);

namespace App\Shared\Audit\Enum;

/**
 * docs/07-data-model.md, section 20 cite plusieurs event_type possibles (login,
 * organization_updated, invoice_created, ...) mais seuls les deux événements réellement
 * produits par cette phase sont listés ici (docs/06-technical-architecture.md, section 22,
 * et backend/CLAUDE.md section 15 : implémenter le minimum nécessaire) — les événements de
 * Phase 2 (login, réinitialisation de mot de passe) ne sont volontairement pas
 * instrumentés rétroactivement dans cette tâche, ce gap préexistant relevant du
 * durcissement sécurité de la Phase 10 (voir plan Phase 3).
 */
enum EventType: string
{
    case ORGANIZATION_UPDATED = 'ORGANIZATION_UPDATED';
    case ELIGIBILITY_DIAGNOSTIC_COMPUTED = 'ELIGIBILITY_DIAGNOSTIC_COMPUTED';
}
