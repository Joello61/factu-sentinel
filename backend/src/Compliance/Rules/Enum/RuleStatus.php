<?php

declare(strict_types=1);

namespace App\Compliance\Rules\Enum;

/** docs/07-data-model.md, section 15. */
enum RuleStatus: string
{
    case ACTIVE = 'ACTIVE';
    case RETIRED = 'RETIRED';
}
