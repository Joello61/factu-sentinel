<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Entity;

use App\PlatformAdmin\Repository\PlatformAdminMfaChallengeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Ticket opaque, à usage unique et de courte durée, émis entre l'étape mot de passe
 * (POST /platform-admin/auth/login) et l'étape TOTP (POST /platform-admin/auth/mfa/verify) -
 * plan Phase 15, revue utilisateur du 21/08/2026 : "l'état intermédiaire mfa_required
 * n'est jamais lui-même un moyen de contournement".
 *
 * Même principe de sécurité que App\Identity\Entity\Invitation (sélecteur indexé + hash du
 * vérificateur, jamais le jeton en clair persisté) - jamais un JWT, même à portée réduite :
 * un principe non négociable de ce ticket est qu'il ne donne accès à **aucun** endpoint
 * `/platform-admin/*` métier tant que le code TOTP n'a pas été vérifié.
 *
 * `consumedAt` rend le ticket définitivement inutilisable après un premier succès - jamais
 * rejouable, même avant expiration.
 */
#[ORM\Entity(repositoryClass: PlatformAdminMfaChallengeRepository::class)]
#[ORM\Table(name: 'platform_admin_mfa_challenges')]
class PlatformAdminMfaChallenge
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: PlatformAdministrator::class)]
    #[ORM\JoinColumn(name: 'platform_administrator_id', nullable: false)]
    private PlatformAdministrator $platformAdministrator;

    #[ORM\Column(name: 'token_selector', type: Types::STRING, length: 32, unique: true)]
    private string $tokenSelector;

    #[ORM\Column(name: 'token_hash', type: Types::STRING, length: 64)]
    private string $tokenHash;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'consumed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

    public function __construct(
        PlatformAdministrator $platformAdministrator,
        string $tokenSelector,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->id = Uuid::v7();
        $this->platformAdministrator = $platformAdministrator;
        $this->tokenSelector = $tokenSelector;
        $this->tokenHash = $tokenHash;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $expiresAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPlatformAdministrator(): PlatformAdministrator
    {
        return $this->platformAdministrator;
    }

    public function getTokenSelector(): string
    {
        return $this->tokenSelector;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function isUsable(): bool
    {
        return null === $this->consumedAt && $this->expiresAt > new \DateTimeImmutable();
    }

    public function markConsumed(): void
    {
        $this->consumedAt = new \DateTimeImmutable();
    }
}
