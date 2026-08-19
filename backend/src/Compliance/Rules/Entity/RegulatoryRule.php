<?php

declare(strict_types=1);

namespace App\Compliance\Rules\Entity;

use App\Compliance\Rules\Enum\RuleCategory;
use App\Compliance\Rules\Enum\RuleStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Référentiel réglementaire pur (docs/06-technical-architecture.md, section 9 ;
 * docs/07-data-model.md, section 15) : entité globale, jamais tenant-scoped, sans aucune
 * dépendance sortante vers un autre module (n'importe rien de App\Organization, App\Identity,
 * etc.) — la réglementation française ne dépend d'aucune organisation particulière.
 *
 * Identifiant métier stable (pas un UUID) : un identifiant lisible et invariant dans le
 * temps, indépendant de ses versions (docs/06-technical-architecture.md, section 9).
 */
#[ORM\Entity]
#[ORM\Table(name: 'regulatory_rules')]
class RegulatoryRule
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::STRING, enumType: RuleCategory::class)]
    private RuleCategory $category;

    /** Code pays ISO 3166-1 alpha-2 — portée géographique (docs/07-data-model.md, section 15). */
    #[ORM\Column(type: Types::STRING, length: 2)]
    private string $jurisdiction;

    #[ORM\Column(type: Types::STRING, enumType: RuleStatus::class)]
    private RuleStatus $status;

    public function __construct(
        string $id,
        string $name,
        string $description,
        RuleCategory $category,
        string $jurisdiction,
        RuleStatus $status,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->category = $category;
        $this->jurisdiction = $jurisdiction;
        $this->status = $status;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCategory(): RuleCategory
    {
        return $this->category;
    }

    public function getJurisdiction(): string
    {
        return $this->jurisdiction;
    }

    public function getStatus(): RuleStatus
    {
        return $this->status;
    }
}
