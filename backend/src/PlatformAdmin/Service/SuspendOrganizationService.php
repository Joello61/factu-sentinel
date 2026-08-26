<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Service;

use App\Organization\Entity\Organization;
use App\PlatformAdmin\Entity\PlatformAdministrator;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * POST /platform-admin/organizations/{id}/suspend|reactivate (US-PLATFORMADMIN-002,
 * docs/08-api-specification.md section 38.2). Effet immédiat : App\Shared\Security\
 * TenantFilterActivationListener rejette tout membre de cette organisation dès sa prochaine
 * requête authentifiée, sans attendre l'expiration de son JWT (App\Organization\Entity\
 * Organization::isSuspended()).
 */
final readonly class SuspendOrganizationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
    ) {
    }

    public function suspend(Organization $organization, PlatformAdministrator $actor): void
    {
        $organization->suspend();

        $this->auditLogger->record(
            organizationId: null,
            actorType: ActorType::PLATFORM_ADMIN,
            actorId: $actor->getId(),
            eventType: EventType::PLATFORM_ORGANIZATION_SUSPENDED,
            entityType: 'Organization',
            entityId: $organization->getId()->toRfc4122(),
        );

        $this->entityManager->flush();
    }

    public function reactivate(Organization $organization, PlatformAdministrator $actor): void
    {
        $organization->reactivate();

        $this->auditLogger->record(
            organizationId: null,
            actorType: ActorType::PLATFORM_ADMIN,
            actorId: $actor->getId(),
            eventType: EventType::PLATFORM_ORGANIZATION_REACTIVATED,
            entityType: 'Organization',
            entityId: $organization->getId()->toRfc4122(),
        );

        $this->entityManager->flush();
    }
}
