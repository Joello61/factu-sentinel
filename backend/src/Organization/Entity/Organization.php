<?php

declare(strict_types=1);

namespace App\Organization\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Identité légale de l'entreprise cliente. C'est le tenant lui-même : il n'existe pas
 * d'entité Tenant séparée (docs/07-data-model.md, section 4), et elle n'est donc jamais
 * elle-même tenant-scoped (n'implémente pas TenantScopedInterface, n'a pas de
 * organization_id).
 *
 * Créée vide à l'inscription (Phase 2, US-AUTH-001 -> US-ONBOARDING-001) : `legal_name`,
 * `siren` et `country` sont nullable jusqu'à leur saisie en Phase 3
 * (`PATCH /organizations/current`). Invariant explicite : une organisation est
 * "configurée" quand `legal_name` n'est pas nul — pas de colonne de statut séparée pour
 * ce seul besoin.
 *
 * Le contexte fiscal (statut TVA, taille) n'est volontairement pas porté ici : il évolue
 * dans le temps indépendamment de l'identité légale et sera modélisé par un FiscalContext
 * historisé distinct en Phase 3 (docs/07-data-model.md, section 7).
 */
#[ORM\Entity]
#[ORM\Table(name: 'organizations')]
class Organization
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $legalName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $tradeName = null;

    #[ORM\Column(type: Types::STRING, length: 9, nullable: true)]
    private ?string $siren = null;

    #[ORM\Column(type: Types::STRING, length: 14, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $legalForm = null;

    /** Code pays ISO 3166-1 alpha-2 (ex. "FR"). */
    #[ORM\Column(type: Types::STRING, length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLegalName(): ?string
    {
        return $this->legalName;
    }

    public function isConfigured(): bool
    {
        return null !== $this->legalName;
    }

    public function getTradeName(): ?string
    {
        return $this->tradeName;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function getLegalForm(): ?string
    {
        return $this->legalForm;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
