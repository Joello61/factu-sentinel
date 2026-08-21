<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Identity\Entity\Membership;
use App\Identity\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorisation par rôle d'organisation (docs/04-product-requirements.md, section 21.1 ;
 * plan Phase 14 - décision "Matrice complète des permissions d'équipe"). Voter **unique**,
 * pas un par permission - centralisation exigée par docs/06-technical-architecture.md
 * section 19 et docs/10-security-privacy.md section 15.
 *
 * Strictement additif à l'isolation tenant (TenantFilter, ADR-004) - ne la remplace ni ne
 * la contourne jamais : ce Voter répond uniquement à "ce rôle autorise-t-il cette action au
 * sein de l'organisation déjà résolue comme courante", jamais "cette ressource
 * appartient-elle à cette organisation" (App\Shared\Doctrine\TenantFilter s'en charge déjà,
 * en amont).
 *
 * Matrice codée en dur, jamais une table Permission dynamique
 * (docs/07-data-model.md, section 5) :
 *
 * | Permission              | OWNER | ADMIN              | COLLABORATOR |
 * | ------------------------ | :---: | :-----------------: | :----------: |
 * | team:read                | Oui   | Oui                 | Oui          |
 * | team:invite               | Oui   | Oui                 | Non          |
 * | team:manage_roles         | Oui   | Non                 | Non          |
 * | team:remove               | Oui   | Oui (jamais OWNER)  | Non          |
 * | notification:send_team    | Oui   | Oui                 | Non          |
 * | organization:update       | Oui   | Oui                 | Non          |
 *
 * @extends Voter<string, Membership|null>
 */
final class OrganizationPermissionVoter extends Voter
{
    public const string TEAM_READ = 'team:read';
    public const string TEAM_INVITE = 'team:invite';
    public const string TEAM_MANAGE_ROLES = 'team:manage_roles';
    public const string TEAM_REMOVE = 'team:remove';
    public const string NOTIFICATION_SEND_TEAM = 'notification:send_team';
    public const string ORGANIZATION_UPDATE = 'organization:update';

    private const array ATTRIBUTES = [
        self::TEAM_READ,
        self::TEAM_INVITE,
        self::TEAM_MANAGE_ROLES,
        self::TEAM_REMOVE,
        self::NOTIFICATION_SEND_TEAM,
        self::ORGANIZATION_UPDATE,
    ];

    public function __construct(
        private readonly CurrentMembershipResolver $currentMembershipResolver,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true);
    }

    /**
     * $subject, quand fourni, est le Membership **cible** de l'action (ex. le membre dont
     * on modifie/retire le rôle) - jamais requis pour team:read/team:invite/
     * notification:send_team/organization:update, qui ne portent pas sur un Membership
     * précis.
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $role = $this->currentMembershipResolver->getMembership()->getRole();

        return match ($attribute) {
            self::TEAM_READ => true, // OWNER, ADMIN, COLLABORATOR - tous.
            self::TEAM_INVITE, self::NOTIFICATION_SEND_TEAM, self::ORGANIZATION_UPDATE => in_array($role, [Role::OWNER, Role::ADMIN], true),
            self::TEAM_MANAGE_ROLES => Role::OWNER === $role,
            self::TEAM_REMOVE => $this->voteTeamRemove($role, $subject),
            default => false,
        };
    }

    private function voteTeamRemove(Role $callerRole, mixed $targetMembership): bool
    {
        if (!in_array($callerRole, [Role::OWNER, Role::ADMIN], true)) {
            return false;
        }

        if ($targetMembership instanceof Membership && Role::OWNER === $targetMembership->getRole()) {
            // Un ADMIN ne retire jamais l'OWNER ; l'OWNER ne se retire jamais lui-même par
            // ce chemin (docs/04-product-requirements.md section 21.1) - vérifié ici plutôt
            // que délégué au seul contrôleur, pour qu'aucun endpoint ne puisse l'oublier.
            return false;
        }

        return true;
    }
}
