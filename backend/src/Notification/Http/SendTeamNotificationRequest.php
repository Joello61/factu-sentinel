<?php

declare(strict_types=1);

namespace App\Notification\Http;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /organizations/current/notifications (US-NOTIFICATION-003,
 * docs/08-api-specification.md section 34). `recipient_ids` : liste explicite choisie par
 * l'expéditeur - jamais un ciblage implicite "tous les membres" (voir App\Notification\Enum\
 * TargetType). L'appartenance de chaque id à l'organisation courante est vérifiée par
 * App\Notification\Service\SendTeamNotificationService, pas par ce DTO (422 générique,
 * jamais 403 par destinataire - docs/08-api-specification.md section 34).
 */
final readonly class SendTeamNotificationRequest
{
    /** @param list<string> $recipientIds */
    public function __construct(
        #[SerializedName('recipient_ids')]
        #[Assert\NotBlank(message: 'recipient_ids ne peut pas être vide.')]
        #[Assert\Count(min: 1, minMessage: 'recipient_ids ne peut pas être vide.')]
        #[Assert\All([new Assert\Uuid(message: 'Chaque destinataire doit être un UUID valide.')])]
        public array $recipientIds,
        #[Assert\NotBlank(message: 'Le message est requis.')]
        #[Assert\Length(max: 1000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.')]
        public string $message,
    ) {
    }
}
