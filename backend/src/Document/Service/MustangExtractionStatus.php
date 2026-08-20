<?php

declare(strict_types=1);

namespace App\Document\Service;

/**
 * Signal à 3 branches explicites (plan Phase 7, correction demandée) : ne jamais assimiler
 * "pas de XML embarqué" (NO_XML_EMBEDDED, extraction propre, Mustang-CLI se termine avec le
 * code de sortie 0) à "erreur du service" (SERVICE_ERROR, timeout/indisponibilité du
 * conteneur) - ces deux cas produisent des DocumentProcessingFailureReason différents (voir
 * App\Document\MessageHandler\ExtractDocumentContentHandler).
 */
enum MustangExtractionStatus
{
    case XML_FOUND;
    case NO_XML_EMBEDDED;
    case SERVICE_ERROR;
}
