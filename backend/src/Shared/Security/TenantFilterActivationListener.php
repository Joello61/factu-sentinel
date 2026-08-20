<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Shared\Doctrine\TenantFilter;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Active l'isolation tenant (docs/06-technical-architecture.md, ADR-004) juste après
 * qu'un JWT a authentifié une requête (lexik_jwt_authentication.on_jwt_authenticated),
 * avant que le contrôleur ne puisse déclencher la moindre requête Doctrine - jamais un
 * kernel.request de priorité approximative.
 *
 * Invariant non négociable : aucune requête Doctrine tenant-scoped n'est exécutée sans
 * que ce listener n'ait activé TenantFilter pour la requête HTTP courante.
 */
final class TenantFilterActivationListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_authenticated')]
    public function onJwtAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $claim = $event->getPayload()['org'] ?? null;

        if (!is_string($claim) || '' === $claim) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Authenticated JWT carries no "org" claim.');
        }

        try {
            $organizationId = Uuid::fromString($claim);
        } catch (\InvalidArgumentException $exception) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Authenticated JWT carries a malformed "org" claim.', previous: $exception);
        }

        $this->requestStack->getCurrentRequest()?->attributes->set(CurrentOrganizationResolver::ATTRIBUTE, $organizationId);

        $filter = $this->entityManager->getFilters()->enable('tenant_filter');
        \assert($filter instanceof TenantFilter);
        $filter->setParameter('organization_id', $organizationId->toRfc4122(), 'string');
    }
}
