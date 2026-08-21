<?php

declare(strict_types=1);

namespace App\Notification\Enum;

/** docs/07-data-model.md, section 21. */
enum Channel: string
{
    case EMAIL = 'email';
    case IN_APP = 'in_app';
}
