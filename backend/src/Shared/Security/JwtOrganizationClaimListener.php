<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Identity\Entity\User;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Ajoute le claim `org` (UUID de l'Organization du Membership unique de l'utilisateur) au
 * JWT à sa création — jamais `email_verified` : cette information doit rester lue depuis
 * `GET /users/current`, pas dupliquée dans un token qui n'est pas rafraîchi en temps réel
 * (voir plan Phase 2).
 *
 * Invariant Phase 2 : un User a exactement un Membership actif au MVP
 * (docs/07-data-model.md prévoit 1—N pour une évolution future, non activée ici). Si cet
 * invariant est rompu, on refuse d'émettre un jeton plutôt que de choisir arbitrairement.
 */
final class JwtOrganizationClaimListener
{
    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $memberships = $user->getMemberships();
        if (1 !== count($memberships)) {
            throw new AuthenticatedIdentityWithoutOrganizationException(sprintf(
                'User "%s" has %d Membership(s), expected exactly 1.',
                $user->getUserIdentifier(),
                count($memberships),
            ));
        }

        $data = $event->getData();
        $data['org'] = $memberships->first()->getOrganization()->getId()->toRfc4122();
        $event->setData($data);
    }
}
