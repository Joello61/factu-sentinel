<?php

declare(strict_types=1);

namespace App\Organization\Service;

use App\Compliance\Entity\EligibilityDiagnostic;
use App\Organization\Entity\FiscalContext;
use App\Organization\Entity\Organization;

final readonly class ConfigureOrganizationResult
{
    public function __construct(
        public Organization $organization,
        public ?FiscalContext $fiscalContext,
        public ?EligibilityDiagnostic $diagnostic,
    ) {
    }
}
