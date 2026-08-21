<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Compte individuel. Un User n'a pas de organization_id direct : il est rattaché à une ou
 * plusieurs organisations via Membership (docs/07-data-model.md, section 4-5) - au MVP,
 * un seul Membership actif (décision Phase 2, voir plan).
 *
 * `getRoles()` retourne un rôle Symfony Security générique (`ROLE_USER`), distinct du
 * `Role` métier porté par `Membership` (OWNER, etc.) : le premier sert uniquement au
 * système d'authentification, le second à l'autorisation métier propre au produit.
 *
 * L'unicité de `email` n'est plus déclarée via `#[ORM\UniqueConstraint]` depuis la Phase 13 :
 * elle doit exclure les comptes soft-deleted (`deletedAt`, US-SETTINGS-002 - un email de
 * compte supprimé redevient disponible), ce qui exige un index unique partiel
 * (`WHERE deleted_at IS NULL`) que le driver attribute de Doctrine ORM ne supporte pas -
 * même limitation déjà rencontrée et documentée pour
 * App\Organization\Entity\FiscalContext (Phase 3). Créé directement en SQL dans la
 * migration de la Phase 13.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $email;

    #[ORM\Column(type: Types::STRING)]
    private string $password;

    /** NULL = email non vérifié (docs/10-security-privacy.md, section 12). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Soft delete (docs/07-data-model.md, section 30 ; US-SETTINGS-002, Phase 13). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @var Collection<int, Membership> */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'user')]
    private Collection $memberships;

    public function __construct(string $email, string $hashedPassword)
    {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->password = $hashedPassword;
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

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function isEmailVerified(): bool
    {
        return null !== $this->emailVerifiedAt;
    }

    public function markEmailAsVerified(): void
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
    }

    /** Un changement d'email exige une nouvelle vérification (docs/10-security-privacy.md, section 12). */
    public function markEmailAsUnverified(): void
    {
        $this->emailVerifiedAt = null;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function markDeleted(\DateTimeImmutable $at): void
    {
        $this->deletedAt = $at;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /** @return Collection<int, Membership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    /** Appelé exclusivement par Membership::__construct() pour synchroniser le côté inverse. */
    public function addMembership(Membership $membership): void
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
        }
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // Aucun secret en clair transitoire sur cette entité.
    }
}
