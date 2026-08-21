<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Enum\InvitationStatus;
use App\Identity\Enum\Role;
use App\Identity\Repository\InvitationRepository;
use App\Organization\Entity\Organization;
use App\Shared\Doctrine\TenantScopedInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Invitation en attente à rejoindre une Organization avec un Role donné
 * (docs/07-data-model.md, section 5 ; US-TEAM-001, Phase 14). Ne devient un Membership
 * qu'une fois explicitement acceptée (App\Identity\Controller\AcceptInvitationController) -
 * jamais fusionnée avec Membership lui-même, une invitation en attente n'accorde aucun
 * accès.
 *
 * `role` est restreint à ADMIN/COLLABORATOR au niveau applicatif (jamais OWNER, cohérent
 * avec US-TEAM-001 - "un ADMIN ne peut jamais inviter quelqu'un avec le rôle OWNER", et
 * plus largement il n'existe qu'un seul OWNER par organisation à la création).
 *
 * Jeton d'acceptation : sélecteur (recherche, indexé) + hash du vérificateur - même principe
 * de sécurité que `symfonycasts/reset-password-bundle` (ResetPasswordRequest), mais implémenté
 * ici manuellement car ce bundle est structurellement lié à un User déjà existant
 * (`ResetPasswordRequestInterface`), alors qu'une Invitation cible un email qui peut ne
 * correspondre à aucun compte au moment de l'émission. Le jeton en clair (sélecteur +
 * vérificateur concaténés) n'est **jamais** persisté - seul son hash SHA-256 l'est,
 * comparé en temps constant (hash_equals) à la vérification.
 */
#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\Table(name: 'invitations')]
class Invitation implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private Organization $organization;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $email;

    #[ORM\Column(type: Types::STRING, enumType: Role::class)]
    private Role $role;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'invited_by', nullable: false)]
    private User $invitedBy;

    #[ORM\Column(type: Types::STRING, enumType: InvitationStatus::class)]
    private InvitationStatus $status;

    #[ORM\Column(name: 'token_selector', type: Types::STRING, length: 32, unique: true)]
    private string $tokenSelector;

    #[ORM\Column(name: 'token_hash', type: Types::STRING, length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    public function __construct(
        Organization $organization,
        string $email,
        Role $role,
        User $invitedBy,
        string $tokenSelector,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
    ) {
        if (Role::OWNER === $role) {
            throw new \InvalidArgumentException('An Invitation can never carry the OWNER role.');
        }

        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->email = $email;
        $this->role = $role;
        $this->invitedBy = $invitedBy;
        $this->status = InvitationStatus::PENDING;
        $this->tokenSelector = $tokenSelector;
        $this->tokenHash = $tokenHash;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $expiresAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organization->getId();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getInvitedBy(): User
    {
        return $this->invitedBy;
    }

    public function getStatus(): InvitationStatus
    {
        return $this->status;
    }

    public function getTokenSelector(): string
    {
        return $this->tokenSelector;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isPending(): bool
    {
        return InvitationStatus::PENDING === $this->status && $this->expiresAt > new \DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        return InvitationStatus::PENDING === $this->status && $this->expiresAt <= new \DateTimeImmutable();
    }

    /** Idempotent au niveau applicatif : les appelants doivent vérifier isPending() avant, jamais s'y fier implicitement. */
    public function markAccepted(): void
    {
        $this->status = InvitationStatus::ACCEPTED;
    }

    public function markRevoked(): void
    {
        $this->status = InvitationStatus::REVOKED;
    }
}
