<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Http;

use App\PlatformAdmin\Enum\PlatformNotificationTargetType;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

/**
 * POST /platform-admin/notifications (docs/08-api-specification.md, section 38.2 ;
 * US-PLATFORMADMIN-004). La cohérence target_id/target_criteria par rapport à target_type
 * (ex. target_id requis pour USER/ORGANIZATION) est vérifiée par App\PlatformAdmin\Service\
 * PlatformNotificationRecipientResolver, jamais dupliquée ici en règles de validation
 * conditionnelles (422 générique, même discipline que
 * App\Notification\Http\SendTeamNotificationRequest).
 */
final readonly class SendPlatformNotificationRequest
{
    public function __construct(
        #[SerializedName('target_type')]
        #[Assert\NotNull]
        public PlatformNotificationTargetType $targetType,
        #[SerializedName('target_id')]
        #[Assert\Uuid]
        public ?string $targetIdString = null,
        #[SerializedName('target_criteria')]
        #[Assert\Valid]
        public ?SendPlatformNotificationTargetCriteria $targetCriteria = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.')]
        public string $message = '',
    ) {
    }

    public function targetId(): ?Uuid
    {
        return null !== $this->targetIdString ? Uuid::fromString($this->targetIdString) : null;
    }
}
