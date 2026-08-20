<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Headers de sécurité (docs/10-security-privacy.md, section 47), même patron que
 * App\Shared\Http\RequestIdListener. L'API ne sert jamais de HTML directement (toutes les
 * réponses sont du JSON, jamais rendu par un navigateur) - X-Frame-Options et CSP ont donc
 * une portée réduite ici selon l'OWASP HTTP Headers Cheat Sheet (vérifié : "might be
 * meaningless in the response of a REST API that returns content that is not going to be
 * rendered"), mais restent posés en défense en profondeur, conformément à la décision déjà
 * actée dans le tableau de la section 47, sans coût ni risque de régression pour un client
 * JSON.
 *
 * Strict-Transport-Security volontairement absent d'ici (jamais posé, même désactivé par
 * défaut) : voir HstsHeaderListener, qui le gère séparément et sous condition explicite
 * (HSTS_ENABLED) - ce header modifie durablement le comportement du navigateur pour le
 * domaine une fois honoré en HTTPS, contrairement aux headers ci-dessous qui sont sans
 * effet persistant.
 */
final class SecurityHeadersListener implements EventSubscriberInterface
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=(), usb=()');
        $headers->set('X-Frame-Options', 'DENY');
        // "default-src 'none'" : aucune page HTML n'est servie par cette API, aucune source
        // n'a besoin d'être autorisée (docs/10-security-privacy.md, section 47).
        $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }
}
