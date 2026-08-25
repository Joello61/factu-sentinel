<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Enum;

/**
 * Valeurs du contrat API `target_type` (docs/08-api-specification.md, section 38.2) - jamais
 * confondu avec App\Notification\Enum\TargetType (enum persistée sur Notification, nommage
 * déjà fixé en Phase 14) : `ORGANIZATION` ici correspond à `ORGANIZATION_MEMBERS` côté
 * persistance, mapping explicite fait par App\PlatformAdmin\Service\
 * SendPlatformNotificationService.
 */
enum PlatformNotificationTargetType: string
{
    case USER = 'USER';
    case ORGANIZATION = 'ORGANIZATION';
    case SEGMENT = 'SEGMENT';
    case ALL = 'ALL';
}
