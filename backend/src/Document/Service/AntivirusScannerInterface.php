<?php

declare(strict_types=1);

namespace App\Document\Service;

/**
 * Dépendance externe encapsulée derrière une interface interne (ADR-005), même patron que
 * StructuredDocumentValidatorInterface pour Mustang. Une seule implémentation à ce jour
 * (ClamAvScanner), mais jamais un appel ClamAV dispersé ailleurs dans le domaine métier.
 */
interface AntivirusScannerInterface
{
    /**
     * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException si le
     *         contenu est détecté comme infecté (422 - le fichier lui-même est rejeté)
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException si le service de scan est
     *         indisponible (503 - erreur technique, jamais un contournement silencieux du
     *         scan : politique fail-closed, docs/10-security-privacy.md section 3)
     */
    public function scan(string $content): void;
}
