<?php

declare(strict_types=1);

namespace App\Identity\Enum;

/**
 * docs/07-data-model.md, section 5 (Phase 14). Une Invitation n'accorde jamais d'accès par
 * elle-même - seul un Membership réellement créé (App\Identity\Controller\
 * AcceptInvitationController) le fait.
 */
enum InvitationStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
}
