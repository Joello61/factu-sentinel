<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Contrat d'erreur uniforme (docs/08-api-specification.md, section 14-15) : introduit ici,
 * avec le premier ensemble d'endpoints métier réels (Phase 2) — volontairement non
 * exhaustif, à étendre au fil des phases suivantes plutôt que anticipé en une fois.
 *
 * Rappel structurant : ce contrat ne représente jamais un résultat de conformité, qui
 * n'existe pas encore à ce stade du produit.
 */
final class ApiExceptionListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $throwable = $event->getThrowable();
        $requestId = $event->getRequest()->attributes->get(RequestIdListener::ATTRIBUTE);

        [$status, $code, $message, $details] = $this->resolve($throwable);

        if ($status >= 500) {
            $this->logger->error('Unhandled API exception', [
                'request_id' => $requestId,
                'exception' => $throwable,
            ]);
        }

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => $requestId,
            ],
        ], $status));
    }

    /** @return array{0: int, 1: string, 2: string, 3: list<array{field: string, issue: string}>} */
    private function resolve(\Throwable $throwable): array
    {
        if ($throwable instanceof AuthenticatedIdentityWithoutOrganizationException) {
            // Violation d'invariant interne, jamais un refus d'autorisation ordinaire (403).
            return [500, 'INTERNAL_ERROR', 'Une erreur interne est survenue.', []];
        }

        if ($throwable instanceof AuthenticationException) {
            // Volontairement non spécifique (US-AUTH-002) : ne révèle jamais si c'est
            // l'identifiant ou le mot de passe qui est invalide.
            return [401, 'AUTHENTICATION_FAILED', 'Identifiants invalides.', []];
        }

        // #[MapRequestPayload] enveloppe la ValidationFailedException dans une
        // UnprocessableEntityHttpException (HttpExceptionInterface) : on déballe le cas
        // échéant plutôt que de se contenter du message générique du wrapper.
        $validationFailure = $throwable instanceof ValidationFailedException
            ? $throwable
            : ($throwable->getPrevious() instanceof ValidationFailedException ? $throwable->getPrevious() : null);

        if (null !== $validationFailure) {
            $details = [];
            foreach ($validationFailure->getViolations() as $violation) {
                $details[] = [
                    'field' => (string) $violation->getPropertyPath(),
                    'issue' => (string) $violation->getMessage(),
                ];
            }

            return [422, 'VALIDATION_ERROR', 'La requête contient des champs invalides.', $details];
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return [$throwable->getStatusCode(), 'HTTP_ERROR', $throwable->getMessage(), []];
        }

        // Jamais de détail d'implémentation exposé au client (10-security-privacy.md, section 18).
        return [500, 'INTERNAL_ERROR', 'Une erreur interne est survenue.', []];
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }
}
