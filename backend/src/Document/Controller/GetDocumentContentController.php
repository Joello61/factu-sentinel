<?php

declare(strict_types=1);

namespace App\Document\Controller;

use App\Document\Repository\DocumentRepository;
use App\Shared\Storage\StorageInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * GET /documents/{id}/content (docs/08-api-specification.md, section 31 ;
 * docs/10-security-privacy.md, section 24 : route authentifiée revalidant l'appartenance
 * tenant à chaque appel, jamais un lien permanent/prévisible ni un chemin Nginx direct).
 *
 * Servi systématiquement en `application/octet-stream` + `Content-Disposition: attachment`
 * (jamais le Content-Type du fichier réel) : empêche tout MIME-sniffing du navigateur sur un
 * contenu importé par l'utilisateur, indépendamment du fait que le fichier ait déjà été
 * validé à l'upload (défense en profondeur, docs/10-security-privacy.md section 3).
 */
final class GetDocumentContentController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('/api/v1/documents/{id}/content', name: 'documents_get_content', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Ce document n\'existe pas ou n\'est plus disponible.');
        }

        $document = $this->documentRepository->findActive(Uuid::fromString($id));
        if (null === $document) {
            throw new NotFoundHttpException('Ce document n\'existe pas ou n\'est plus disponible.');
        }

        try {
            $content = $this->storage->retrieve($document->getStorageReference());
        } catch (\RuntimeException) {
            throw new NotFoundHttpException('Ce document n\'existe pas ou n\'est plus disponible.');
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $document->getFileName(),
        ));

        return $response;
    }
}
