<?php

declare(strict_types=1);

namespace App\Identity\Enum;

/**
 * Rôle porté par un Membership. Révisé en Phase 14 (DEC-009, docs/04-product-requirements.md
 * section 21.1) : trois rôles, matrice de permissions codée en dur dans
 * App\Shared\Security\OrganizationPermissionVoter - pas de RBAC complexe avec table
 * Permission dynamique (docs/07-data-model.md, section 5).
 */
enum Role: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case COLLABORATOR = 'COLLABORATOR';

    /**
     * Rôles assignables par une Invitation ou par PATCH .../members/{id} (jamais OWNER -
     * un seul par organisation, transfert de propriété hors périmètre de la Phase 14,
     * docs/04-product-requirements.md section 21.1). Source unique de cette restriction,
     * réutilisée par App\Identity\Http\InviteMemberRequest et
     * App\Identity\Http\UpdateMemberRoleRequest plutôt que dupliquée.
     *
     * @return list<string>
     */
    public static function assignableValues(): array
    {
        return [self::ADMIN->value, self::COLLABORATOR->value];
    }
}
