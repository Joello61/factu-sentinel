<?php

declare(strict_types=1);

namespace App\Shared\Audit;

use App\Shared\Audit\Entity\AuditLogEntry;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Point d'entrée unique pour écrire au journal d'audit (docs/06-technical-architecture.md,
 * section 22). N'ouvre pas sa propre transaction : persist() seulement, le flush()/commit()
 * appartient à l'appelant (App\Organization\Service\ConfigureOrganizationService pour cette
 * phase), pour que l'écriture d'audit fasse partie de la même transaction atomique que les
 * données qu'elle décrit plutôt que d'être une écriture indépendante qui pourrait réussir
 * seule si le reste échoue.
 */
final class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed>|null $previousState
     * @param array<string, mixed>|null $newState
     */
    public function record(
        ?Uuid $organizationId,
        ActorType $actorType,
        ?Uuid $actorId,
        EventType $eventType,
        string $entityType,
        string $entityId,
        ?array $previousState = null,
        ?array $newState = null,
    ): void {
        $this->entityManager->persist(new AuditLogEntry(
            $organizationId,
            $actorType,
            $actorId,
            $eventType,
            $entityType,
            $entityId,
            $previousState,
            $newState,
        ));
    }
}
