<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\PlatformAdmin\Entity\PlatformAdministrator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorisation du rôle plateforme (docs/08-api-specification.md, sections 38.2 et 38.3 ;
 * docs/10-security-privacy.md, section 17 bis : "liste explicite des actions permises, jamais
 * un accès complet implicite du seul fait d'être PlatformAdministrator"). Même patron que
 * App\Shared\Security\OrganizationPermissionVoter - Voter unique, matrice codée en dur
 * (`08-api-specification.md` section 38.2, pas de RBAC interne à ce rôle au MVP de cette
 * phase).
 *
 * Contrairement à OrganizationPermissionVoter, tous les attributs sont accordés de façon
 * identique dès qu'un PlatformAdministrator authentifié est résolu - il n'existe qu'un seul
 * rôle plateforme au MVP de cette phase (section 17 bis). La distinction en attributs
 * multiples (plutôt qu'un unique "IS_PLATFORM_ADMIN") est délibérée : elle rend explicite,
 * dans chaque contrôleur, quelle permission précise il exerce (traçabilité, et prépare une
 * éventuelle segmentation future sans réécrire les contrôleurs).
 *
 * @extends Voter<string, null>
 */
final class PlatformAdminPermissionVoter extends Voter
{
    public const string ORGANIZATIONS_READ = 'platform:organizations:read';
    public const string ORGANIZATIONS_SUSPEND = 'platform:organizations:suspend';
    public const string AUDIT_READ = 'platform:audit:read';
    public const string NOTIFICATIONS_SEND = 'platform:notifications:send';
    public const string HEALTH_READ = 'platform:health:read';
    public const string ANALYTICS_READ = 'platform:analytics:read';

    private const array ATTRIBUTES = [
        self::ORGANIZATIONS_READ,
        self::ORGANIZATIONS_SUSPEND,
        self::AUDIT_READ,
        self::NOTIFICATIONS_SEND,
        self::HEALTH_READ,
        self::ANALYTICS_READ,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof PlatformAdministrator;
    }
}
