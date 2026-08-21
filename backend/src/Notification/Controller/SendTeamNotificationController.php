<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Identity\Entity\User;
use App\Notification\Http\SendTeamNotificationRequest;
use App\Notification\Service\SendTeamNotificationService;
use App\Organization\Repository\OrganizationRepository;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Security\CurrentOrganizationResolver;
use App\Shared\Security\OrganizationPermissionVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** POST /organizations/current/notifications (US-NOTIFICATION-003, docs/08-api-specification.md section 34). */
final class SendTeamNotificationController
{
    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly OrganizationRepository $organizationRepository,
        private readonly Security $security,
        private readonly SendTeamNotificationService $sendTeamNotificationService,
    ) {
    }

    #[Route('/api/v1/organizations/current/notifications', name: 'notifications_send_team', methods: ['POST'])]
    #[IsGranted(OrganizationPermissionVoter::NOTIFICATION_SEND_TEAM)]
    public function __invoke(Request $request, #[MapRequestPayload] SendTeamNotificationRequest $payload): JsonResponse
    {
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if (null === $idempotencyKey || '' === trim($idempotencyKey)) {
            throw new BadRequestHttpException('L\'en-tête Idempotency-Key est requis pour envoyer une notification.');
        }

        $organization = $this->organizationRepository->find($this->currentOrganizationResolver->getOrganizationId());
        if (null === $organization) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Resolved organization does not exist.');
        }

        $user = $this->security->getUser();
        \assert($user instanceof User);

        $result = $this->sendTeamNotificationService->send(
            $organization,
            $user,
            $payload->recipientIds,
            $payload->message,
            $idempotencyKey,
        );

        return new JsonResponse($result['body'], $result['status']);
    }
}
