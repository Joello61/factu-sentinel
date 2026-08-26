<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use App\PlatformAdmin\Http\SendPlatformNotificationRequest;
use App\PlatformAdmin\Service\SendPlatformNotificationService;
use App\Shared\Security\CurrentPlatformAdministratorResolver;
use App\Shared\Security\PlatformAdminPermissionVoter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** POST /platform-admin/notifications (docs/08-api-specification.md, section 38.2 ; US-PLATFORMADMIN-004). */
final class SendPlatformNotificationController
{
    public function __construct(
        private readonly SendPlatformNotificationService $sendPlatformNotificationService,
        private readonly CurrentPlatformAdministratorResolver $currentPlatformAdministratorResolver,
    ) {
    }

    #[Route('/api/v1/platform-admin/notifications', name: 'platform_admin_notifications_send', methods: ['POST'])]
    #[IsGranted(PlatformAdminPermissionVoter::NOTIFICATIONS_SEND)]
    public function __invoke(Request $request, #[MapRequestPayload] SendPlatformNotificationRequest $payload): JsonResponse
    {
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if (null === $idempotencyKey || '' === trim($idempotencyKey)) {
            throw new BadRequestHttpException('L\'en-tête Idempotency-Key est requis pour envoyer une notification.');
        }

        $result = $this->sendPlatformNotificationService->send(
            $payload,
            $this->currentPlatformAdministratorResolver->getPlatformAdministrator(),
            $idempotencyKey,
        );

        return new JsonResponse($result['body'], $result['status']);
    }
}
