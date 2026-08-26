<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Service;

use App\Notification\Entity\Notification;
use App\Notification\Enum\Channel;
use App\Notification\Enum\NotificationType;
use App\Notification\Enum\SenderType;
use App\Notification\Enum\TargetType;
use App\PlatformAdmin\Entity\PlatformAdministrator;
use App\PlatformAdmin\Enum\PlatformNotificationTargetType;
use App\PlatformAdmin\Http\SendPlatformNotificationRequest;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Idempotency\Service\IdempotencyStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * US-PLATFORMADMIN-004 (docs/08-api-specification.md, section 38.2). Même patron
 * transactionnel que App\Notification\Service\SendTeamNotificationService (Phase 14) : une
 * ligne Notification par destinataire effectif, transaction + Idempotency-Key, audit.
 *
 * `organization = null` sur chaque ligne créée (docs/07-data-model.md, section 21 : "null
 * pour une notification émise par un PlatformAdministrator, portée cross-tenant") - y compris
 * pour target_type = ORGANIZATION/SEGMENT, où le message est pourtant destiné à un ensemble
 * précis d'organisations : un choix délibéré (voir App\Notification\Entity\Notification,
 * révision Phase 15) plutôt qu'un rattachement par ligne, pour rester fidèle à la sémantique
 * documentée "jamais rattachée à une seule organisation" et pour que la règle de lecture
 * centralisée (App\Notification\Repository\NotificationRepository) reste unique et simple -
 * conséquence assumée : un destinataire voit cette notification quelle que soit
 * l'organisation qu'il consulte, pas seulement celle visée.
 *
 * L'idempotency store est scopé par organization_id (App\Shared\Idempotency\Service\
 * IdempotencyStore, colonne sans contrainte FK) - réutilisé ici avec l'id du
 * PlatformAdministrator appelant comme scope (aucune organisation n'est disponible dans ce
 * contexte), un choix de portée tout aussi valide que par organisation : dédupliquer une
 * requête rejouée par le même acteur avec la même clé.
 */
final readonly class SendPlatformNotificationService
{
    public function __construct(
        private PlatformNotificationRecipientResolver $recipientResolver,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private IdempotencyStore $idempotencyStore,
    ) {
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function send(SendPlatformNotificationRequest $payload, PlatformAdministrator $sender, string $idempotencyKey): array
    {
        return $this->entityManager->wrapInTransaction(
            fn (): array => $this->idempotencyStore->execute(
                $sender->getId(),
                $idempotencyKey,
                fn (): array => $this->doSend($payload, $sender),
            ),
        );
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function doSend(SendPlatformNotificationRequest $payload, PlatformAdministrator $sender): array
    {
        $resolution = $this->recipientResolver->resolve($payload);
        $persistedTargetType = self::toPersistedTargetType($payload->targetType);

        foreach ($resolution['recipients'] as $recipient) {
            $notification = new Notification(
                organization: null,
                recipientUser: $recipient,
                notificationType: NotificationType::MESSAGE_PLATEFORME,
                senderType: SenderType::PLATFORM_ADMIN,
                sender: null,
                targetType: $persistedTargetType,
                message: $payload->message,
                channel: Channel::IN_APP,
                platformAdminSenderId: $sender->getId(),
            );
            $this->entityManager->persist($notification);
        }

        $this->auditLogger->record(
            organizationId: null,
            actorType: ActorType::PLATFORM_ADMIN,
            actorId: $sender->getId(),
            eventType: EventType::PLATFORM_NOTIFICATION_SENT,
            entityType: 'Notification',
            entityId: $sender->getId()->toRfc4122(),
            previousState: null,
            newState: [
                'target_type' => $payload->targetType->value,
                'recipient_count' => $resolution['estimatedRecipientCount'],
            ],
        );

        return [
            'status' => JsonResponse::HTTP_CREATED,
            'body' => ['data' => [
                'sender_type' => SenderType::PLATFORM_ADMIN->value,
                'target_type' => $payload->targetType->value,
                'estimated_recipient_count' => $resolution['estimatedRecipientCount'],
            ]],
        ];
    }

    private static function toPersistedTargetType(PlatformNotificationTargetType $targetType): TargetType
    {
        return match ($targetType) {
            PlatformNotificationTargetType::USER => TargetType::USER,
            PlatformNotificationTargetType::ORGANIZATION => TargetType::ORGANIZATION_MEMBERS,
            PlatformNotificationTargetType::SEGMENT => TargetType::SEGMENT,
            PlatformNotificationTargetType::ALL => TargetType::ALL,
        };
    }
}
