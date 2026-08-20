<?php

declare(strict_types=1);

namespace App\Document\Controller;

use App\Document\Http\DocumentView;
use App\Document\Repository\DocumentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * GET /documents/{id} (docs/08-api-specification.md, section 31). Le TenantFilter déjà actif
 * globalement (ADR-004) exclut automatiquement un document d'une autre organisation - 404
 * uniforme, jamais 403 (backend/CLAUDE.md, section 6), qu'il s'agisse d'un document
 * inexistant, cross-tenant, ou logiquement supprimé (App\Document\Repository\
 * DocumentRepository::findActive).
 */
final class GetDocumentController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
    ) {
    }

    #[Route('/api/v1/documents/{id}', name: 'documents_get', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Ce document n\'existe pas ou n\'est plus disponible.');
        }

        $document = $this->documentRepository->findActive(Uuid::fromString($id));
        if (null === $document) {
            throw new NotFoundHttpException('Ce document n\'existe pas ou n\'est plus disponible.');
        }

        return new JsonResponse(['data' => DocumentView::fromEntity($document)]);
    }
}
