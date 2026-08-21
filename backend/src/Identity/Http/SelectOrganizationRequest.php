<?php

declare(strict_types=1);

namespace App\Identity\Http;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/** POST /auth/select-organization (docs/08-api-specification.md section 9, plan Phase 14). */
final readonly class SelectOrganizationRequest
{
    public function __construct(
        #[SerializedName('organization_id')]
        #[Assert\NotBlank(message: 'organization_id est requis.')]
        #[Assert\Uuid(message: 'organization_id doit être un UUID valide.')]
        public string $organizationId,
    ) {
    }
}
