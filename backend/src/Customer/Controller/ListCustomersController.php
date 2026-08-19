<?php

declare(strict_types=1);

namespace App\Customer\Controller;

use App\Customer\Enum\CustomerType;
use App\Customer\Http\CustomerView;
use App\Customer\Repository\CustomerRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /customers (docs/08-api-specification.md, section 26, 40-41) : pagination page/per_page,
 * filtrage par customer_type, recherche par nom/SIREN (section 16).
 */
final class ListCustomersController
{
    private const int DEFAULT_PER_PAGE = 20;
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private readonly CustomerRepository $customerRepository,
    ) {
    }

    #[Route('/api/v1/customers', name: 'customers_list', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('per_page', self::DEFAULT_PER_PAGE)));

        $customerTypeParam = $request->query->get('customer_type');
        $customerType = null !== $customerTypeParam && CustomerType::tryFrom((string) $customerTypeParam) instanceof CustomerType
            ? CustomerType::from((string) $customerTypeParam)
            : null;

        $search = $request->query->get('search');

        $result = $this->customerRepository->paginate(
            $customerType,
            null !== $search ? (string) $search : null,
            $page,
            $perPage,
        );

        return new JsonResponse([
            'data' => array_map(CustomerView::fromEntity(...), $result['items']),
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
