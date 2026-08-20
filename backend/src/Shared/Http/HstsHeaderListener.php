<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Strict-Transport-Security (docs/10-security-privacy.md, section 47) - traité séparément
 * de App\Shared\Http\SecurityHeadersListener, avec prudence : un navigateur ignore ce
 * header reçu en clair (HTTP), mais une fois honoré sur un vrai domaine HTTPS il modifie
 * durablement le comportement du navigateur pour ce domaine (et ses sous-domaines si
 * "includeSubDomains"). L'environnement actuel est local/Docker, sans domaine de production
 * maîtrisé - désactivé par défaut (HSTS_ENABLED=false, backend/.env), à activer
 * explicitement seulement une fois un domaine HTTPS réel arrêté (Phase 13,
 * docs/12-roadmap.md). "includeSubDomains" et "preload" ne sont jamais activés ici, même
 * quand le header est actif - ces deux options s'ajouteront explicitement en Phase 13, pas
 * par défaut, un domaine de production n'étant pas encore maîtrisé.
 *
 * Sa présence dans le code ne constitue pas une preuve de "HTTPS forcé" pour la checklist
 * de production (docs/10-security-privacy.md, section 68) - ce point reste marqué
 * DEFERRED - Phase 13 - requires hosted infrastructure tant que HSTS_ENABLED n'est pas
 * positionné à "true" sur un environnement réellement servi en HTTPS.
 */
final class HstsHeaderListener implements EventSubscriberInterface
{
    // max-age recommandé (OWASP HTTP Headers Cheat Sheet, vérifié à l'implémentation) :
    // deux ans en production standard - repris tel quel, "includeSubDomains"/"preload"
    // exclus pour la raison ci-dessus.
    private const string HEADER_VALUE = 'max-age=63072000';

    public function __construct(
        private readonly bool $hstsEnabled,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->hstsEnabled || !$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set('Strict-Transport-Security', self::HEADER_VALUE);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }
}
