<?php

declare(strict_types=1);

namespace App\Invoicing\Controller;

use App\Invoicing\Enum\InvoiceStatus;
use App\Invoicing\Http\InvoiceView;
use App\Invoicing\Repository\InvoiceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * GET /invoices (docs/08-api-specification.md, section 27, 40-41) : pagination page/per_page,
 * filtrage par status et customer_id (section 16).
 */
final class ListInvoicesController
{
    private const int DEFAULT_PER_PAGE = 20;
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
    ) {
    }

    #[Route('/api/v1/invoices', name: 'invoices_list', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('per_page', self::DEFAULT_PER_PAGE)));

        $statusParam = $request->query->get('status');
        $status = null !== $statusParam && InvoiceStatus::tryFrom((string) $statusParam) instanceof InvoiceStatus
            ? InvoiceStatus::from((string) $statusParam)
            : null;

        $customerIdParam = $request->query->get('customer_id');
        $customerId = \is_string($customerIdParam) && Uuid::isValid($customerIdParam) ? Uuid::fromString($customerIdParam) : null;

        $result = $this->invoiceRepository->paginate($status, $customerId, $page, $perPage);

        return new JsonResponse([
            'data' => array_map(InvoiceView::fromEntity(...), $result['items']),
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
