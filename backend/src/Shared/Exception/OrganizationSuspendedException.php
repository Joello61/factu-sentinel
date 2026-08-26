<?php

declare(strict_types=1);

namespace App\Shared\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * L'Organization résolue par le claim `org` d'un JWT par ailleurs valide a été suspendue par
 * un App\PlatformAdmin\Entity\PlatformAdministrator (US-PLATFORMADMIN-002, Phase 15,
 * App\Organization\Entity\Organization::$suspendedAt). Tous les membres perdent l'accès
 * applicatif immédiatement, dès la requête authentifiée suivante - même famille d'exception
 * que App\Shared\Exception\OrganizationMembershipMismatchException (401, jamais 403, qui
 * confirmerait l'existence et le statut de l'organisation à un appelant qui ne devrait rien en
 * savoir).
 */
final class OrganizationSuspendedException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'This organization has been suspended.';
    }
}
