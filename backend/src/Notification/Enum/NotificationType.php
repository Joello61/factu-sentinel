<?php

declare(strict_types=1);

namespace App\Notification\Enum;

/**
 * docs/07-data-model.md, section 21. `ECHEANCE_OBLIGATION` (rappel système) reste P2/non
 * engagé (05-user-stories.md, US-NOTIFICATION-001) - déclaré ici pour fidélité au schéma
 * complet, jamais produit par aucun chemin applicatif de la Phase 14.
 */
enum NotificationType: string
{
    case ECHEANCE_OBLIGATION = 'echeance_obligation';
    case MESSAGE_ORGANISATION = 'message_organisation';
    case MESSAGE_PLATEFORME = 'message_plateforme';
}
