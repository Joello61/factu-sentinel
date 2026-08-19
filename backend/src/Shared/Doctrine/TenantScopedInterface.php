<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Symfony\Component\Uid\Uuid;

/**
 * Marqueur pour toute entité tenant-scoped (docs/06-technical-architecture.md, ADR-004 ;
 * docs/07-data-model.md, section 4). `Organization` n'implémente jamais cette interface :
 * c'est le tenant racine, pas une entité tenant-scoped (elle n'a pas de organization_id).
 *
 * Toute entité qui l'implémente doit porter une colonne `organization_id` réellement
 * indexée en base - c'est ce que `TenantFilter` exploite pour filtrer automatiquement.
 */
interface TenantScopedInterface
{
    public function getOrganizationId(): Uuid;
}
