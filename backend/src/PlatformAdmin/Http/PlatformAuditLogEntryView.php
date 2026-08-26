<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Http;

use App\Shared\Audit\Entity\AuditLogEntry;

/**
 * GET /platform-admin/audit-events (docs/08-api-specification.md, section 38.2) - jamais
 * App\Shared\Audit\Http\AuditLogEntryView (vue tenant, section 39), qui omet
 * organization_id (implicite côté tenant, essentiel côté cross-tenant).
 */
final class PlatformAuditLogEntryView
{
    /** @return array<string, mixed> */
    public static function fromEntity(AuditLogEntry $entry): array
    {
        return [
            'event_type' => $entry->getEventType()->value,
            'entity_type' => $entry->getEntityType(),
            'entity_id' => $entry->getEntityId(),
            'organization_id' => $entry->getOrganizationId()?->toRfc4122(),
            'occurred_at' => $entry->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'actor' => [
                'type' => $entry->getActorType()->value,
                'id' => $entry->getActorId()?->toRfc4122(),
            ],
        ];
    }
}
