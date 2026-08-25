<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Entity;

use App\PlatformAdmin\Repository\PlatformAdministratorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Identité **structurellement séparée** de App\Identity\Entity\User (docs/06-technical-architecture.md,
 * ADR-009 ; docs/07-data-model.md, section 5) : globale, hors modèle tenant, aucune relation
 * avec Organization/Membership. Jamais un indicateur booléen sur User - un compromis de
 * l'un des deux comptes n'expose jamais l'autre espace d'autorisation.
 *
 * `totpSecret` n'est jamais stocké en clair (App\PlatformAdmin\Service\PlatformAdminMfaService
 * chiffre/déchiffre via sodium_crypto_secretbox avant toute lecture/écriture ici).
 * `totpConfirmedAt` reste null tant que l'enrôlement MFA n'a pas été confirmé par un premier
 * code TOTP valide (plan Phase 15) - un compte non confirmé ne peut jamais obtenir de JWT
 * complet.
 *
 * `revokedAt` (plan Phase 15, revue utilisateur du 21/08/2026) : un JWT signé valide ne doit
 * jamais suffire à lui seul - ce compte est rechargé depuis
 * App\PlatformAdmin\Repository\PlatformAdministratorRepository à chaque requête authentifiée
 * (même patron que App\Identity\Repository\UserRepository excluant les comptes soft-deleted),
 * et un compte révoqué en cours de session perd l'accès dès la requête suivante.
 */
#[ORM\Entity(repositoryClass: PlatformAdministratorRepository::class)]
#[ORM\Table(name: 'platform_administrators')]
class PlatformAdministrator implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::STRING)]
    private string $password;

    /** Chiffré (sodium_crypto_secretbox) - jamais en clair, voir PlatformAdminMfaService. */
    #[ORM\Column(name: 'totp_secret', type: Types::TEXT)]
    private string $totpSecret;

    #[ORM\Column(name: 'totp_confirmed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $totpConfirmedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'revoked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(string $email, string $hashedPassword, string $encryptedTotpSecret)
    {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->password = $hashedPassword;
        $this->totpSecret = $encryptedTotpSecret;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function getTotpSecret(): string
    {
        return $this->totpSecret;
    }

    public function isMfaConfirmed(): bool
    {
        return null !== $this->totpConfirmedAt;
    }

    public function confirmMfa(): void
    {
        $this->totpConfirmedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function revoke(): void
    {
        $this->revokedAt = new \DateTimeImmutable();
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_PLATFORM_ADMIN'];
    }

    public function eraseCredentials(): void
    {
        // Aucun secret en clair transitoire sur cette entité.
    }
}
