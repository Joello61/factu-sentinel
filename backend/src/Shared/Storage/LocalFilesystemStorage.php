<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

/**
 * Implémentation MVP de StorageInterface (docs/06-technical-architecture.md, ADR-007) :
 * système de fichiers local, jamais servi directement par Nginx ni exposé dans un
 * répertoire public (docs/10-security-privacy.md, section 24) - tout accès passe par
 * App\Document\Controller\GetDocumentContentController, qui revalide l'appartenance tenant
 * avant de lire le fichier via retrieve().
 *
 * $basePath (paramètre `storageLocalPath`, config/services.yaml) est injecté depuis
 * STORAGE_LOCAL_PATH (backend/.env) - jamais codé en dur ici.
 */
final class LocalFilesystemStorage implements StorageInterface
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $storageLocalBasePath,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function store(string $content): string
    {
        // Référence opaque générée ici, jamais dérivée d'un nom de fichier fourni par
        // l'appelant (docs/10-security-privacy.md, section 22) : Uuid::v7() garantit
        // l'absence de séquence de traversée de répertoire par construction, pas par
        // validation a posteriori d'une entrée utilisateur.
        $reference = Uuid::v7()->toRfc4122();

        try {
            $this->filesystem->dumpFile($this->pathFor($reference), $content);
        } catch (IOExceptionInterface $exception) {
            throw new \RuntimeException('Unable to store document content.', previous: $exception);
        }

        return $reference;
    }

    public function retrieve(string $reference): string
    {
        $path = $this->pathFor($this->validatedReference($reference));

        if (!$this->filesystem->exists($path)) {
            throw new \RuntimeException(\sprintf('Storage reference "%s" not found.', $reference));
        }

        $content = file_get_contents($path);
        if (false === $content) {
            throw new \RuntimeException(\sprintf('Unable to read storage reference "%s".', $reference));
        }

        return $content;
    }

    public function delete(string $reference): void
    {
        $this->filesystem->remove($this->pathFor($this->validatedReference($reference)));
    }

    /**
     * $reference provient toujours de Document::getStorageReference(), jamais directement
     * d'une entrée HTTP - cette validation est une défense en profondeur, pas la garantie
     * principale (celle-ci vient de store() qui ne génère jamais rien d'autre qu'un UUID).
     */
    private function validatedReference(string $reference): string
    {
        if (!Uuid::isValid($reference)) {
            throw new \RuntimeException('Invalid storage reference.');
        }

        return $reference;
    }

    private function pathFor(string $reference): string
    {
        return \rtrim($this->storageLocalBasePath, '/').'/'.$reference;
    }
}
