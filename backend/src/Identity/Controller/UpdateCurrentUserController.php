<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Http\UpdateUserRequest;
use App\Identity\Service\UpdateCurrentUserService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/** PATCH /users/current (US-SETTINGS-001, docs/08-api-specification.md section 23, voir plan Phase 13). */
final class UpdateCurrentUserController
{
    public function __construct(
        private readonly Security $security,
        private readonly UpdateCurrentUserService $updateCurrentUserService,
    ) {
    }

    #[Route('/api/v1/users/current', name: 'users_current_update', methods: ['PATCH'])]
    public function __invoke(#[MapRequestPayload] UpdateUserRequest $payload): JsonResponse
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $updated = $this->updateCurrentUserService->update($user, $payload);

        return new JsonResponse([
            'data' => [
                'id' => $updated->getId()->toRfc4122(),
                'email' => $updated->getEmail(),
                'email_verified_at' => $updated->getEmailVerifiedAt()?->format(\DateTimeInterface::ATOM),
                'created_at' => $updated->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }
}
