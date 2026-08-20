<?php

declare(strict_types=1);

namespace App\Document\Controller;

use App\Document\Service\UploadDocumentService;
use App\Invoicing\Repository\InvoiceRepository;
use App\Organization\Repository\OrganizationRepository;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Security\CurrentOrganizationResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * POST /documents (docs/08-api-specification.md, section 31 ; US-INVOICE-001).
 * `multipart/form-data` : champ "file" (fichier binaire), champ "invoice_id" (obligatoire -
 * plan Phase 7, décision 1 corrigée : jamais optionnel dans cette phase, contrairement à la
 * spécification générique du modèle de données).
 *
 * Idempotency-Key requis et honorée (ferme l'écart D2 pour ce nouvel endpoint - contrairement
 * à POST /invoices, corrigé séparément par App\Invoicing\Controller\CreateInvoiceController).
 */
final class CreateDocumentController
{
    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly OrganizationRepository $organizationRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly UploadDocumentService $uploadDocumentService,
    ) {
    }

    #[Route('/api/v1/documents', name: 'documents_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if (null === $idempotencyKey || '' === trim($idempotencyKey)) {
            throw new BadRequestHttpException('L\'en-tête Idempotency-Key est requis pour importer un document.');
        }

        $invoiceId = $request->request->get('invoice_id');
        if (!\is_string($invoiceId) || !Uuid::isValid($invoiceId)) {
            throw new UnprocessableEntityHttpException('Le champ invoice_id est requis et doit désigner une facture existante.');
        }

        $file = $request->files->get('file');
        if (null === $file) {
            throw new UnprocessableEntityHttpException('Aucun fichier n\'a été transmis.');
        }

        $invoice = $this->invoiceRepository->find(Uuid::fromString($invoiceId));
        if (null === $invoice) {
            throw new NotFoundHttpException('Cette facture n\'existe pas ou n\'est plus disponible.');
        }

        $organization = $this->organizationRepository->find($this->currentOrganizationResolver->getOrganizationId());
        if (null === $organization) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Resolved organization does not exist.');
        }

        $result = $this->uploadDocumentService->upload($organization, $invoice, $file, $idempotencyKey);

        return new JsonResponse($result['body'], $result['status']);
    }
}
