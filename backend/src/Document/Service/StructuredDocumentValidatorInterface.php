<?php

declare(strict_types=1);

namespace App\Document\Service;

/**
 * Abstraction du Validator Container (ADR-008, docs/06-technical-architecture.md, section
 * 11/30) : App\Document\MessageHandler\ExtractDocumentContentHandler ne connaît jamais
 * "Mustang" directement, uniquement cette interface (ADR-005). MustangValidatorClient en est
 * l'unique implémentation.
 */
interface StructuredDocumentValidatorInterface
{
    public function extract(string $content): MustangExtractionResult;

    /**
     * @throws \RuntimeException si le conteneur est indisponible ou si Mustang juge le
     *                            fichier structurellement invalide - l'appelant traduit cela
     *                            en DocumentProcessingFailureReason::MUSTANG_UNAVAILABLE ou
     *                            MUSTANG_VALIDATION_FAILED selon le cas, jamais interprété
     *                            comme un résultat de conformité (backend/CLAUDE.md, section 5)
     *
     * @return string rapport de validation XML brut
     */
    public function validate(string $content): string;
}
