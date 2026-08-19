<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Non bloquant pour le reste du compte (docs/08-api-specification.md, section 7) : ne
 * fait que marquer email_verified_at, ne modifie ni l'accès de base ni la session.
 */
final class VerifyEmailController
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/auth/verify-email/{id}', name: 'auth_verify_email', methods: ['GET'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        try {
            $userId = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException();
        }

        $user = $this->userRepository->find($userId);
        if (null === $user) {
            throw new NotFoundHttpException();
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, $user->getId()->toRfc4122(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface) {
            throw new BadRequestHttpException('Lien de vérification invalide ou expiré.');
        }

        $user->markEmailAsVerified();
        $this->entityManager->flush();

        return new JsonResponse(['data' => ['email_verified' => true]]);
    }
}
