<?php

declare(strict_types=1);

namespace App\Invoicing\Entity;

use App\Customer\Entity\Customer;
use App\Invoicing\Enum\InvoiceSource;
use App\Invoicing\Enum\InvoiceStatus;
use App\Invoicing\Enum\OperationType;
use App\Organization\Entity\Organization;
use App\Shared\Doctrine\TenantScopedInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Facture à des fins d'analyse uniquement, jamais un document destiné à être émis ou
 * transmis (docs/06-technical-architecture.md, section 6 ; docs/07-data-model.md, section
 * 10). Pas de colonne `deleted_at` en Phase 4 : aucun endpoint DELETE n'existe pour cette
 * ressource dans docs/08-api-specification.md section 27 (contrairement à Customer) - voir
 * plan Phase 4, décision D4. Elle sera ajoutée exactement quand un endpoint de suppression
 * sera spécifié.
 *
 * `version` (#[ORM\Version]) porte le verrouillage optimiste de PATCH /invoices/{id}
 * (docs/08-api-specification.md, section 21 ; plan Phase 4, décision D3), exposé en ETag
 * par les controllers. Vérifié contre le code source de doctrine/orm installé (3.6) :
 * EntityManagerInterface::find($class, $id, LockMode::OPTIMISTIC, $expectedVersion) lève
 * Doctrine\ORM\OptimisticLockException si la version ne correspond plus.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invoices')]
#[ORM\Index(name: 'idx_invoices_organization_id', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_invoices_organization_invoice_number', columns: ['organization_id', 'invoice_number'])]
class Invoice implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false)]
    private Customer $customer;

    /** Numéro métier tel que présent sur la facture analysée, jamais généré par ce produit. */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $invoiceNumber = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $issueDate;

    #[ORM\Column(type: Types::STRING, enumType: OperationType::class)]
    private OperationType $operationType;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $totalAmountHt;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $totalAmountTtc;

    /** Régime/exonération global de la facture (docs/07-data-model.md, section 12). */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $vatExemptionReason = null;

    #[ORM\Column(type: Types::STRING, enumType: InvoiceStatus::class)]
    private InvoiceStatus $status;

    #[ORM\Column(type: Types::STRING, enumType: InvoiceSource::class)]
    private InvoiceSource $source;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, InvoiceLine> */
    #[ORM\OneToMany(targetEntity: InvoiceLine::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lines;

    public function __construct(
        Organization $organization,
        Customer $customer,
        ?string $invoiceNumber,
        \DateTimeImmutable $issueDate,
        OperationType $operationType,
        string $currency,
        ?string $vatExemptionReason,
        InvoiceSource $source,
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->customer = $customer;
        $this->invoiceNumber = $invoiceNumber;
        $this->issueDate = $issueDate;
        $this->operationType = $operationType;
        $this->currency = $currency;
        $this->totalAmountHt = '0.00';
        $this->totalAmountTtc = '0.00';
        $this->vatExemptionReason = $vatExemptionReason;
        $this->status = InvoiceStatus::DRAFT;
        $this->source = $source;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->lines = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organization->getId();
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): void
    {
        $this->invoiceNumber = $invoiceNumber;
    }

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeImmutable $issueDate): void
    {
        $this->issueDate = $issueDate;
    }

    public function getOperationType(): OperationType
    {
        return $this->operationType;
    }

    public function setOperationType(OperationType $operationType): void
    {
        $this->operationType = $operationType;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getTotalAmountHt(): string
    {
        return $this->totalAmountHt;
    }

    public function getTotalAmountTtc(): string
    {
        return $this->totalAmountTtc;
    }

    public function getVatExemptionReason(): ?string
    {
        return $this->vatExemptionReason;
    }

    public function setVatExemptionReason(?string $vatExemptionReason): void
    {
        $this->vatExemptionReason = $vatExemptionReason;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function getSource(): InvoiceSource
    {
        return $this->source;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, InvoiceLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    /**
     * Remplace intégralement les lignes (docs/08-api-specification.md, section 19 : pas de
     * cycle de vie de ligne indépendant de la facture) : `orphanRemoval: true` supprime les
     * anciennes au flush, jamais un diff ligne à ligne.
     */
    public function replaceLines(InvoiceLine ...$lines): void
    {
        $this->lines->clear();

        foreach ($lines as $line) {
            $this->lines->add($line);
        }
    }

    public function setTotals(string $totalAmountHt, string $totalAmountTtc): void
    {
        $this->totalAmountHt = $totalAmountHt;
        $this->totalAmountTtc = $totalAmountTtc;
    }

    /**
     * Règle documentée de transition DRAFT -> READY_FOR_ANALYSIS (docs/08-api-specification.md,
     * section 28) : "client et lignes minimales présents". ANALYZED/ANALYSIS_STALE ne sont
     * jamais produits ici (inatteignables avant Phase 5, voir plan Phase 4) : cette méthode ne
     * régresse jamais un statut déjà ANALYZED/ANALYSIS_STALE vers DRAFT (transition interdite,
     * docs/07-data-model.md section 29), elle ne fait qu'établir/actualiser le statut tant que
     * la facture reste dans le sous-ensemble DRAFT/READY_FOR_ANALYSIS.
     */
    public function refreshReadinessStatus(): void
    {
        if (InvoiceStatus::DRAFT !== $this->status && InvoiceStatus::READY_FOR_ANALYSIS !== $this->status) {
            return;
        }

        $this->status = $this->lines->isEmpty() ? InvoiceStatus::DRAFT : InvoiceStatus::READY_FOR_ANALYSIS;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Transition produite par App\Compliance\Engine\Service\RunComplianceAnalysisService
     * (Phase 5) à l'issue d'une ComplianceAnalysis COMPLETED : ANALYZED quel que soit le
     * statut précédent (y compris ANALYSIS_STALE, US-COMPLIANCE-006 : une relance après
     * correction remplace toujours l'ancien statut, sans jamais effacer les analyses
     * passées elles-mêmes).
     */
    public function markAnalyzed(): void
    {
        $this->status = InvoiceStatus::ANALYZED;
    }

    /**
     * US-COMPLIANCE-006bis : modifier une facture ANALYZED rend son résultat de conformité
     * obsolète. No-op si le statut n'est pas ANALYZED (jamais de régression depuis
     * ANALYSIS_STALE lui-même, ni depuis DRAFT/READY_FOR_ANALYSIS qui n'ont jamais été
     * analysés) - même défense que refreshReadinessStatus().
     */
    public function markStale(): void
    {
        if (InvoiceStatus::ANALYZED === $this->status) {
            $this->status = InvoiceStatus::ANALYSIS_STALE;
        }
    }
}
