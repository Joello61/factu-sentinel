<?php

declare(strict_types=1);

namespace App\Document\Controller;

use App\Document\Repository\DocumentRepository;
use App\Document\Service\DeleteDocumentService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** DELETE /documents/{id} (docs/08-api-specification.md, section 31 ; US-DOCUMENT-002). */
final class DeleteDocumentController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly DeleteDocumentService $deleteDocumentService,
    ) {
    }

    #[Route('/api/v1/documents/{id}', name: 'documents_delete', methods: ['DELETE'])]
    public function __invoke(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Ce document n\'existe pas ou n\'est plus disponible.');
        }

        $document = $this->documentRepository->findActive(Uuid::fromString($id));
        if (null === $document) {
            throw new NotFoundHttpException('Ce document n\'existe pas ou n\'est plus disponible.');
        }

        $this->deleteDocumentService->delete($document);

        return new Response(status: 204);
    }
}
