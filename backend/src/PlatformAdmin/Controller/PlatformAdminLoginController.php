<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\PlatformAdmin\Http\PlatformAdminLoginRequest;
use App\PlatformAdmin\Repository\PlatformAdministratorRepository;
use App\PlatformAdmin\Service\PlatformAdminMfaChallengeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * POST /api/v1/platform-admin/auth/login (docs/10-security-privacy.md, section 17 bis ; plan
 * Phase 15). Étape 1/2 de l'authentification : email + mot de passe. Ne renvoie **jamais** de
 * jeton exploitable sur /platform-admin/* à cette étape - seulement un ticket opaque à usage
 * unique et de courte durée (App\PlatformAdmin\Service\PlatformAdminMfaChallengeResolver),
 * consommé par App\PlatformAdmin\Controller\ConfirmPlatformAdminMfaController.
 *
 * Réponse volontairement générique (même message, même statut) pour un email inconnu, un mot
 * de passe invalide ou un compte révoqué - jamais de distinction qui confirmerait l'existence
 * d'un compte (même discipline que l'authentification tenant, US-AUTH-002).
 */
final class PlatformAdminLoginController
{
    public function __construct(
        private readonly PlatformAdministratorRepository $platformAdministratorRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PlatformAdminMfaChallengeResolver $challengeResolver,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.platform_admin_login')]
        private readonly RateLimiterFactory $rateLimiter,
    ) {
    }

    #[Route('/api/v1/platform-admin/auth/login', name: 'platform_admin_auth_login', methods: ['POST'])]
    public function __invoke(Request $request, #[MapRequestPayload] PlatformAdminLoginRequest $payload): JsonResponse
    {
        $limit = $this->rateLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $administrator = $this->platformAdministratorRepository->findOneByEmail($payload->email);
        if (null === $administrator || !$this->passwordHasher->isPasswordValid($administrator, $payload->password)) {
            throw new BadCredentialsException();
        }

        $challenge = $this->challengeResolver->issue($administrator);
        $this->entityManager->flush();

        return new JsonResponse(['data' => [
            'status' => 'mfa_required',
            'mfa_challenge' => $challenge,
        ]]);
    }
}
