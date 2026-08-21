<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\Invitation;
use App\Identity\Entity\User;
use App\Identity\Enum\InvitationStatus;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Security\CurrentOrganizationResolver;
use App\Shared\Security\OrganizationPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * DELETE /organizations/current/invitations/{id} (docs/08-api-specification.md, section 25).
 * TenantFilter (Invitation implémente TenantScopedInterface) garantit déjà qu'une invitation
 * d'une autre organisation ne peut jamais être trouvée ici - 404 uniforme, cohérent avec
 * docs/10-security-privacy.md section 17.
 */
final class RevokeInvitationController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly AuditLogger $auditLogger,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/v1/organizations/current/invitations/{id}', name: 'invitations_revoke', methods: ['DELETE'])]
    #[IsGranted(OrganizationPermissionVoter::TEAM_INVITE)]
    public function __invoke(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Cette invitation n\'existe pas ou n\'est plus disponible.');
        }

        $invitation = $this->entityManager->find(Invitation::class, $id);
        if (null === $invitation || InvitationStatus::PENDING !== $invitation->getStatus()) {
            throw new NotFoundHttpException('Cette invitation n\'existe pas ou n\'est plus disponible.');
        }

        $invitation->markRevoked();

        $user = $this->security->getUser();
        \assert($user instanceof User);

        $this->auditLogger->record(
            $this->currentOrganizationResolver->getOrganizationId(),
            ActorType::USER,
            $user->getId(),
            EventType::MEMBER_INVITATION_REVOKED,
            'Invitation',
            $invitation->getId()->toRfc4122(),
            ['status' => InvitationStatus::PENDING->value],
            ['status' => InvitationStatus::REVOKED->value],
        );

        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
