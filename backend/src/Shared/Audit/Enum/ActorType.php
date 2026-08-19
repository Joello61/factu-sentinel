<?php

declare(strict_types=1);

namespace App\Shared\Audit\Enum;

/** docs/07-data-model.md, section 20. */
enum ActorType: string
{
    case USER = 'USER';
    case SYSTEM = 'SYSTEM';
}
