<?php

declare(strict_types=1);

namespace App\Customer\Controller;

use App\Customer\Repository\CustomerRepository;
use App\Customer\Service\CustomerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * DELETE /customers/{id} (docs/08-api-specification.md, section 26) : soft delete
 * uniquement (docs/07-data-model.md, section 30) — jamais de suppression physique.
 */
final class DeleteCustomerController
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly CustomerService $customerService,
    ) {
    }

    #[Route('/api/v1/customers/{id}', name: 'customers_delete', methods: ['DELETE'])]
    public function __invoke(string $id): JsonResponse
    {
        $customer = Uuid::isValid($id) ? $this->customerRepository->findActive(Uuid::fromString($id)) : null;

        if (null === $customer) {
            throw new NotFoundHttpException('Ce client n\'existe pas ou n\'est plus disponible.');
        }

        $this->customerService->delete($customer);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
