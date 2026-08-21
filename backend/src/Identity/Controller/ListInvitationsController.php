<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\Invitation;
use App\Identity\Service\InviteMemberService;
use App\Shared\Security\OrganizationPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET /organizations/current/invitations (docs/08-api-specification.md, section 25) - liste
 * les invitations en attente de l'organisation courante (TenantFilter, ADR-004 - jamais un
 * filtre applicatif redondant ici).
 */
final class ListInvitationsController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/organizations/current/invitations', name: 'invitations_list', methods: ['GET'])]
    #[IsGranted(OrganizationPermissionVoter::TEAM_READ)]
    public function __invoke(): JsonResponse
    {
        $invitations = $this->entityManager->getRepository(Invitation::class)
            ->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->setParameter('status', \App\Identity\Enum\InvitationStatus::PENDING)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'data' => array_map(InviteMemberService::toView(...), $invitations),
        ]);
    }
}
