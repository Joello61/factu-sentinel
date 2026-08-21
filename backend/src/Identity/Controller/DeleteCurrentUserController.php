<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Http\DeleteUserRequest;
use App\Identity\Service\DeleteCurrentUserService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/** DELETE /users/current (US-SETTINGS-002, docs/08-api-specification.md section 23, voir plan Phase 13). */
final class DeleteCurrentUserController
{
    public function __construct(
        private readonly Security $security,
        private readonly DeleteCurrentUserService $deleteCurrentUserService,
    ) {
    }

    #[Route('/api/v1/users/current', name: 'users_current_delete', methods: ['DELETE'])]
    public function __invoke(#[MapRequestPayload] DeleteUserRequest $payload): JsonResponse
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $this->deleteCurrentUserService->delete($user, $payload);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
