<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Source fiable de l'état de vérification d'email côté frontend - jamais un claim JWT,
 * qui pourrait être obsolète entre deux rafraîchissements (voir plan Phase 2).
 */
final class CurrentUserController
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    #[Route('/api/v1/users/current', name: 'users_current', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return new JsonResponse([
            'data' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'email_verified_at' => $user->getEmailVerifiedAt()?->format(\DateTimeInterface::ATOM),
                'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }
}
