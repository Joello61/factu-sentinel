<?php

declare(strict_types=1);

namespace App\Customer\Controller;

use App\Customer\Http\CustomerView;
use App\Customer\Repository\CustomerRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * GET /customers/{id} (docs/08-api-specification.md, section 26). TenantFilter (déjà actif)
 * exclut automatiquement les clients d'une autre organisation ; CustomerRepository::findActive
 * exclut en plus les clients soft-deleted. Un identifiant invalide, absent, appartenant à un
 * autre tenant, ou soft-deleted retourne uniformément 404 (jamais 403, backend/CLAUDE.md
 * section 6).
 */
final class GetCustomerController
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
    ) {
    }

    #[Route('/api/v1/customers/{id}', name: 'customers_get', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        $customer = null;

        if (Uuid::isValid($id)) {
            $customer = $this->customerRepository->findActive(Uuid::fromString($id));
        }

        if (null === $customer) {
            throw new NotFoundHttpException('Ce client n\'existe pas ou n\'est plus disponible.');
        }

        return new JsonResponse(['data' => CustomerView::fromEntity($customer)]);
    }
}
