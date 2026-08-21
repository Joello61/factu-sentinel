<?php

declare(strict_types=1);

namespace App\Notification\Enum;

/** docs/07-data-model.md, section 21. */
enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
