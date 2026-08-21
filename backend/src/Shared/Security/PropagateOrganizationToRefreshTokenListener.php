<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Identity\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Reporte l'organisation active résolue par App\Shared\Security\JwtOrganizationClaimListener
 * (login, sélection d'organisation, rotation au refresh) sur le refresh token émis à la même
 * occasion (docs/12-roadmap.md, Phase 14) - condition nécessaire pour que la sélection
 * d'organisation survive à un rafraîchissement de l'access token (donc à un simple
 * rechargement de page), sans quoi App\Shared\Security\JwtOrganizationClaimListener n'aurait
 * jamais de préférence à revalider au refresh suivant et retomberait systématiquement sur le
 * repli par défaut.
 *
 * Priorité négative explicite : doit s'exécuter **après**
 * `gesdinet_jwt_refresh_token.event_listener.attach_refresh_token` (tag
 * `kernel.event_listener` sans priorité déclarée dans le bundle installé - v3.0.0, vérifié
 * dans son `config/services.php` - donc priorité par défaut 0), qui crée/fait tourner le
 * refresh token sur ce même événement Lexik\Bundle\JWTAuthenticationBundle\Events::
 * AUTHENTICATION_SUCCESS. Une priorité positive ou nulle s'exécuterait avant que la nouvelle
 * ligne existe.
 */
final class PropagateOrganizationToRefreshTokenListener
{
    public function __construct(
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'gesdinet_jwt_refresh_token.token_parameter_name')]
        private readonly string $tokenParameterName,
    ) {
    }

    #[AsEventListener(event: Events::AUTHENTICATION_SUCCESS, priority: -10)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $resolvedOrganizationId = $this->requestStack->getCurrentRequest()?->attributes
            ->get(JwtOrganizationClaimListener::RESOLVED_ORGANIZATION_ATTRIBUTE);

        if (!is_string($resolvedOrganizationId) || '' === $resolvedOrganizationId) {
            // Aucune organisation résolue pour ce token (ne devrait jamais arriver pour un
            // App\Identity\Entity\User authentifié - voir JwtOrganizationClaimListener,
            // qui lève plutôt que de laisser ce cas silencieux) : rien à propager.
            return;
        }

        $tokenString = $this->extractIssuedRefreshTokenFromResponse($event);
        if (null === $tokenString) {
            // Pas de cookie de refresh token sur cette réponse (ex. cookie désactivé, ou
            // aucun refresh token émis à cette occasion) - rien à propager.
            return;
        }

        $refreshToken = $this->refreshTokenManager->get($tokenString);
        if (!$refreshToken instanceof RefreshToken) {
            return;
        }

        $refreshToken->setOrganizationId(Uuid::fromString($resolvedOrganizationId));
        $this->entityManager->flush();
    }

    private function extractIssuedRefreshTokenFromResponse(AuthenticationSuccessEvent $event): ?string
    {
        foreach ($event->getResponse()->headers->getCookies() as $cookie) {
            if ($this->tokenParameterName === $cookie->getName()) {
                return $cookie->getValue();
            }
        }

        return null;
    }
}
