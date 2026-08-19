<?php

declare(strict_types=1);

namespace App\Customer\Entity;

use App\Customer\Enum\CustomerType;
use App\Organization\Entity\Organization;
use App\Shared\Doctrine\TenantScopedInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Client d'une Invoice (docs/07-data-model.md, section 8). Pas d'entité Address associée en
 * Phase 4 (aucun endpoint ne l'expose encore, docs/08-api-specification.md section 26) : ce
 * champ sera ajouté quand un besoin réel apparaîtra plutôt qu'anticipé maintenant (voir plan
 * Phase 4, décision D5).
 *
 * siren reste nullable même pour un PROFESSIONNEL_FRANCAIS : son absence n'est jamais une
 * erreur de validation à la création (05-user-stories.md, US-CUSTOMER-002 ; CLAUDE.md racine
 * section 9, BR-COMPLIANCE-003/ADR-002) — seul le Compliance Engine (Phase 5, inexistant à ce
 * stade) qualifiera cette absence en A_VERIFIER. Voir plan Phase 4, décision D1.
 *
 * Pas d'historisation (décision confirmée, docs/07-data-model.md section 8) : le snapshot
 * pris à chaque analyse de conformité (Phase 5) suffira à la traçabilité.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customers')]
#[ORM\Index(name: 'idx_customers_organization_id', columns: ['organization_id'])]
class Customer implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private Organization $organization;

    #[ORM\Column(type: Types::STRING, enumType: CustomerType::class)]
    private CustomerType $customerType;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 9, nullable: true)]
    private ?string $siren = null;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $vatNumber = null;

    /** Code pays ISO 3166-1 alpha-2 (ex. "FR"). */
    #[ORM\Column(type: Types::STRING, length: 2)]
    private string $country;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** Soft delete (docs/07-data-model.md, section 30) : jamais de suppression physique. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        Organization $organization,
        CustomerType $customerType,
        string $name,
        ?string $siren,
        ?string $vatNumber,
        string $country,
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->customerType = $customerType;
        $this->name = $name;
        $this->siren = $siren;
        $this->vatNumber = $vatNumber;
        $this->country = $country;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organization->getId();
    }

    public function getCustomerType(): CustomerType
    {
        return $this->customerType;
    }

    public function setCustomerType(CustomerType $customerType): void
    {
        $this->customerType = $customerType;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): void
    {
        $this->siren = $siren;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): void
    {
        $this->vatNumber = $vatNumber;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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
}
