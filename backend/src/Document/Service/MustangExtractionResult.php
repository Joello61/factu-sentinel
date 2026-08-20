<?php

declare(strict_types=1);

namespace App\Document\Service;

final class MustangExtractionResult
{
    private function __construct(
        public readonly MustangExtractionStatus $status,
        public readonly ?string $xml,
    ) {
    }

    public static function xmlFound(string $xml): self
    {
        return new self(MustangExtractionStatus::XML_FOUND, $xml);
    }

    public static function noXmlEmbedded(): self
    {
        return new self(MustangExtractionStatus::NO_XML_EMBEDDED, null);
    }

    public static function serviceError(): self
    {
        return new self(MustangExtractionStatus::SERVICE_ERROR, null);
    }
}
