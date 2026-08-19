<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Http\ForgotPasswordRequest;
use App\Identity\Mailer\PasswordResetMailer;
use App\Identity\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * US-AUTH-003 : réponse toujours générique, que l'email corresponde à un compte ou non
 * (ne jamais révéler l'existence d'un compte).
 */
final class ForgotPasswordController
{
    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly UserRepository $userRepository,
        private readonly PasswordResetMailer $passwordResetMailer,
        #[Autowire(service: 'limiter.password_reset_request')]
        private readonly RateLimiterFactory $rateLimiter,
    ) {
    }

    #[Route('/api/v1/auth/password/forgot', name: 'auth_password_forgot', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] ForgotPasswordRequest $payload, Request $request): JsonResponse
    {
        $limit = $this->rateLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $user = $this->userRepository->findOneByEmail($payload->email);

        if (null !== $user) {
            try {
                $resetToken = $this->resetPasswordHelper->generateResetToken($user);
                $this->passwordResetMailer->send($user, $resetToken);
            } catch (ResetPasswordExceptionInterface) {
                // Volontairement absorbé : ne jamais laisser transparaître un état interne
                // (compte inexistant, jeton déjà demandé récemment, etc.).
            }
        }

        return new JsonResponse(['data' => ['status' => 'ok']]);
    }
}
