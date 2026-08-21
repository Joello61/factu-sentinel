<?php

declare(strict_types=1);

namespace App\Notification\Enum;

/**
 * docs/07-data-model.md, section 21. Ensemble complet déclaré dès la Phase 14 - SEGMENT/ALL
 * ne sont utilisables qu'à partir de la Phase 15. `08-api-specification.md` section 34
 * (POST /organizations/current/notifications) prend un `recipient_ids` explicite - un
 * ciblage par destinataires nommément choisis par l'expéditeur, jamais un ciblage implicite
 * "tous les membres" - donc App\Notification\Service\SendTeamNotificationService de cette
 * phase ne produit jamais que des lignes `USER` (une par destinataire choisi), jamais
 * ORGANIZATION_MEMBERS : cette dernière valeur reste déclarée pour la complétude du schéma
 * (Phase 15) mais dormante en Phase 14, comme NotificationType::ECHEANCE_OBLIGATION et
 * SenderType::PLATFORM_ADMIN.
 */
enum TargetType: string
{
    case USER = 'USER';
    case ORGANIZATION_MEMBERS = 'ORGANIZATION_MEMBERS';
    case SEGMENT = 'SEGMENT';
    case ALL = 'ALL';
}
