<?php

declare(strict_types=1);

namespace App\Document\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Validation technique avant tout traitement (SEC-DOC-001, docs/10-security-privacy.md
 * section 22 ; docs/09-test-strategy.md section 19). Ordre volontaire : taille d'abord (la
 * moins coûteuse), puis inspection du contenu réel (magic bytes via finfo, jamais
 * l'extension déclarée ni le Content-Type du client, tous deux non fiables par construction).
 *
 * Ne détermine ici que PDF vs XML (les deux seuls types acceptés à l'upload) : la
 * distinction plus fine PDF_SIMPLE/FACTURX (Mustang) et UBL/CII (sniff de namespace) est
 * volontairement différée à App\Document\MessageHandler\ExtractDocumentContentHandler
 * (traitement asynchrone, docs/06-technical-architecture.md section 11-12) - cette classe ne
 * fait que décider si le fichier peut entrer dans le pipeline, jamais quel sous-format
 * précis il représente.
 */
final class UploadedDocumentValidator
{
    private const array ACCEPTED_MIME_TYPES = [
        'application/pdf',
        'application/xml',
        'text/xml',
    ];

    public function __construct(
        private readonly int $documentMaxSizeBytes,
    ) {
    }

    public function validate(UploadedFile $file): UploadedDocumentValidationResult
    {
        // PHP peut rejeter l'upload avant même que ce code s'exécute (upload_max_filesize/
        // post_max_size, ini indépendant de $this->documentMaxSizeBytes) - sans cette
        // vérification explicite, un fichier trop volumineux au sens de PHP atterrit dans le
        // "vide ou illisible" ci-dessous (422) au lieu du 413 attendu, quel que soit
        // l'environnement d'exécution réel.
        if (!$file->isValid()) {
            if (\in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
                throw new HttpException(413, \sprintf(
                    'Le fichier dépasse la taille maximale autorisée (%d Mo).',
                    (int) ($this->documentMaxSizeBytes / 1024 / 1024),
                ));
            }

            throw new UnprocessableEntityHttpException('Le fichier importé est vide ou illisible.');
        }

        $size = $file->getSize();
        if (null === $size || $size <= 0) {
            throw new UnprocessableEntityHttpException('Le fichier importé est vide ou illisible.');
        }

        if ($size > $this->documentMaxSizeBytes) {
            throw new HttpException(413, \sprintf(
                'Le fichier dépasse la taille maximale autorisée (%d Mo).',
                (int) ($this->documentMaxSizeBytes / 1024 / 1024),
            ));
        }

        $realPath = $file->getRealPath();
        if (false === $realPath) {
            throw new UnprocessableEntityHttpException('Le fichier importé est vide ou illisible.');
        }

        // finfo inspecte le contenu réel du fichier, jamais l'extension déclarée ni le
        // Content-Type envoyé par le client (tous deux falsifiables) - SEC-DOC-001.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMimeType = $finfo->file($realPath);

        if (false === $detectedMimeType || !\in_array($detectedMimeType, self::ACCEPTED_MIME_TYPES, true)) {
            throw new UnprocessableEntityHttpException(
                'Ce format de fichier n\'est pas pris en charge. Formats acceptés : PDF, Factur-X, XML (UBL/CII).',
            );
        }

        $content = file_get_contents($realPath);
        if (false === $content) {
            throw new UnprocessableEntityHttpException('Le fichier importé est vide ou illisible.');
        }

        return new UploadedDocumentValidationResult(
            $this->sanitizeFileName($file->getClientOriginalName()),
            hash('sha256', $content),
            $size,
        );
    }

    /**
     * Neutralise le nom de fichier avant tout usage (docs/10-security-privacy.md, section
     * 22) : jamais utilisé pour construire un chemin de stockage
     * (App\Shared\Storage\LocalFilesystemStorage génère sa propre référence opaque), mais
     * conservé pour l'affichage - séquences de traversée de répertoire et caractères de
     * contrôle supprimés, nom de base uniquement.
     */
    private function sanitizeFileName(string $originalName): string
    {
        $baseName = basename(str_replace('\\', '/', $originalName));
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $baseName) ?? '';
        $sanitized = trim($sanitized);

        return '' === $sanitized ? 'document' : mb_substr($sanitized, 0, 255);
    }
}
