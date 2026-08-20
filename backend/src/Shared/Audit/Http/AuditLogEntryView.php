<?php

declare(strict_types=1);

namespace App\Shared\Audit\Http;

use App\Shared\Audit\Entity\AuditLogEntry;

/**
 * GET /audit-events (docs/08-api-specification.md, section 39). Expose exactement la forme
 * documentée - event_type, occurred_at, actor{type, id} - jamais previous_state/new_state :
 * ces deux champs ne sont pas dans le contrat, et bien qu'aucun appelant actuel
 * n'y écrive de secret (revue Phase 10 de tous les sites d'appel de
 * App\Shared\Audit\AuditLogger::record - uniquement des données métier déjà propriété du
 * tenant : statut, identifiants, nom/SIREN/pays d'un Customer), les exposer irait au-delà
 * de ce qui est documenté sans nécessité produit identifiée.
 */
final class AuditLogEntryView
{
    /** @return array<string, mixed> */
    public static function fromEntity(AuditLogEntry $entry): array
    {
        return [
            'event_type' => $entry->getEventType()->value,
            'entity_type' => $entry->getEntityType(),
            'entity_id' => $entry->getEntityId(),
            'occurred_at' => $entry->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'actor' => [
                'type' => $entry->getActorType()->value,
                'id' => $entry->getActorId()?->toRfc4122(),
            ],
        ];
    }
}
