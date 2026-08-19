<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Entity;

use App\Compliance\Engine\Enum\ComplianceAnalysisStatus;
use App\Compliance\Engine\Enum\ComplianceResult;
use App\Invoicing\Entity\Invoice;
use App\Organization\Entity\Organization;
use App\Shared\Doctrine\TenantScopedInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Résultat d'une analyse de conformité sur une Invoice précise (docs/07-data-model.md,
 * section 17). Une Invoice peut être analysée plusieurs fois (Invoice 1 --- N
 * ComplianceAnalysis) : anciens et nouveaux résultats restent tous deux consultables
 * (US-COMPLIANCE-006), jamais écrasés.
 *
 * Jamais supprimée physiquement (backend/CLAUDE.md, section 7). Immuable une fois
 * COMPLETED/FAILED : aucune méthode de mutation du contenu n'est exposée hors les
 * transitions de statut utilisées par App\Compliance\Engine\Service\RunComplianceAnalysisService
 * pendant la construction initiale (PENDING -> RUNNING -> COMPLETED), jamais après.
 */
#[ORM\Entity]
#[ORM\Table(name: 'compliance_analyses')]
#[ORM\Index(name: 'idx_compliance_analyses_organization_id', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_compliance_analyses_invoice_id', columns: ['invoice_id'])]
class ComplianceAnalysis implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false)]
    private Invoice $invoice;

    #[ORM\Column(type: Types::STRING, enumType: ComplianceAnalysisStatus::class)]
    private ComplianceAnalysisStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $triggeredAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::STRING, enumType: ComplianceResult::class, nullable: true)]
    private ?ComplianceResult $globalResult = null;

    #[ORM\OneToOne(targetEntity: ContextSnapshot::class, mappedBy: 'complianceAnalysis')]
    private ?ContextSnapshot $contextSnapshot = null;

    public function __construct(Organization $organization, Invoice $invoice)
    {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->invoice = $invoice;
        $this->status = ComplianceAnalysisStatus::PENDING;
        $this->triggeredAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organization->getId();
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getStatus(): ComplianceAnalysisStatus
    {
        return $this->status;
    }

    public function getTriggeredAt(): \DateTimeImmutable
    {
        return $this->triggeredAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getGlobalResult(): ?ComplianceResult
    {
        return $this->globalResult;
    }

    public function getContextSnapshot(): ?ContextSnapshot
    {
        return $this->contextSnapshot;
    }

    public function markRunning(): void
    {
        $this->status = ComplianceAnalysisStatus::RUNNING;
    }

    public function markCompleted(ComplianceResult $globalResult, \DateTimeImmutable $completedAt): void
    {
        $this->status = ComplianceAnalysisStatus::COMPLETED;
        $this->globalResult = $globalResult;
        $this->completedAt = $completedAt;
    }
}
