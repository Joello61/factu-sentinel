<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Enum\Role;
use App\Identity\Repository\MembershipRepository;
use App\Organization\Entity\Organization;
use App\Shared\Doctrine\TenantScopedInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Relation entre un User et une Organization, porteuse du Role (docs/07-data-model.md,
 * section 5). Un User peut avoir plusieurs Membership - un par Organization à laquelle il
 * appartient (une seule au MVP, décision Phase 2 ; plusieurs possibles depuis la Phase 14,
 * DEC-009, via l'acceptation d'une Invitation - App\Identity\Controller\
 * AcceptInvitationController).
 *
 * Première entité réellement tenant-scoped du projet : implémente TenantScopedInterface,
 * contrairement à Organization qui est le tenant racine lui-même.
 */
#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[ORM\Table(name: 'memberships')]
#[ORM\UniqueConstraint(name: 'uniq_membership_user_organization', columns: ['user_id', 'organization_id'])]
class Membership implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private Organization $organization;

    #[ORM\Column(type: Types::STRING, enumType: Role::class)]
    private Role $role;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, Organization $organization, Role $role)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->organization = $organization;
        $this->role = $role;
        $this->createdAt = new \DateTimeImmutable();

        // Synchronise le côté inverse de la relation (docs/07-data-model.md, section 5) :
        // sans cet appel, un User déjà géré par l'identity map Doctrine dans la même unité
        // de travail (ex. juste après sa propre création, avant tout rechargement depuis la
        // base) garderait une collection $memberships vide, Doctrine ne la remplaçant
        // jamais rétroactivement pour un objet qu'il n'a pas lui-même hydraté par requête.
        $user->addMembership($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organization->getId();
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    /**
     * US-TEAM-002 (App\Identity\Controller\UpdateMemberRoleController). Ne vérifie jamais
     * elle-même l'invariant "jamais OWNER" - ce contrôle métier appartient à l'appelant
     * (le rôle assignable dépend du contexte : jamais OWNER via ce chemin, matrice PRD
     * section 21.1), pas à l'entité.
     */
    public function setRole(Role $role): void
    {
        $this->role = $role;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
