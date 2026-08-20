<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Controller;

use App\Compliance\Engine\Enum\ComplianceResult;
use App\Compliance\Engine\Http\ComplianceAnalysisHistoryView;
use App\Compliance\Engine\Repository\ComplianceAnalysisRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /compliance-analyses (docs/08-api-specification.md, section 29 bis ; US-HISTORY-001) :
 * historique organisation-wide, toutes factures confondues -- distinct de
 * GET /invoices/{id}/compliance-analyses (App\Compliance\Engine\Controller\
 * ListComplianceAnalysesController), déjà scopé à une seule facture. Anciens et nouveaux
 * résultats tous deux consultables, jamais écrasés (US-COMPLIANCE-006). Filtrage par
 * attribut (global_result, from, to), pagination page/per_page (docs/08-api-specification.md,
 * section 40-41), même bornage que ListInvoicesController/ListComplianceAnalysesController.
 */
final class ListComplianceAnalysisHistoryController
{
    private const int DEFAULT_PER_PAGE = 20;
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private readonly ComplianceAnalysisRepository $complianceAnalysisRepository,
    ) {
    }

    #[Route('/api/v1/compliance-analyses', name: 'compliance_analyses_history', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('per_page', self::DEFAULT_PER_PAGE)));

        $globalResultParam = $request->query->get('global_result');
        $globalResult = null !== $globalResultParam && ComplianceResult::tryFrom((string) $globalResultParam) instanceof ComplianceResult
            ? ComplianceResult::from((string) $globalResultParam)
            : null;

        $from = self::parseDate($request->query->get('from'));
        $to = self::parseDate($request->query->get('to'));

        $result = $this->complianceAnalysisRepository->paginateForOrganization($globalResult, $from, $to, $page, $perPage);

        return new JsonResponse([
            'data' => array_map(ComplianceAnalysisHistoryView::fromEntity(...), $result['items']),
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

    /**
     * Filtre "date" de docs/07-data-model.md (section 44) : borne triggeredAt à un jour
     * entier (Y-m-d), silencieusement ignorée si absente ou mal formée -- jamais une erreur
     * 400 sur un filtre optionnel invalide (même tolérance que le filtre status de
     * App\Invoicing\Controller\ListInvoicesController).
     */
    private static function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date ? $date : null;
    }
}
