<?php

declare(strict_types=1);

namespace App\AI\Service;

use App\AI\Repository\AiCallLogEntryRepository;

final readonly class AiUsageReader implements AiUsageReaderInterface
{
    public function __construct(
        private AiCallLogEntryRepository $aiCallLogEntryRepository,
    ) {
    }

    public function getUsageLast24Hours(): array
    {
        return $this->aiCallLogEntryRepository->aggregateUsageSince(new \DateTimeImmutable('-24 hours'));
    }
}
