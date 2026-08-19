<?php

declare(strict_types=1);

namespace App\Shared\Audit\Entity;

use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Journal append-only (docs/07-data-model.md, section 20 ; docs/06-technical-architecture.md,
 * section 22) : aucune méthode de mise à jour ou de suppression n'est exposée, cohérent
 * avec backend/CLAUDE.md section 7 ("AuditLogEntry ne sont jamais supprimées").
 *
 * organizationId/actorId sont des références opaques (Uuid brut, pas de relation Doctrine
 * vers Organization/User) plutôt que des associations ManyToOne : Shared ne doit lire ni
 * écrire directement les entités internes d'un autre module (backend/CLAUDE.md, section 3)
 * — Audit reste transverse, jamais couplé aux modules métier qu'il journalise.
 *
 * organizationId nullable (docs/07-data-model.md, section 20 et 25 : "nullable pour
 * événements globaux") — cette entité n'implémente donc volontairement pas
 * TenantScopedInterface, qui exige un organization_id non nul ; elle n'est jamais filtrée
 * automatiquement par TenantFilter.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_log_entries')]
#[ORM\Index(name: 'idx_audit_log_entries_organization_id', columns: ['organization_id'])]
class AuditLogEntry
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $organizationId;

    #[ORM\Column(type: Types::STRING, enumType: ActorType::class)]
    private ActorType $actorType;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $actorId;

    #[ORM\Column(type: Types::STRING, enumType: EventType::class)]
    private EventType $eventType;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $entityType;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $entityId;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $previousState;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newState;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed>|null $previousState
     * @param array<string, mixed>|null $newState
     */
    public function __construct(
        ?Uuid $organizationId,
        ActorType $actorType,
        ?Uuid $actorId,
        EventType $eventType,
        string $entityType,
        string $entityId,
        ?array $previousState,
        ?array $newState,
    ) {
        $this->id = Uuid::v7();
        $this->organizationId = $organizationId;
        $this->actorType = $actorType;
        $this->actorId = $actorId;
        $this->eventType = $eventType;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->previousState = $previousState;
        $this->newState = $newState;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganizationId(): ?Uuid
    {
        return $this->organizationId;
    }

    public function getActorType(): ActorType
    {
        return $this->actorType;
    }

    public function getActorId(): ?Uuid
    {
        return $this->actorId;
    }

    public function getEventType(): EventType
    {
        return $this->eventType;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    /** @return array<string, mixed>|null */
    public function getPreviousState(): ?array
    {
        return $this->previousState;
    }

    /** @return array<string, mixed>|null */
    public function getNewState(): ?array
    {
        return $this->newState;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
