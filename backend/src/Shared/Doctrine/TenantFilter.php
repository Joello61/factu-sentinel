<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Ajoute automatiquement `WHERE organization_id = :tenant_org` à toute requête portant
 * sur une entité `TenantScopedInterface` (docs/06-technical-architecture.md, ADR-004).
 * Désactivé par défaut (config/packages/doctrine.yaml) ; activé par
 * App\Shared\Security\TenantFilterActivationListener sur
 * lexik_jwt_authentication.on_jwt_authenticated, jamais laissé au contrôleur.
 */
final class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (null === $targetEntity->reflClass || !$targetEntity->reflClass->implementsInterface(TenantScopedInterface::class)) {
            return '';
        }

        if (!$this->hasParameter('organization_id')) {
            return '';
        }

        return sprintf('%s.organization_id = %s', $targetTableAlias, $this->getParameter('organization_id'));
    }
}
