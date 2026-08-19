<?php

declare(strict_types=1);

namespace App\Invoicing\Controller;

use App\Invoicing\Http\InvoiceView;
use App\Invoicing\Repository\InvoiceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * GET /invoices/{id} (docs/08-api-specification.md, section 27), avec ses lignes. TenantFilter
 * (déjà actif) exclut automatiquement les factures d'une autre organisation : un identifiant
 * invalide, absent ou appartenant à un autre tenant retourne uniformément 404 (jamais 403,
 * backend/CLAUDE.md section 6). Expose l'ETag de verrouillage optimiste (plan Phase 4,
 * décision D3) pour permettre un futur PATCH /invoices/{id}.
 */
final class GetInvoiceController
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
    ) {
    }

    #[Route('/api/v1/invoices/{id}', name: 'invoices_get', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        $invoice = Uuid::isValid($id) ? $this->invoiceRepository->find(Uuid::fromString($id)) : null;

        if (null === $invoice) {
            throw new NotFoundHttpException('Cette facture n\'existe pas ou n\'est plus disponible.');
        }

        $response = new JsonResponse(['data' => InvoiceView::fromEntity($invoice)]);
        $response->headers->set('ETag', sprintf('W/"%d"', $invoice->getVersion()));

        return $response;
    }
}
