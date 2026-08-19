<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Entity;

use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Rules\Entity\RuleVersion;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Résultat d'une règle précise au sein d'une ComplianceAnalysis (docs/07-data-model.md,
 * section 18). Référence toujours ruleVersion (la version précise), jamais la
 * RegulatoryRule seule (ADR-003, immutabilité) : un ComplianceFinding consulté plus tard
 * reste fidèle à la version de règle réellement appliquée au moment de l'analyse.
 *
 * message/correctionAction sont copiés/figés à la création depuis
 * RuleVersion.conditions['outcomes'][result], jamais recalculés dynamiquement.
 *
 * Pas de TenantScopedInterface (comme InvoiceLine vis-à-vis d'Invoice) : le scoping tenant
 * est hérité via complianceAnalysis, jamais une donnée directement filtrable côté requête
 * indépendante.
 *
 * Jamais supprimée physiquement (backend/CLAUDE.md, section 7), jamais modifiée après
 * création : aucune méthode de mutation n'est exposée.
 */
#[ORM\Entity]
#[ORM\Table(name: 'compliance_findings')]
#[ORM\Index(name: 'idx_compliance_findings_compliance_analysis_id', columns: ['compliance_analysis_id'])]
class ComplianceFinding
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ComplianceAnalysis::class)]
    #[ORM\JoinColumn(name: 'compliance_analysis_id', nullable: false)]
    private ComplianceAnalysis $complianceAnalysis;

    #[ORM\ManyToOne(targetEntity: RuleVersion::class)]
    #[ORM\JoinColumn(name: 'rule_version_id', nullable: false)]
    private RuleVersion $ruleVersion;

    #[ORM\Column(type: Types::STRING, enumType: ComplianceResult::class)]
    private ComplianceResult $result;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $relatedField;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $observedValue;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $correctionAction;

    public function __construct(
        ComplianceAnalysis $complianceAnalysis,
        RuleVersion $ruleVersion,
        ComplianceResult $result,
        string $message,
        ?string $relatedField,
        ?string $observedValue,
        ?string $correctionAction,
    ) {
        $this->id = Uuid::v7();
        $this->complianceAnalysis = $complianceAnalysis;
        $this->ruleVersion = $ruleVersion;
        $this->result = $result;
        $this->message = $message;
        $this->relatedField = $relatedField;
        $this->observedValue = $observedValue;
        $this->correctionAction = $correctionAction;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRuleVersion(): RuleVersion
    {
        return $this->ruleVersion;
    }

    public function getResult(): ComplianceResult
    {
        return $this->result;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getRelatedField(): ?string
    {
        return $this->relatedField;
    }

    public function getObservedValue(): ?string
    {
        return $this->observedValue;
    }

    public function getCorrectionAction(): ?string
    {
        return $this->correctionAction;
    }
}
