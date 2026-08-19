<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Shared\Http\RequestIdListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Les réponses d'échec de Lexik (login invalide, JWT absent/invalide/expiré) répondent
 * `{"code": 401, "message": "..."}` à plat : reformaté dans l'enveloppe
 * `{"error": {"code", "message", "details", "request_id"}}` du reste du contrat API
 * (docs/08-api-specification.md, section 14), pour la même raison que
 * AuthResponseEnvelopeListener côté succès. Message toujours non spécifique
 * (US-AUTH-002) : jamais "email inconnu" ni "mot de passe incorrect".
 */
final class AuthFailureEnvelopeListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    #[AsEventListener(event: Events::JWT_INVALID)]
    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $requestId = $this->requestStack->getCurrentRequest()?->attributes->get(RequestIdListener::ATTRIBUTE);

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => 'AUTHENTICATION_FAILED',
                'message' => 'Identifiants invalides.',
                'details' => [],
                'request_id' => $requestId,
            ],
        ], JsonResponse::HTTP_UNAUTHORIZED));
    }
}
