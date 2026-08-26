<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Repository\ComplianceAnalysisRepository;

final readonly class ComplianceAnalyticsReader implements ComplianceAnalyticsReaderInterface
{
    public function __construct(
        private ComplianceAnalysisRepository $complianceAnalysisRepository,
    ) {
    }

    public function getCompletedAndConformeCounts(): array
    {
        return $this->complianceAnalysisRepository->countCompletedAndConforme();
    }

    public function getCompletedAnalysesSince(\DateTimeImmutable $since): array
    {
        return $this->complianceAnalysisRepository->findTriggeredAtAndResultSince($since);
    }
}
