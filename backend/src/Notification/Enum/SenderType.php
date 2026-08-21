<?php

declare(strict_types=1);

namespace App\Notification\Enum;

/**
 * docs/07-data-model.md, section 21. Ensemble complet déclaré dès la Phase 14 pour éviter
 * une migration cassante en Phase 15 (PLATFORM_ADMIN) - seuls SYSTEM et ORGANIZATION_OWNER
 * sont produits par un chemin applicatif de cette phase
 * (App\Notification\Service\SendTeamNotificationService n'utilise jamais PLATFORM_ADMIN).
 */
enum SenderType: string
{
    case SYSTEM = 'SYSTEM';
    case ORGANIZATION_OWNER = 'ORGANIZATION_OWNER';
    case PLATFORM_ADMIN = 'PLATFORM_ADMIN';
}
