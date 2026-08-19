<?php

declare(strict_types=1);

namespace App\Compliance\Rules\Entity;

use App\Compliance\Rules\Enum\ConfidenceLevel;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Version immuable d'une RegulatoryRule (ADR-003, docs/06-technical-architecture.md section
 * 10 ; docs/07-data-model.md section 16). Aucune méthode de mise à jour n'est exposée
 * volontairement, y compris pour effectiveUntil : toute évolution de la règle crée une
 * nouvelle RuleVersion, l'ancienne recevant sa date de fin de validité par une opération de
 * publication dédiée, hors périmètre de cette phase (aucune règle n'y est encore révisée —
 * voir plan Phase 3, scope boundary) — jamais une modification en place de cette entité.
 *
 * `conditions` porte les seuils/dates concrets de la règle en JSON structuré (montant,
 * date, effectif...) : c'est ce qui évite un `if` codé en dur dans le calculateur qui la
 * consomme (voir Compliance/Service/EligibilityDiagnosticCalculator) — la nature exacte du
 * contenu est documentée au moment de la migration de seed de cette phase, pas ici.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rule_versions')]
#[ORM\Index(name: 'idx_rule_versions_rule_id', columns: ['rule_id'])]
class RuleVersion
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: RegulatoryRule::class)]
    #[ORM\JoinColumn(name: 'rule_id', referencedColumnName: 'id', nullable: false)]
    private RegulatoryRule $rule;

    #[ORM\Column(type: Types::INTEGER)]
    private int $versionNumber;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $effectiveFrom;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveUntil = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $conditions;

    /**
     * Sévérité au sens des six états de résultat de conformité
     * (docs/05-user-stories.md section 8) — nullable : sans objet pour les deux
     * RuleVersion d'éligibilité de cette phase, qui ne produisent jamais de
     * ComplianceFinding, seulement un EligibilityDiagnostic (calendrier). Un enum dédié
     * à ces six états sera introduit avec le Compliance Engine générique de la Phase 5,
     * quand il aura un usage réel — non anticipé ici (voir plan Phase 3, scope boundary).
     */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $severity;

    /** Référence explicite vers docs/02-regulatory-study.md (docs/07-data-model.md, section 16). */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $sourceReference;

    #[ORM\Column(type: Types::STRING, enumType: ConfidenceLevel::class)]
    private ConfidenceLevel $confidenceLevel;

    #[ORM\Column(type: Types::TEXT)]
    private string $explanationTemplate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $conditions */
    public function __construct(
        RegulatoryRule $rule,
        int $versionNumber,
        \DateTimeImmutable $effectiveFrom,
        array $conditions,
        ?string $severity,
        string $sourceReference,
        ConfidenceLevel $confidenceLevel,
        string $explanationTemplate,
    ) {
        $this->id = Uuid::v7();
        $this->rule = $rule;
        $this->versionNumber = $versionNumber;
        $this->effectiveFrom = $effectiveFrom;
        $this->conditions = $conditions;
        $this->severity = $severity;
        $this->sourceReference = $sourceReference;
        $this->confidenceLevel = $confidenceLevel;
        $this->explanationTemplate = $explanationTemplate;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRule(): RegulatoryRule
    {
        return $this->rule;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function getEffectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function getEffectiveUntil(): ?\DateTimeImmutable
    {
        return $this->effectiveUntil;
    }

    /** @return array<string, mixed> */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function getSourceReference(): string
    {
        return $this->sourceReference;
    }

    public function getConfidenceLevel(): ConfidenceLevel
    {
        return $this->confidenceLevel;
    }

    public function getExplanationTemplate(): string
    {
        return $this->explanationTemplate;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
