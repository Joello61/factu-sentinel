<?php

declare(strict_types=1);

namespace App\Invoicing\Controller;

use App\Invoicing\Entity\Invoice;
use App\Invoicing\Http\CreateInvoiceRequest;
use App\Invoicing\Http\InvoiceView;
use App\Invoicing\Service\InvoiceService;
use App\Organization\Repository\OrganizationRepository;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Idempotency\Service\IdempotencyStore;
use App\Shared\Security\CurrentOrganizationResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /invoices (docs/08-api-specification.md, section 27 ; US-INVOICE-002).
 *
 * Idempotency-Key requise et honorée depuis la Phase 7 (App\Shared\Idempotency\Service\
 * IdempotencyStore, store PostgreSQL - ferme l'écart D2 documenté depuis la Phase 4,
 * différé à l'intégration Messenger/Redis, `08-api-specification.md` section 27). Câblée
 * directement ici (pas dans App\Invoicing\Service\InvoiceService) : même précédent que
 * If-Match/ETag sur App\Invoicing\Controller\UpdateInvoiceController - un en-tête HTTP
 * d'idempotence/concurrence reste une préoccupation de ce controller, InvoiceService::create()
 * reste un simple appel synchrone (aucune notion d'idempotence à l'intérieur).
 *
 * L'ETag est reconstruite après coup depuis l'Invoice rechargée (jamais depuis le corps mis
 * en cache seul) : correcte aussi bien sur la première requête que sur un rejeu, la version
 * de l'entité pouvant en principe différer de celle capturée dans le corps caché.
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
        private readonly IdempotencyStore $idempotencyStore,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/invoices', name: 'invoices_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if (null === $idempotencyKey || '' === trim($idempotencyKey)) {
            throw new BadRequestHttpException('L\'en-tête Idempotency-Key est requis pour créer une facture.');
        }

        $organization = $this->organizationRepository->find($this->currentOrganizationResolver->getOrganizationId());

        if (null === $organization) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Resolved organization does not exist.');
        }

        $createRequest = CreateInvoiceRequest::fromArray($request->toArray());

        $violations = $this->validator->validate($createRequest);
        if (\count($violations) > 0) {
            throw new ValidationFailedException($createRequest, $violations);
        }

        $result = $this->entityManager->wrapInTransaction(
            fn (): array => $this->idempotencyStore->execute(
                $organization->getId(),
                $idempotencyKey,
                function () use ($organization, $createRequest): array {
                    $invoice = $this->invoiceService->create($organization, $createRequest);

                    return ['status' => JsonResponse::HTTP_CREATED, 'body' => ['data' => InvoiceView::fromEntity($invoice)]];
                },
            ),
        );

        $response = new JsonResponse($result['body'], $result['status']);

        $invoiceId = $result['body']['data']['id'] ?? null;
        if (\is_string($invoiceId)) {
            $invoice = $this->entityManager->find(Invoice::class, $invoiceId);
            if (null !== $invoice) {
                $response->headers->set('ETag', sprintf('W/"%d"', $invoice->getVersion()));
            }
        }

        return $response;
    }
}
