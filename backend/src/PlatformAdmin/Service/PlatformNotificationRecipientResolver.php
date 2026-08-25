<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\MembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Organization\Enum\CompanySizeCategory;
use App\Organization\Enum\VatStatus;
use App\Organization\Repository\FiscalContextRepository;
use App\Organization\Repository\OrganizationRepository;
use App\PlatformAdmin\Enum\PlatformNotificationTargetType;
use App\PlatformAdmin\Http\SendPlatformNotificationRequest;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Résolution des destinataires effectifs de POST /platform-admin/notifications
 * (US-PLATFORMADMIN-004, docs/07-data-model.md section 21 : critères de segment repris de
 * FiscalContext, jamais un champ dupliqué). Fan-out immédiat - une ligne Notification par
 * destinataire effectif, même patron que App\Notification\Service\SendTeamNotificationService
 * (Phase 14).
 */
final readonly class PlatformNotificationRecipientResolver
{
    public function __construct(
        private MembershipRepository $membershipRepository,
        private OrganizationRepository $organizationRepository,
        private FiscalContextRepository $fiscalContextRepository,
        private UserRepository $userRepository,
    ) {
    }

    /** @return array{recipients: list<User>, estimatedRecipientCount: int} */
    public function resolve(SendPlatformNotificationRequest $payload): array
    {
        $recipients = match ($payload->targetType) {
            PlatformNotificationTargetType::USER => $this->resolveUser($payload),
            PlatformNotificationTargetType::ORGANIZATION => $this->resolveOrganization($payload),
            PlatformNotificationTargetType::SEGMENT => $this->resolveSegment($payload),
            PlatformNotificationTargetType::ALL => $this->resolveAll(),
        };

        // Déduplication par id (un utilisateur appartenant à plusieurs organisations d'un
        // même segment/diffusion ne reçoit jamais qu'un seul exemplaire).
        $byId = [];
        foreach ($recipients as $recipient) {
            $byId[$recipient->getId()->toRfc4122()] = $recipient;
        }

        return ['recipients' => array_values($byId), 'estimatedRecipientCount' => count($byId)];
    }

    /** @return list<User> */
    private function resolveUser(SendPlatformNotificationRequest $payload): array
    {
        $targetId = $payload->targetId();
        if (null === $targetId) {
            throw new UnprocessableEntityHttpException('target_id est requis pour target_type = USER.');
        }

        $user = $this->userRepository->find($targetId);
        if (null === $user) {
            throw new UnprocessableEntityHttpException('Cet utilisateur n\'existe pas.');
        }

        return [$user];
    }

    /** @return list<User> */
    private function resolveOrganization(SendPlatformNotificationRequest $payload): array
    {
        $targetId = $payload->targetId();
        if (null === $targetId) {
            throw new UnprocessableEntityHttpException('target_id est requis pour target_type = ORGANIZATION.');
        }

        $organization = $this->organizationRepository->find($targetId);
        if (null === $organization) {
            throw new UnprocessableEntityHttpException('Cette organisation n\'existe pas.');
        }

        return array_map(
            static fn ($membership) => $membership->getUser(),
            $this->membershipRepository->findAllForOrganization($organization->getId()),
        );
    }

    /** @return list<User> */
    private function resolveSegment(SendPlatformNotificationRequest $payload): array
    {
        if (null === $payload->targetCriteria) {
            throw new UnprocessableEntityHttpException('target_criteria est requis pour target_type = SEGMENT.');
        }

        $organizationIds = $this->fiscalContextRepository->findCurrentOrganizationIdsMatching(
            null !== $payload->targetCriteria->vatStatuses
                ? array_map(static fn (string $v) => VatStatus::from($v), $payload->targetCriteria->vatStatuses)
                : null,
            null !== $payload->targetCriteria->companySizeCategories
                ? array_map(static fn (string $v) => CompanySizeCategory::from($v), $payload->targetCriteria->companySizeCategories)
                : null,
        );

        $recipients = [];
        foreach ($organizationIds as $organizationId) {
            foreach ($this->membershipRepository->findAllForOrganization($organizationId) as $membership) {
                $recipients[] = $membership->getUser();
            }
        }

        return $recipients;
    }

    /** @return list<User> */
    private function resolveAll(): array
    {
        return array_map(
            static fn ($membership) => $membership->getUser(),
            $this->membershipRepository->findAllAcrossOrganizations(),
        );
    }
}
