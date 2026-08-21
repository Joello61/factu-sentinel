<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\Membership;
use App\Identity\Repository\MembershipRepository;
use App\Shared\Security\OrganizationPermissionVoter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** GET /organizations/current/members (docs/08-api-specification.md, section 25). */
final class ListMembersController
{
    public function __construct(
        private readonly MembershipRepository $membershipRepository,
    ) {
    }

    #[Route('/api/v1/organizations/current/members', name: 'members_list', methods: ['GET'])]
    #[IsGranted(OrganizationPermissionVoter::TEAM_READ)]
    public function __invoke(): JsonResponse
    {
        $memberships = $this->membershipRepository->findAllForCurrentOrganization();

        return new JsonResponse([
            'data' => array_map(self::toView(...), $memberships),
        ]);
    }

    /** @return array<string, mixed> */
    public static function toView(Membership $membership): array
    {
        return [
            'id' => $membership->getId()->toRfc4122(),
            'user_id' => $membership->getUser()->getId()->toRfc4122(),
            'email' => $membership->getUser()->getEmail(),
            'role' => $membership->getRole()->value,
            'created_at' => $membership->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
