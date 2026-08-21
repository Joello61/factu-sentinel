<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\Membership;
use App\Identity\Entity\User;
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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * DELETE /organizations/current/members/{id} (US-TEAM-003, docs/08-api-specification.md
 * section 25). Le sujet voté est le Membership cible (App\Shared\Security\
 * OrganizationPermissionVoter refuse un ADMIN tentant de retirer l'OWNER) - `#[IsGranted]`
 * ne peut voter que sur un argument déjà résolu par le routeur (l'id brut, pas l'entité),
 * donc vérification impérative ici, après chargement du Membership, plutôt que l'attribut.
 */
final class RemoveMemberController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly AuditLogger $auditLogger,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/v1/organizations/current/members/{id}', name: 'members_remove', methods: ['DELETE'])]
    public function __invoke(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Ce membre n\'existe pas ou n\'est plus disponible.');
        }

        $membership = $this->entityManager->find(Membership::class, $id);
        if (null === $membership) {
            throw new NotFoundHttpException('Ce membre n\'existe pas ou n\'est plus disponible.');
        }

        if (!$this->security->isGranted(OrganizationPermissionVoter::TEAM_REMOVE, $membership)) {
            throw new AccessDeniedException('Retrait de membre non autorisé.');
        }

        $user = $this->security->getUser();
        \assert($user instanceof User);

        $this->auditLogger->record(
            $this->currentOrganizationResolver->getOrganizationId(),
            ActorType::USER,
            $user->getId(),
            EventType::MEMBER_REMOVED,
            'Membership',
            $membership->getId()->toRfc4122(),
            ['user_id' => $membership->getUser()->getId()->toRfc4122(), 'role' => $membership->getRole()->value],
            null,
        );

        $this->entityManager->remove($membership);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
