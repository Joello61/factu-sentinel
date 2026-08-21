<?php

declare(strict_types=1);

namespace App\Identity\Http;

use App\Identity\Enum\Role;
use Symfony\Component\Validator\Constraints as Assert;

/** PATCH /organizations/current/members/{id} (US-TEAM-002, docs/08-api-specification.md section 25). */
final readonly class UpdateMemberRoleRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le rôle est requis.')]
        #[Assert\Choice(callback: [Role::class, 'assignableValues'], message: 'Rôle invalide.')]
        public string $role,
    ) {
    }
}
