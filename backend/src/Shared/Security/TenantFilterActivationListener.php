<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Identity\Entity\User;
use App\Shared\Doctrine\TenantFilter;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Exception\OrganizationMembershipMismatchException;
use App\Shared\Exception\OrganizationSuspendedException;
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
 *
 * Depuis la Phase 14 (DEC-009, un User peut avoir plusieurs Membership) : le claim `org`
 * n'est jamais traité comme une preuve d'appartenance suffisante à lui seul - ce listener
 * revalide, à chaque requête authentifiée, que le Membership correspondant existe
 * réellement pour l'utilisateur porté par le token déjà chargé
 * (App\Identity\Repository\UserRepository::loadUserByIdentifier(), rejoué à chaque requête
 * par le firewall JWT stateless, docs/12-roadmap.md bilan Phase 13). Un membre retiré de
 * l'organisation après l'émission d'un token encore valide (courte durée de vie résiduelle,
 * un JWT signé n'étant pas révocable) est ainsi rejeté dès la requête authentifiée
 * suivante, jamais laissé passer sur la seule foi du claim signé.
 *
 * **Phase 15 (ADR-009)** : `lexik_jwt_authentication.on_jwt_authenticated` est un événement
 * global, dispatché par le même `EventDispatcherInterface` partagé quel que soit le firewall
 * qui authentifie - y compris `platform_admin_jwt.authenticator` sur le firewall
 * `platform_admin` (backend/config/services.yaml). Ce listener doit donc explicitement
 * ignorer tout principal qui n'est pas un App\Identity\Entity\User (early return, jamais une
 * exception) : un App\PlatformAdmin\Entity\PlatformAdministrator n'a structurellement aucun
 * `org` à revalider, et App\Shared\Security\PlatformAdminAuthenticationListener est le seul
 * listener responsable de ce principal. `tenant_filter` reste ainsi **jamais activé** pour
 * une requête `platform_admin` - pas par un mécanisme dédié, mais simplement parce que ce
 * listener (seul point d'activation du filtre) ne s'exécute jamais pour ce principal.
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
        $user = $event->getToken()->getUser();
        if (!$user instanceof User) {
            // App\PlatformAdmin\Entity\PlatformAdministrator (Phase 15) ou tout futur
            // principal non tenant-scoped - jamais cette classe qui en est responsable.
            return;
        }

        $claim = $event->getPayload()['org'] ?? null;

        if (!is_string($claim) || '' === $claim) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Authenticated JWT carries no "org" claim.');
        }

        try {
            $organizationId = Uuid::fromString($claim);
        } catch (\InvalidArgumentException $exception) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Authenticated JWT carries a malformed "org" claim.', previous: $exception);
        }

        $membership = null;
        foreach ($user->getMemberships() as $candidate) {
            if ($candidate->getOrganizationId()->equals($organizationId)) {
                $membership = $candidate;
                break;
            }
        }

        if (null === $membership) {
            throw new OrganizationMembershipMismatchException('No active Membership matches the "org" claim for this user.');
        }

        if ($membership->getOrganization()->isSuspended()) {
            throw new OrganizationSuspendedException('This organization has been suspended by a platform administrator.');
        }

        $request = $this->requestStack->getCurrentRequest();
        $request?->attributes->set(CurrentOrganizationResolver::ATTRIBUTE, $organizationId);
        $request?->attributes->set(CurrentMembershipResolver::ATTRIBUTE, $membership);

        $filter = $this->entityManager->getFilters()->enable('tenant_filter');
        \assert($filter instanceof TenantFilter);
        $filter->setParameter('organization_id', $organizationId->toRfc4122(), 'string');
    }
}
