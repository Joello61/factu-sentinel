<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Identity\Http\InviteMemberRequest;
use App\Identity\Service\InviteMemberService;
use App\Organization\Repository\OrganizationRepository;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Security\CurrentOrganizationResolver;
use App\Shared\Security\OrganizationPermissionVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** POST /organizations/current/invitations (US-TEAM-001, docs/08-api-specification.md section 25). */
final class InviteMemberController
{
    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly OrganizationRepository $organizationRepository,
        private readonly Security $security,
        private readonly InviteMemberService $inviteMemberService,
    ) {
    }

    #[Route('/api/v1/organizations/current/invitations', name: 'invitations_create', methods: ['POST'])]
    #[IsGranted(OrganizationPermissionVoter::TEAM_INVITE)]
    public function __invoke(Request $request, #[MapRequestPayload] InviteMemberRequest $payload): JsonResponse
    {
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if (null === $idempotencyKey || '' === trim($idempotencyKey)) {
            throw new BadRequestHttpException('L\'en-tête Idempotency-Key est requis pour inviter un membre.');
        }

        $organization = $this->organizationRepository->find($this->currentOrganizationResolver->getOrganizationId());
        if (null === $organization) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Resolved organization does not exist.');
        }

        $user = $this->security->getUser();
        \assert($user instanceof User);

        $result = $this->inviteMemberService->invite(
            $organization,
            $user,
            $payload->email,
            Role::from($payload->role),
            $idempotencyKey,
        );

        return new JsonResponse($result['body'], $result['status']);
    }
}
