<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Shared\Http\RequestIdListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Les réponses d'échec de Lexik (login invalide, JWT absent/invalide/expiré) répondent
 * `{"code": 401, "message": "..."}` à plat : reformaté dans l'enveloppe
 * `{"error": {"code", "message", "details", "request_id"}}` du reste du contrat API
 * (docs/08-api-specification.md, section 14), pour la même raison que
 * AuthResponseEnvelopeListener côté succès. Message toujours non spécifique
 * (US-AUTH-002) : jamais "email inconnu" ni "mot de passe incorrect".
 *
 * Cas particulier (constaté en Phase 12, docs/10-security-privacy.md section 36) :
 * security.yaml (login_throttling) lève une TooManyLoginAttemptsAuthenticationException,
 * elle aussi une AuthenticationException - sans ce cas distinct, ce listener l'aurait
 * silencieusement écrasée par un 401 générique, rendant le rate limiting de connexion
 * inopérant en pratique bien qu'actif côté Symfony. Distinguer ce cas ne révèle rien sur
 * l'existence d'un compte (US-AUTH-002 reste respecté) : un 429 signale un volume de
 * tentatives, jamais si l'identifiant ou le mot de passe est en cause.
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
        $exception = $event->getException();

        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $retryAfterMinutes = max(1, (int) $exception->getMessageData()['%minutes%']);

            $response = new JsonResponse([
                'error' => [
                    'code' => 'HTTP_ERROR',
                    'message' => 'Trop de tentatives de connexion. Réessayez plus tard.',
                    'details' => [],
                    'request_id' => $requestId,
                ],
            ], JsonResponse::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', (string) ($retryAfterMinutes * 60));

            $event->setResponse($response);

            return;
        }

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
