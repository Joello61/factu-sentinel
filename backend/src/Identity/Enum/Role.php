<?php

declare(strict_types=1);

namespace App\Identity\Enum;

/**
 * Rôle porté par un Membership. Un seul rôle existe au MVP (docs/04-product-requirements.md,
 * section 21) : pas de RBAC complexe sans besoin justifié (docs/07-data-model.md, section 5).
 * L'entité Membership existe malgré tout dès maintenant pour accueillir de futurs rôles
 * (persona secondaire C) sans refonte.
 */
enum Role: string
{
    case OWNER = 'OWNER';
}
