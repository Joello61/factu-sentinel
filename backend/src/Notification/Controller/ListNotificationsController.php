<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Identity\Entity\User;
use App\Notification\Entity\Notification;
use App\Notification\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /notifications (docs/08-api-specification.md, section 34 : "paginé" - pagination
 * page/per_page ajoutée après une revue de complétude, même patron que
 * App\Customer\Controller\ListCustomersController). Filtré par `recipientUser = utilisateur
 * courant` (App\Notification\Repository\NotificationRepository) - jamais par la seule
 * organisation courante, voir l'invariant documenté sur App\Notification\Entity\Notification.
 */
final class ListNotificationsController
{
    private const int DEFAULT_PER_PAGE = 20;
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/v1/notifications', name: 'notifications_list', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('per_page', self::DEFAULT_PER_PAGE)));

        $result = $this->notificationRepository->paginate($user, $page, $perPage);

        return new JsonResponse([
            'data' => array_map(self::toView(...), $result['items']),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_count' => $result['totalCount'],
                    'total_pages' => 0 === $result['totalCount'] ? 0 : (int) ceil($result['totalCount'] / $perPage),
                ],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public static function toView(Notification $notification): array
    {
        return [
            'id' => $notification->getId()->toRfc4122(),
            'notification_type' => $notification->getNotificationType()->value,
            'sender_type' => $notification->getSenderType()->value,
            'message' => $notification->getMessage(),
            'channel' => $notification->getChannel()->value,
            'status' => $notification->getStatus()->value,
            'scheduled_for' => $notification->getScheduledFor()->format(\DateTimeInterface::ATOM),
            'sent_at' => $notification->getSentAt()?->format(\DateTimeInterface::ATOM),
            'read_at' => $notification->getReadAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
