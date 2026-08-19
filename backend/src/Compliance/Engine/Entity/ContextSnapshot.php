<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Entity;

use App\Organization\Entity\FiscalContext;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Contexte figé au moment d'une ComplianceAnalysis (docs/07-data-model.md, section 19) :
 * FiscalContext référencé (déjà historisé, jamais copié), Customer copié en JSON (jamais
 * historisé, sa valeur au moment de l'analyse doit rester consultable même si le client est
 * modifié ensuite).
 *
 * Pas de champ invoice_snapshot_reference distinct : ComplianceAnalysis.invoice_id porte
 * déjà cette référence (même principe d'équivalence que ComplianceFinding, qui ne répète
 * pas invoice_id/date déjà portés par son ComplianceAnalysis parente, docs/07-data-model.md
 * section 18) — écart assumé plutôt qu'une duplication systématique.
 *
 * Jamais supprimée physiquement (backend/CLAUDE.md, section 7), jamais modifiée après
 * création : aucune méthode de mutation n'est exposée.
 */
#[ORM\Entity]
#[ORM\Table(name: 'context_snapshots')]
class ContextSnapshot
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: ComplianceAnalysis::class, inversedBy: 'contextSnapshot')]
    #[ORM\JoinColumn(name: 'compliance_analysis_id', nullable: false, unique: true)]
    private ComplianceAnalysis $complianceAnalysis;

    #[ORM\ManyToOne(targetEntity: FiscalContext::class)]
    #[ORM\JoinColumn(name: 'fiscal_context_id', nullable: false)]
    private FiscalContext $fiscalContext;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $customerSnapshot;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $computedAt;

    /** @param array<string, mixed> $customerSnapshot */
    public function __construct(
        ComplianceAnalysis $complianceAnalysis,
        FiscalContext $fiscalContext,
        array $customerSnapshot,
    ) {
        $this->id = Uuid::v7();
        $this->complianceAnalysis = $complianceAnalysis;
        $this->fiscalContext = $fiscalContext;
        $this->customerSnapshot = $customerSnapshot;
        $this->computedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFiscalContext(): FiscalContext
    {
        return $this->fiscalContext;
    }

    /** @return array<string, mixed> */
    public function getCustomerSnapshot(): array
    {
        return $this->customerSnapshot;
    }

    public function getComputedAt(): \DateTimeImmutable
    {
        return $this->computedAt;
    }
}
