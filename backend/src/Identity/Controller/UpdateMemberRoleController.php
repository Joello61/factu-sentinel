<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\Membership;
use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Identity\Http\UpdateMemberRoleRequest;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Security\CurrentOrganizationResolver;
use App\Shared\Security\OrganizationPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * PATCH /organizations/current/members/{id} (US-TEAM-002, docs/08-api-specification.md
 * section 25). `team:manage_roles` (Voter) garantit déjà que seul un OWNER atteint ce code
 * (403 sinon) ; la cible étant l'OWNER lui-même est un conflit métier (409), jamais un refus
 * d'autorisation - vérifié ici, séparément du Voter (qui ne connaît pas la cible pour cette
 * permission, voir App\Shared\Security\OrganizationPermissionVoter).
 */
final class UpdateMemberRoleController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly AuditLogger $auditLogger,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/v1/organizations/current/members/{id}', name: 'members_update_role', methods: ['PATCH'])]
    #[IsGranted(OrganizationPermissionVoter::TEAM_MANAGE_ROLES)]
    public function __invoke(string $id, #[MapRequestPayload] UpdateMemberRoleRequest $payload): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Ce membre n\'existe pas ou n\'est plus disponible.');
        }

        $membership = $this->entityManager->find(Membership::class, $id);
        if (null === $membership) {
            throw new NotFoundHttpException('Ce membre n\'existe pas ou n\'est plus disponible.');
        }

        if (Role::OWNER === $membership->getRole()) {
            throw new ConflictHttpException('Le rôle du propriétaire de l\'organisation ne peut pas être modifié.');
        }

        $previousRole = $membership->getRole();
        $newRole = Role::from($payload->role);
        $membership->setRole($newRole);

        $user = $this->security->getUser();
        \assert($user instanceof User);

        $this->auditLogger->record(
            $this->currentOrganizationResolver->getOrganizationId(),
            ActorType::USER,
            $user->getId(),
            EventType::MEMBER_ROLE_CHANGED,
            'Membership',
            $membership->getId()->toRfc4122(),
            ['role' => $previousRole->value],
            ['role' => $newRole->value],
        );

        $this->entityManager->flush();

        return new JsonResponse(['data' => ListMembersController::toView($membership)]);
    }
}
