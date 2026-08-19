<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Store PostgreSQL léger pour l'en-tête Idempotency-Key (../../CLAUDE.md racine, section
 * 11 ; décision Phase 5 documentée dans le plan : pas de Redis avant son besoin réel,
 * ADR-006, traitement asynchrone de la Phase 7).
 *
 * organizationId est un Uuid brut, pas une relation Doctrine vers Organization : comme
 * App\Shared\Audit\Entity\AuditLogEntry, Shared ne doit jamais dépendre d'un autre module
 * métier (backend/CLAUDE.md, section 3).
 *
 * Jamais construite/lue directement par un controller ou un service métier : uniquement
 * via App\Shared\Idempotency\Repository\IdempotencyKeyRepository (UPSERT atomique) et
 * App\Shared\Idempotency\Service\IdempotencyStore -- voir ces classes pour l'algorithme de
 * réservation sûr sous concurrence.
 */
#[ORM\Entity]
#[ORM\Table(name: 'idempotency_keys')]
#[ORM\UniqueConstraint(name: 'uniq_idempotency_keys_organization_key', columns: ['organization_id', 'idempotency_key'])]
class IdempotencyKey
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $organizationId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $idempotencyKey;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $responseStatus;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $responseBody;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getResponseStatus(): ?int
    {
        return $this->responseStatus;
    }

    /** @return array<string, mixed>|null */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}
