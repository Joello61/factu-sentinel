<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\PlatformAdmin\Http\PlatformAdminMfaVerifyRequest;
use App\PlatformAdmin\Service\PlatformAdminMfaChallengeResolver;
use App\PlatformAdmin\Service\PlatformAdminMfaService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * POST /api/v1/platform-admin/auth/mfa/verify (docs/10-security-privacy.md, section 17 bis ;
 * plan Phase 15). Étape 2/2 : consomme le ticket émis par
 * App\PlatformAdmin\Controller\PlatformAdminLoginController, vérifie le code TOTP, confirme
 * l'enrôlement MFA au tout premier succès (App\PlatformAdmin\Entity\
 * PlatformAdministrator::$totpConfirmedAt), puis émet le JWT complet.
 *
 * Réponse volontairement générique pour un ticket invalide/expiré/déjà consommé ou un code
 * TOTP incorrect - jamais de distinction (même discipline que PlatformAdminLoginController).
 * Le secret TOTP et le code soumis ne sont **jamais** journalisés, ici ou ailleurs (audit :
 * uniquement le résultat succès/échec implicite via le statut HTTP, jamais de tentative
 * échouée journalisée avec le code).
 */
final class ConfirmPlatformAdminMfaController
{
    public function __construct(
        private readonly PlatformAdminMfaChallengeResolver $challengeResolver,
        private readonly PlatformAdminMfaService $mfaService,
        private readonly AuditLogger $auditLogger,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'platform_admin_jwt.manager')]
        private readonly JWTTokenManagerInterface $jwtManager,
        #[Autowire(service: 'platform_admin_jwt.success_handler')]
        private readonly AuthenticationSuccessHandler $successHandler,
        #[Autowire(service: 'limiter.platform_admin_mfa_verify')]
        private readonly RateLimiterFactory $rateLimiter,
    ) {
    }

    #[Route('/api/v1/platform-admin/auth/mfa/verify', name: 'platform_admin_auth_mfa_verify', methods: ['POST'])]
    public function __invoke(Request $request, #[MapRequestPayload] PlatformAdminMfaVerifyRequest $payload): Response
    {
        $limit = $this->rateLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $challenge = $this->challengeResolver->resolve($payload->mfaChallenge);
        if (null === $challenge) {
            throw new BadCredentialsException();
        }

        $administrator = $challenge->getPlatformAdministrator();
        $plainSecret = $this->mfaService->decrypt($administrator->getTotpSecret());

        if (!$this->mfaService->verifyCode($plainSecret, $payload->code)) {
            throw new BadCredentialsException();
        }

        $challenge->markConsumed();

        if (!$administrator->isMfaConfirmed()) {
            $administrator->confirmMfa();
        }

        $this->auditLogger->record(
            organizationId: null,
            actorType: ActorType::PLATFORM_ADMIN,
            actorId: $administrator->getId(),
            eventType: EventType::PLATFORM_ADMIN_LOGIN,
            entityType: 'PlatformAdministrator',
            entityId: $administrator->getId()->toRfc4122(),
        );

        $this->entityManager->flush();

        $jwt = $this->jwtManager->create($administrator);

        return $this->successHandler->handleAuthenticationSuccess($administrator, $jwt);
    }
}
