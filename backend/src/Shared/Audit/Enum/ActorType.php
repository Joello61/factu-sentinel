<?php

declare(strict_types=1);

namespace App\Shared\Audit\Enum;

/**
 * docs/07-data-model.md, section 20. `PLATFORM_ADMIN` (Phase 15, ADR-009) - jamais confondu
 * avec `USER` : un App\PlatformAdmin\Entity\PlatformAdministrator est une identité
 * structurellement séparée d'un App\Identity\Entity\User, y compris dans l'audit trail.
 */
enum ActorType: string
{
    case USER = 'USER';
    case SYSTEM = 'SYSTEM';
    case PLATFORM_ADMIN = 'PLATFORM_ADMIN';
}
