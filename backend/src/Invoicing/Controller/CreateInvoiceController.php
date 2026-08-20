<?php

declare(strict_types=1);

namespace App\Invoicing\Controller;

use App\Invoicing\Http\CreateInvoiceRequest;
use App\Invoicing\Http\InvoiceView;
use App\Invoicing\Service\InvoiceService;
use App\Organization\Repository\OrganizationRepository;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Security\CurrentOrganizationResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /invoices (docs/08-api-specification.md, section 27 ; US-INVOICE-002 : saisie
 * manuelle uniquement en Phase 4, pas d'import de document - docs/12-roadmap.md, Phase 4).
 *
 * N'honore pas Idempotency-Key (plan Phase 4, décision D2) : écart connu et documenté avec
 * CLAUDE.md racine section 11, différé à l'intégration Redis/Messenger de la Phase 7
 * (docs/06-technical-architecture.md, section 12 : la saisie manuelle reste synchrone,
 * aucune vraie raison de câbler Redis avant l'extraction de document).
 *
 * Construit App\Invoicing\Http\CreateInvoiceRequest manuellement (::fromArray) plutôt que
 * via #[MapRequestPayload] : voir le commentaire de cette classe pour la limitation
 * PropertyInfo vérifiée sur ce projet (pas de résolution de type d'élément de tableau de DTO
 * sans phpdocumentor/phpstan installés).
 */
final class CreateInvoiceController
{
    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly OrganizationRepository $organizationRepository,
        private readonly InvoiceService $invoiceService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/v1/invoices', name: 'invoices_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $organization = $this->organizationRepository->find($this->currentOrganizationResolver->getOrganizationId());

        if (null === $organization) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Resolved organization does not exist.');
        }

        $createRequest = CreateInvoiceRequest::fromArray($request->toArray());

        $violations = $this->validator->validate($createRequest);
        if (\count($violations) > 0) {
            throw new ValidationFailedException($createRequest, $violations);
        }

        $invoice = $this->invoiceService->create($organization, $createRequest);

        $response = new JsonResponse(['data' => InvoiceView::fromEntity($invoice)], JsonResponse::HTTP_CREATED);
        $response->headers->set('ETag', sprintf('W/"%d"', $invoice->getVersion()));

        return $response;
    }
}
