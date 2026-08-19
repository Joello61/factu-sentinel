<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Enum\Role;
use App\Organization\Entity\Organization;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Relation entre un User et une Organization, porteuse du Role (docs/07-data-model.md,
 * section 5). Un User peut avoir plusieurs Membership : cas plausible (une même personne
 * gérant plusieurs entreprises), non encore utilisé par le produit au MVP mais qui évite
 * une refonte du modèle si ce besoin est confirmé plus tard.
 */
#[ORM\Entity]
#[ORM\Table(name: 'memberships')]
#[ORM\UniqueConstraint(name: 'uniq_membership_user_organization', columns: ['user_id', 'organization_id'])]
class Membership
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

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
