<?php

declare(strict_types=1);

namespace App\Document\Service;

final class UploadedDocumentValidationResult
{
    public function __construct(
        public readonly string $sanitizedFileName,
        public readonly string $checksum,
        public readonly int $fileSize,
    ) {
    }
}
