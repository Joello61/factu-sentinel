<?php

declare(strict_types=1);

namespace App\Compliance\Entity;

use App\Compliance\Rules\Entity\RuleVersion;
use App\Organization\Entity\FiscalContext;
use App\Organization\Entity\Organization;
use App\Shared\Doctrine\TenantScopedInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Porte le résultat de US-COMPLIANCE-001 (docs/07-data-model.md, section 21 ; confirmé
 * "Résolu" en Phase 3, voir plan). Distincte d'un futur ComplianceAnalysis (Phase 5), qui
 * porte sur une facture précise : ceci porte sur l'éligibilité globale de l'organisation à
 * la réforme.
 *
 * Amendement documenté par rapport au schéma initial de la section 21 (voir plan Phase 3 et
 * mise à jour de 07-data-model.md) : `explanation` et les deux références de RuleVersion
 * sont ajoutées, pour la même raison qu'un ComplianceFinding fige la version de règle
 * utilisée (docs/06-technical-architecture.md, section 10) : un diagnostic consulté plus
 * tard doit rester fidèle aux règles actives au moment de son calcul, jamais recalculé
 * dynamiquement depuis la RuleVersion courante.
 *
 * receptionObligationDate/emissionObligationDate sont nullables : null signifie que
 * l'organisation est hors du périmètre de la réforme (vatStatus = NON_ASSUJETTI,
 * docs/02-regulatory-study.md section 6) : ce n'est pas une absence de calcul.
 *
 * Immuable après création : un nouveau calcul crée toujours une nouvelle ligne (computedAt
 * les distingue), jamais une mise à jour en place : aucune méthode de mutation n'est
 * exposée.
 */
#[ORM\Entity]
#[ORM\Table(name: 'eligibility_diagnostics')]
#[ORM\Index(name: 'idx_eligibility_diagnostics_organization_id', columns: ['organization_id'])]
class EligibilityDiagnostic implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: FiscalContext::class)]
    #[ORM\JoinColumn(name: 'fiscal_context_id', nullable: false)]
    private FiscalContext $fiscalContext;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $receptionObligationDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emissionObligationDate;

    #[ORM\Column(type: Types::TEXT)]
    private string $explanation;

    #[ORM\ManyToOne(targetEntity: RuleVersion::class)]
    #[ORM\JoinColumn(name: 'franchise_rule_version_id', nullable: false)]
    private RuleVersion $franchiseRuleVersion;

    #[ORM\ManyToOne(targetEntity: RuleVersion::class)]
    #[ORM\JoinColumn(name: 'calendar_rule_version_id', nullable: false)]
    private RuleVersion $calendarRuleVersion;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $computedAt;

    public function __construct(
        Organization $organization,
        FiscalContext $fiscalContext,
        ?\DateTimeImmutable $receptionObligationDate,
        ?\DateTimeImmutable $emissionObligationDate,
        string $explanation,
        RuleVersion $franchiseRuleVersion,
        RuleVersion $calendarRuleVersion,
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->fiscalContext = $fiscalContext;
        $this->receptionObligationDate = $receptionObligationDate;
        $this->emissionObligationDate = $emissionObligationDate;
        $this->explanation = $explanation;
        $this->franchiseRuleVersion = $franchiseRuleVersion;
        $this->calendarRuleVersion = $calendarRuleVersion;
        $this->computedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organization->getId();
    }

    public function getFiscalContext(): FiscalContext
    {
        return $this->fiscalContext;
    }

    public function getReceptionObligationDate(): ?\DateTimeImmutable
    {
        return $this->receptionObligationDate;
    }

    public function getEmissionObligationDate(): ?\DateTimeImmutable
    {
        return $this->emissionObligationDate;
    }

    public function getExplanation(): string
    {
        return $this->explanation;
    }

    public function getFranchiseRuleVersion(): RuleVersion
    {
        return $this->franchiseRuleVersion;
    }

    public function getCalendarRuleVersion(): RuleVersion
    {
        return $this->calendarRuleVersion;
    }

    public function getComputedAt(): \DateTimeImmutable
    {
        return $this->computedAt;
    }
}
