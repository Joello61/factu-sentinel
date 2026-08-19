<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Controller;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Http\ComplianceAnalysisView;
use App\Compliance\Engine\Repository\ComplianceFindingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * GET /compliance-analyses/{id} (docs/08-api-specification.md, section 29). Tenant-scoped
 * via TenantFilter (ComplianceAnalysis implements TenantScopedInterface) : une analyse
 * inexistante ou appartenant à une autre organisation retourne toujours 404, jamais 403
 * (backend/CLAUDE.md, section 6).
 */
final class GetComplianceAnalysisController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ComplianceFindingRepository $complianceFindingRepository,
    ) {
    }

    #[Route('/api/v1/compliance-analyses/{id}', name: 'compliance_analyses_get', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Cette analyse de conformité n\'existe pas ou n\'est plus disponible.');
        }

        $analysis = $this->entityManager->find(ComplianceAnalysis::class, Uuid::fromString($id));
        if (null === $analysis) {
            throw new NotFoundHttpException('Cette analyse de conformité n\'existe pas ou n\'est plus disponible.');
        }

        $findings = $this->complianceFindingRepository->findByAnalysis($analysis->getId());

        return new JsonResponse(['data' => ComplianceAnalysisView::fromEntity($analysis, $findings)]);
    }
}
