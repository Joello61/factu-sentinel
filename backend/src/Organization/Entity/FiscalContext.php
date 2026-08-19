<?php

declare(strict_types=1);

namespace App\Organization\Entity;

use App\Organization\Enum\CompanySizeCategory;
use App\Organization\Enum\VatStatus;
use App\Shared\Doctrine\TenantScopedInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Contexte fiscal et réglementaire de l'entreprise (docs/07-data-model.md, section 7),
 * historisé plutôt que porté par Organization : ce contexte évolue dans le temps
 * indépendamment de l'identité légale (franchissement de seuil, changement de taille).
 *
 * Toujours créée complète (les quatre attributs métier ci-dessous non-nullables) : la
 * validation de complétude a lieu en amont, dans ConfigureOrganizationService, avant toute
 * tentative de construction : il n'existe donc jamais de FiscalContext partiellement
 * configuré en base (voir plan Phase 3).
 *
 * companySizeCategory n'est jamais fourni par l'appelant : calculé à partir des trois
 * valeurs brutes par ConfigureOrganizationService, à partir des seuils versionnés dans
 * Compliance/Rules (RuleVersion "calendrier-obligation-emission"), jamais codé en dur ici.
 */
/**
 * L'index partiel garantissant au plus une ligne "courante" (effective_until IS NULL) par
 * organisation n'est pas déclaré via un attribut Doctrine : le driver attribute de Doctrine
 * ORM (version installée, voir backend/CLAUDE.md section 2) ne supporte pas nativement les
 * index partiels (clause WHERE) sur #[ORM\UniqueConstraint] : vérifié avant d'écrire cette
 * entité plutôt que supposé. Il est donc créé directement en SQL dans la migration (voir
 * la migration de cette phase), comme les contraintes déjà gérées à la main en Phase 2.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fiscal_contexts')]
#[ORM\Index(name: 'idx_fiscal_contexts_organization_id', columns: ['organization_id'])]
class FiscalContext implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private Organization $organization;

    #[ORM\Column(type: Types::STRING, enumType: VatStatus::class)]
    private VatStatus $vatStatus;

    #[ORM\Column(type: Types::INTEGER)]
    private int $employeesCount;

    #[ORM\Column(type: Types::DECIMAL, precision: 16, scale: 2)]
    private string $annualTurnover;

    #[ORM\Column(type: Types::DECIMAL, precision: 16, scale: 2)]
    private string $annualBalanceSheetTotal;

    #[ORM\Column(type: Types::STRING, enumType: CompanySizeCategory::class)]
    private CompanySizeCategory $companySizeCategory;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $effectiveFrom;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveUntil = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $recordedAt;

    public function __construct(
        Organization $organization,
        VatStatus $vatStatus,
        int $employeesCount,
        string $annualTurnover,
        string $annualBalanceSheetTotal,
        CompanySizeCategory $companySizeCategory,
        \DateTimeImmutable $effectiveFrom,
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->vatStatus = $vatStatus;
        $this->employeesCount = $employeesCount;
        $this->annualTurnover = $annualTurnover;
        $this->annualBalanceSheetTotal = $annualBalanceSheetTotal;
        $this->companySizeCategory = $companySizeCategory;
        $this->effectiveFrom = $effectiveFrom;
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organization->getId();
    }

    public function getVatStatus(): VatStatus
    {
        return $this->vatStatus;
    }

    public function getEmployeesCount(): int
    {
        return $this->employeesCount;
    }

    public function getAnnualTurnover(): string
    {
        return $this->annualTurnover;
    }

    public function getAnnualBalanceSheetTotal(): string
    {
        return $this->annualBalanceSheetTotal;
    }

    public function getCompanySizeCategory(): CompanySizeCategory
    {
        return $this->companySizeCategory;
    }

    public function getEffectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function getEffectiveUntil(): ?\DateTimeImmutable
    {
        return $this->effectiveUntil;
    }

    /**
     * Ferme cette version (invariant de non-chevauchement, docs/07-data-model.md section
     * 37) : garanti ici par construction, jamais par rétro-datation : voir plan Phase 3.
     */
    public function close(\DateTimeImmutable $effectiveUntil): void
    {
        $this->effectiveUntil = $effectiveUntil;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
