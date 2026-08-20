<?php

declare(strict_types=1);

namespace App\Document\Http;

use App\Document\Entity\Document;

/**
 * Forme JSON partagée par les controllers Document (docs/08-api-specification.md, section
 * 31), même style que App\Invoicing\Http\InvoiceView.
 */
final class DocumentView
{
    /** @return array<string, mixed> */
    public static function fromEntity(Document $document): array
    {
        $currentRecord = $document->getCurrentProcessingRecord();

        return [
            'id' => $document->getId()->toRfc4122(),
            'invoice_id' => $document->getInvoice()->getId()->toRfc4122(),
            'file_name' => $document->getFileName(),
            'file_format' => $document->getFileFormat()?->value,
            'file_size' => $document->getFileSize(),
            'processing_status' => $document->getProcessingStatus()->value,
            'failure_reason' => $currentRecord?->getFailureReason()?->value,
            // Nommé "suggestions", jamais "extracted_data"/"data" : rappelle explicitement
            // au frontend (et à quiconque consomme cette API) qu'il ne s'agit jamais d'une
            // vérité métier - seulement des valeurs à proposer dans l'Invoice Editor, que
            // l'utilisateur doit confirmer/corriger avant toute écriture réelle sur
            // Invoice/Customer (invariant central du plan Phase 7).
            'suggestions' => $currentRecord?->getExtractedDataSummary(),
            'uploaded_at' => $document->getUploadedAt()->format(\DateTimeInterface::ATOM),
            'status_url' => \sprintf('/api/v1/documents/%s', $document->getId()->toRfc4122()),
        ];
    }
}
