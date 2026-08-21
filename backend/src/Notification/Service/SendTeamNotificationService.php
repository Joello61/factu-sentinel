<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\MembershipRepository;
use App\Notification\Entity\Notification;
use App\Notification\Enum\Channel;
use App\Notification\Enum\NotificationType;
use App\Notification\Enum\SenderType;
use App\Notification\Enum\TargetType;
use App\Organization\Entity\Organization;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Idempotency\Service\IdempotencyStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * US-NOTIFICATION-003 (docs/08-api-specification.md, section 34). Résolution immédiate des
 * destinataires : une ligne Notification par destinataire effectif, chacune `target_type =
 * USER` (jamais ORGANIZATION_MEMBERS - voir App\Notification\Enum\TargetType pour la
 * justification complète). La réponse HTTP, elle, décrit l'action d'envoi dans son ensemble
 * (`target_type: ORGANIZATION_MEMBERS`, absence d'un id de ligne canonique puisque
 * plusieurs lignes sont créées) - correction apportée à l'exemple de
 * docs/08-api-specification.md section 34, qui montrait un seul objet Notification en
 * réponse, incompatible avec une résolution immédiate multi-destinataires (voir plan Phase
 * 14, cohérent avec le patron de correction déjà utilisé section 26, décision D1).
 */
final readonly class SendTeamNotificationService
{
    public function __construct(
        private MembershipRepository $membershipRepository,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private IdempotencyStore $idempotencyStore,
    ) {
    }

    /**
     * @param list<string> $recipientIds
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function send(Organization $organization, User $sender, array $recipientIds, string $message, string $idempotencyKey): array
    {
        return $this->entityManager->wrapInTransaction(
            fn (): array => $this->idempotencyStore->execute(
                $organization->getId(),
                $idempotencyKey,
                fn (): array => $this->doSend($organization, $sender, $recipientIds, $message),
            ),
        );
    }

    /** @param list<string> $recipientIds
     * @return array{status: int, body: array<string, mixed>} */
    private function doSend(Organization $organization, User $sender, array $recipientIds, string $message): array
    {
        $memberships = $this->membershipRepository->findAllForCurrentOrganization();
        $membersByUserId = [];
        foreach ($memberships as $membership) {
            $membersByUserId[$membership->getUser()->getId()->toRfc4122()] = $membership->getUser();
        }

        $recipients = [];
        foreach ($recipientIds as $recipientId) {
            $user = $membersByUserId[$recipientId] ?? null;
            if (null === $user) {
                // 422, jamais 403 par destinataire (docs/08-api-specification.md, section
                // 34) : un id hors de l'organisation courante est une erreur de requête,
                // pas une tentative d'accès à refuser individuellement.
                throw new UnprocessableEntityHttpException(sprintf(
                    'Le destinataire "%s" n\'appartient pas à cette organisation.',
                    $recipientId,
                ));
            }
            $recipients[$recipientId] = $user;
        }

        foreach ($recipients as $recipient) {
            $notification = new Notification(
                $organization,
                $recipient,
                NotificationType::MESSAGE_ORGANISATION,
                SenderType::ORGANIZATION_OWNER,
                $sender,
                TargetType::USER,
                $message,
                Channel::IN_APP,
            );
            $this->entityManager->persist($notification);
        }

        $this->auditLogger->record(
            $organization->getId(),
            ActorType::USER,
            $sender->getId(),
            EventType::TEAM_NOTIFICATION_SENT,
            'Notification',
            $organization->getId()->toRfc4122(),
            null,
            ['recipient_count' => count($recipients)],
        );

        return [
            'status' => JsonResponse::HTTP_CREATED,
            'body' => ['data' => [
                'sender_type' => SenderType::ORGANIZATION_OWNER->value,
                'target_type' => TargetType::ORGANIZATION_MEMBERS->value,
                'status' => 'sent',
                'recipient_count' => count($recipients),
            ]],
        ];
    }
}
