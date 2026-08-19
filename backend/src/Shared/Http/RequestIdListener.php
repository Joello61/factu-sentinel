<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * Corrèle chaque requête à un identifiant (docs/08-api-specification.md, section 49) :
 * repris de l'en-tête X-Request-ID s'il est fourni, généré sinon. Utilisé par
 * ApiExceptionListener pour les logs et renvoyé au client pour le support/débogage.
 */
final class RequestIdListener implements EventSubscriberInterface
{
    public const string ATTRIBUTE = 'request_id';
    private const string HEADER = 'X-Request-ID';

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId = $event->getRequest()->headers->get(self::HEADER) ?: Uuid::v7()->toRfc4122();
        $event->getRequest()->attributes->set(self::ATTRIBUTE, $requestId);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if (is_string($requestId)) {
            $event->getResponse()->headers->set(self::HEADER, $requestId);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 2048],
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }
}
