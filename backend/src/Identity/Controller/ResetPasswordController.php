<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Http\ResetPasswordRequestPayload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class ResetPasswordController
{
    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/auth/password/reset', name: 'auth_password_reset', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] ResetPasswordRequestPayload $payload): JsonResponse
    {
        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($payload->token);
        } catch (ResetPasswordExceptionInterface) {
            throw new BadRequestHttpException('Jeton de réinitialisation invalide ou expiré.');
        }

        \assert($user instanceof User);

        $this->resetPasswordHelper->removeResetRequest($payload->token);
        $user->setPassword($this->passwordHasher->hashPassword($user, $payload->password));
        $this->entityManager->flush();

        return new JsonResponse(['data' => ['status' => 'ok']]);
    }
}
