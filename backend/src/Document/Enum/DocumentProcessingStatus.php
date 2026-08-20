<?php

declare(strict_types=1);

namespace App\Document\Enum;

/** docs/07-data-model.md, section 14. */
enum DocumentProcessingStatus: string
{
    case UPLOADED = 'UPLOADED';
    case PROCESSING = 'PROCESSING';
    case PARSED = 'PARSED';
    case VALIDATED = 'VALIDATED';
    case FAILED = 'FAILED';
}
