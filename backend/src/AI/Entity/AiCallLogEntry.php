<?php

declare(strict_types=1);

namespace App\AI\Entity;

use App\AI\Enum\AiCallEndpoint;
use App\AI\Repository\AiCallLogEntryRepository;
use App\Shared\Doctrine\TenantScopedInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Nouvelle entité (docs/07-data-model.md, section 22 ; plan Phase 15) - jusqu'ici, le
 * volume/coût des appels IA n'était mentionné que documentairement
 * (docs/06-technical-architecture.md, section 15) sans être persisté. Nécessaire à
 * US-PLATFORMADMIN-005 (santé applicative) et US-ANALYTICS-001 (Phase 16).
 *
 * Ne contient **jamais** le contenu du prompt ni la réponse générée (même discipline que
 * l'audit IA existant, docs/06-technical-architecture.md section 15) - uniquement des
 * métadonnées d'usage agrégables. `estimatedCost` reste une estimation, jamais un montant
 * facturé réel (le fournisseur IA reste l'autorité de facturation).
 *
 * Tenant-scoped (permet une agrégation par organisation si nécessaire) - lue cross-tenant
 * uniquement via App\AI\Service\AiUsageReaderInterface (Phase 15), jamais écrite depuis
 * App\PlatformAdmin.
 */
#[ORM\Entity(repositoryClass: AiCallLogEntryRepository::class)]
#[ORM\Table(name: 'ai_call_log_entries')]
#[ORM\Index(name: 'idx_ai_call_log_entries_created_at', columns: ['created_at'])]
class AiCallLogEntry implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'organization_id', type: UuidType::NAME)]
    private Uuid $organizationId;

    #[ORM\Column(type: Types::STRING, enumType: AiCallEndpoint::class)]
    private AiCallEndpoint $endpoint;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $succeeded;

    #[ORM\Column(name: 'estimated_cost', type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $estimatedCost;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Uuid $organizationId, AiCallEndpoint $endpoint, bool $succeeded, ?string $estimatedCost)
    {
        $this->id = Uuid::v7();
        $this->organizationId = $organizationId;
        $this->endpoint = $endpoint;
        $this->succeeded = $succeeded;
        $this->estimatedCost = $estimatedCost;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organizationId;
    }

    public function getEndpoint(): AiCallEndpoint
    {
        return $this->endpoint;
    }

    public function isSucceeded(): bool
    {
        return $this->succeeded;
    }

    public function getEstimatedCost(): ?string
    {
        return $this->estimatedCost;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
