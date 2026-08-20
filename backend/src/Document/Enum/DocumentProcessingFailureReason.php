<?php

declare(strict_types=1);

namespace App\Document\Enum;

/**
 * Contrat figé entre App\Document\MessageHandler\ExtractDocumentContentHandler et le
 * frontend (plan Phase 7) : DocumentProcessingRecord::status = FAILED ne doit jamais être un
 * texte libre indifférencié - "non pris en charge" (FORMAT_NOT_SUPPORTED) n'est pas la même
 * catégorie qu'une vraie erreur technique, le frontend ne doit jamais les présenter avec le
 * même message (docs/11-frontend-design-system.md, section 37 : "Technical error" jamais
 * confondu avec un jugement sur le fichier lui-même).
 */
enum DocumentProcessingFailureReason: string
{
    /** XML UBL/CII détecté, traitement non couvert par cette phase - fichier potentiellement valide. */
    case FORMAT_NOT_SUPPORTED = 'FORMAT_NOT_SUPPORTED';

    /** Timeout/erreur du conteneur Mustang (ADR-008) - jamais un jugement sur le fichier. */
    case MUSTANG_UNAVAILABLE = 'MUSTANG_UNAVAILABLE';

    /** Mustang a traité le fichier et l'a jugé structurellement invalide (Factur-X/XML malformé). */
    case MUSTANG_VALIDATION_FAILED = 'MUSTANG_VALIDATION_FAILED';

    /** Fichier illisible/corrompu avant même d'atteindre Mustang. */
    case INVALID_DOCUMENT = 'INVALID_DOCUMENT';

    /**
     * Ne devrait normalement jamais atteindre ce handler (rejet synchrone déjà effectué par
     * App\Document\Service\UploadedDocumentValidator, SEC-DOC-001) - conservé pour
     * traçabilité si un cas limite passait la validation synchrone.
     */
    case SECURITY_REJECTED = 'SECURITY_REJECTED';
}
