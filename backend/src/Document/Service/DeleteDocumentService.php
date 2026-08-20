<?php

declare(strict_types=1);

namespace App\Document\Service;

use App\Document\Entity\Document;
use App\Identity\Entity\User;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

/**
 * Régime de suppression mixte (docs/07-data-model.md, section 30 ; US-DOCUMENT-002) :
 * fichier physique et données extraites sensibles supprimés, mais la ligne Document et son
 * historique de DocumentProcessingRecord restent (suppression logique, `deletedAt`) -
 * l'audit et les ComplianceFinding déjà produits (qui référencent Document.file_format en
 * valeur figée, jamais Document lui-même) ne sont jamais affectés.
 */
final class DeleteDocumentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StorageInterface $storage,
        private readonly AuditLogger $auditLogger,
        private readonly Security $security,
    ) {
    }

    public function delete(Document $document): void
    {
        $this->entityManager->wrapInTransaction(function () use ($document): void {
            $this->storage->delete($document->getStorageReference());

            $document->getCurrentProcessingRecord()?->clearExtractedDataSummary();
            $document->markDeleted();

            $this->auditLogger->record(
                $document->getOrganizationId(),
                ActorType::USER,
                $this->currentActorId(),
                EventType::DOCUMENT_DELETED,
                'Document',
                $document->getId()->toRfc4122(),
                null,
                null,
            );
        });
    }

    private function currentActorId(): ?Uuid
    {
        $currentUser = $this->security->getUser();

        return $currentUser instanceof User ? $currentUser->getId() : null;
    }
}
