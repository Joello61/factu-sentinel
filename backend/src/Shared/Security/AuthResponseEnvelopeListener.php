<?php

declare(strict_types=1);

namespace App\Shared\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Les handlers de succès de Lexik/gesdinet répondent `{"token": "..."}` à plat : reformaté
 * ici dans l'enveloppe `{"data": {...}}` du reste du contrat API
 * (docs/08-api-specification.md, section 13), pour que /auth/login et /auth/refresh ne
 * soient pas des exceptions au format de réponse du reste de l'API.
 */
final class AuthResponseEnvelopeListener
{
    #[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $event->setData(['data' => $event->getData()]);
    }
}
