<?php

declare(strict_types=1);

namespace App\Document\Enum;

/**
 * docs/07-data-model.md, section 13. Périmètre de traitement réel de la Phase 7
 * (docs/12-roadmap.md, plan Phase 7, décision produit 2) : seuls PDF_SIMPLE et FACTURX ont
 * un chemin de traitement complet - UBL/CII sont détectés (fichier XML valide) mais leur
 * traitement s'arrête à DocumentProcessingRecord::FAILED
 * (DocumentProcessingFailureReason::FORMAT_NOT_SUPPORTED), jamais un résultat de conformité.
 *
 * INCONNU est une valeur purement défensive : aucun chemin du pipeline Phase 7
 * (App\Document\MessageHandler\ExtractDocumentContentHandler) ne peut la produire - un PDF
 * valide devient toujours PDF_SIMPLE ou FACTURX, un XML valide devient toujours UBL ou CII,
 * et tout le reste est déjà rejeté à l'upload par
 * App\Document\Service\UploadedDocumentValidator (jamais persisté comme Document). Prévue ici
 * pour fidélité au modèle de données documenté, comme InvoiceSource::DOCUMENT_IMPORTE l'a été
 * en Phase 4 avant d'être produite en Phase 7.
 */
enum DocumentFileFormat: string
{
    case PDF_SIMPLE = 'PDF_SIMPLE';
    case FACTURX = 'FACTURX';
    case UBL = 'UBL';
    case CII = 'CII';
    case INCONNU = 'INCONNU';
}
