<?php

declare(strict_types=1);

namespace App\Identity\Http;

use App\Identity\Enum\Role;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /organizations/current/invitations (US-TEAM-001, docs/08-api-specification.md
 * section 25). `role` restreint à ADMIN/COLLABORATOR (jamais OWNER) - Role::assignableValues().
 */
final readonly class InviteMemberRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'email est requis.')]
        #[Assert\Email(message: 'Adresse email invalide.')]
        public string $email,
        #[Assert\NotBlank(message: 'Le rôle est requis.')]
        #[Assert\Choice(callback: [Role::class, 'assignableValues'], message: 'Rôle invalide.')]
        public string $role,
    ) {
    }
}
