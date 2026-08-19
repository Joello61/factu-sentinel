<?php

declare(strict_types=1);

namespace App\Customer\Controller;

use App\Customer\Http\CustomerView;
use App\Customer\Repository\CustomerRepository;
use App\Customer\Service\CustomerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * PATCH /customers/{id} (docs/08-api-specification.md, section 26). Ne passe pas par
 * #[MapRequestPayload] pour la même raison que UpdateOrganizationController : la sémantique
 * de fusion partielle exige de distinguer champ absent de champ explicitement null.
 */
final class UpdateCustomerController
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly CustomerService $customerService,
    ) {
    }

    #[Route('/api/v1/customers/{id}', name: 'customers_update', methods: ['PATCH'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $customer = Uuid::isValid($id) ? $this->customerRepository->findActive(Uuid::fromString($id)) : null;

        if (null === $customer) {
            throw new NotFoundHttpException('Ce client n\'existe pas ou n\'est plus disponible.');
        }

        $customer = $this->customerService->update($customer, $request->toArray());

        return new JsonResponse(['data' => CustomerView::fromEntity($customer)]);
    }
}
