<?php

declare(strict_types=1);

namespace App\Document\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Message asynchrone (config/packages/messenger.yaml, transport "async") déclenché par
 * App\Document\Service\UploadDocumentService après création d'un Document +
 * DocumentProcessingRecord(UPLOADED). Traité par
 * App\Document\MessageHandler\ExtractDocumentContentHandler.
 *
 * Porte l'identifiant du DocumentProcessingRecord cible (pas seulement documentId) - la
 * tentative à laquelle ce message correspond est donc explicite, pas déduite (plan Phase 7,
 * invariant d'idempotence). Porte aussi organizationId explicitement : un message qui
 * "oublierait" son tenant est une brèche potentielle (backend/CLAUDE.md, section 10).
 *
 * Value object immuable, sérialisé/désérialisé par Messenger - jamais de dépendance
 * Doctrine ni de logique ici.
 */
final class ExtractDocumentContentMessage
{
    public function __construct(
        public readonly Uuid $documentId,
        public readonly Uuid $documentProcessingRecordId,
        public readonly Uuid $organizationId,
    ) {
    }
}
