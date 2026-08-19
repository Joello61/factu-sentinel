<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Controller;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Repository\ComplianceAnalysisRepository;
use App\Invoicing\Repository\InvoiceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * GET /invoices/{id}/compliance-analyses (docs/08-api-specification.md, section 29 ;
 * US-COMPLIANCE-006) : anciens et nouveaux résultats tous deux consultables, jamais
 * écrasés. Pagination page/per_page, même pattern que
 * App\Invoicing\Controller\ListInvoicesController.
 */
final class ListComplianceAnalysesController
{
    private const int DEFAULT_PER_PAGE = 20;
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ComplianceAnalysisRepository $complianceAnalysisRepository,
    ) {
    }

    #[Route('/api/v1/invoices/{id}/compliance-analyses', name: 'compliance_analyses_list', methods: ['GET'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Cette facture n\'existe pas ou n\'est plus disponible.');
        }

        $invoiceId = Uuid::fromString($id);
        $invoice = $this->invoiceRepository->find($invoiceId);
        if (null === $invoice) {
            throw new NotFoundHttpException('Cette facture n\'existe pas ou n\'est plus disponible.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('per_page', self::DEFAULT_PER_PAGE)));

        $result = $this->complianceAnalysisRepository->paginateForInvoice($invoiceId, $page, $perPage);

        return new JsonResponse([
            'data' => array_map(
                static fn (ComplianceAnalysis $analysis): array => [
                    'id' => $analysis->getId()->toRfc4122(),
                    'status' => $analysis->getStatus()->value,
                    'global_result' => $analysis->getGlobalResult()?->value,
                    'triggered_at' => $analysis->getTriggeredAt()->format(\DateTimeInterface::ATOM),
                    'completed_at' => $analysis->getCompletedAt()?->format(\DateTimeInterface::ATOM),
                ],
                $result['items'],
            ),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_count' => $result['totalCount'],
                    'total_pages' => 0 === $result['totalCount'] ? 0 : (int) ceil($result['totalCount'] / $perPage),
                ],
            ],
        ]);
    }
}
