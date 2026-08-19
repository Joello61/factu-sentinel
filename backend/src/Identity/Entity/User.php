<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Compte individuel. Un User n'a pas de organization_id direct : il est rattaché à une ou
 * plusieurs organisations via Membership (docs/07-data-model.md, section 4-5).
 *
 * Volontairement sans champ d'authentification (mot de passe, etc.) à ce stade : ce sujet
 * relève de la Phase 2 (Identity & Multi-Tenancy) et de docs/10-security-privacy.md, qui
 * ne fige pas encore l'algorithme de hachage retenu — l'ajouter ici serait deviner une
 * décision de sécurité non prise (docs/CLAUDE.md, section 3).
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $email;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Membership> */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'user')]
    private Collection $memberships;

    public function __construct(string $email)
    {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Membership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }
}
