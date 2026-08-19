<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Controller;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Http\ComplianceFindingView;
use App\Compliance\Engine\Repository\ComplianceFindingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * GET /compliance-analyses/{id}/findings (docs/08-api-specification.md, section 29 ;
 * US-COMPLIANCE-003/004). La vérification tenant a déjà eu lieu via la résolution de
 * l'analyse parente (ComplianceAnalysis tenant-scoped) : jamais de filtre direct sur
 * ComplianceFinding, comme InvoiceLine via Invoice.
 */
final class ListComplianceFindingsController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ComplianceFindingRepository $complianceFindingRepository,
    ) {
    }

    #[Route('/api/v1/compliance-analyses/{id}/findings', name: 'compliance_analyses_findings', methods: ['GET'])]
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

        return new JsonResponse(['data' => array_map(ComplianceFindingView::fromEntity(...), $findings)]);
    }
}
