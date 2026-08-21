<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Identity\Entity\User;
use App\Notification\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PATCH /notifications/{id}/read (docs/08-api-specification.md, section 34). Recherche déjà
 * bornée au destinataire courant (App\Notification\Repository\NotificationRepository::
 * findOneForRecipient) - un OWNER/ADMIN de la même organisation ne peut jamais marquer comme
 * lue une notification adressée à un autre membre, 404 uniforme sinon (même discipline que
 * le reste de l'API pour une ressource non trouvée ou hors périmètre de l'appelant).
 */
final class MarkNotificationReadController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/v1/notifications/{id}/read', name: 'notifications_mark_read', methods: ['PATCH'])]
    public function __invoke(string $id): JsonResponse
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $notification = $this->notificationRepository->findOneForRecipient($id, $user);
        if (null === $notification) {
            throw new NotFoundHttpException('Cette notification n\'existe pas ou n\'est plus disponible.');
        }

        $notification->markRead();
        $this->entityManager->flush();

        return new JsonResponse(['data' => ListNotificationsController::toView($notification)]);
    }
}
