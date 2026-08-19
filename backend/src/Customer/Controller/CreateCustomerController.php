<?php

declare(strict_types=1);

namespace App\Customer\Controller;

use App\Customer\Http\CreateCustomerRequest;
use App\Customer\Http\CustomerView;
use App\Customer\Service\CustomerService;
use App\Organization\Repository\OrganizationRepository;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Security\CurrentOrganizationResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /customers (docs/08-api-specification.md, section 26 ; US-CUSTOMER-001/002).
 *
 * siren manquant pour un PROFESSIONNEL_FRANCAIS n'est jamais une erreur ici (plan Phase 4,
 * décision D1) : App\Customer\Http\CreateCustomerRequest ne porte aucune contrainte
 * conditionnelle sur ce champ. Le contrat documenté ("422 si siren manquant",
 * docs/08-api-specification.md section 26) contredisait US-CUSTOMER-002 et
 * BR-COMPLIANCE-003/ADR-002 (CLAUDE.md racine section 9) : corrigé dans le cadre de cette
 * tâche plutôt qu'implémenté tel quel.
 */
final class CreateCustomerController
{
    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly OrganizationRepository $organizationRepository,
        private readonly CustomerService $customerService,
    ) {
    }

    #[Route('/api/v1/customers', name: 'customers_create', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateCustomerRequest $payload): JsonResponse
    {
        $organization = $this->organizationRepository->find($this->currentOrganizationResolver->getOrganizationId());

        if (null === $organization) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Resolved organization does not exist.');
        }

        $customer = $this->customerService->create($organization, $payload);

        return new JsonResponse(['data' => CustomerView::fromEntity($customer)], JsonResponse::HTTP_CREATED);
    }
}
