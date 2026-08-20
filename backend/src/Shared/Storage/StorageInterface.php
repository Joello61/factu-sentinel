<?php

declare(strict_types=1);

namespace App\Shared\Storage;

/**
 * Abstraction de stockage documentaire (docs/06-technical-architecture.md, section 21 ;
 * ADR-005 : toute dépendance externe passe par une interface interne). Système de fichiers
 * local pour le MVP (`LocalFilesystemStorage`), migrable vers un stockage objet distant sans
 * changement de cette interface ni du modèle de données (`Document.storage_reference` reste
 * un identifiant opaque, docs/07-data-model.md section 13).
 *
 * La référence retournée par `store()` est générée par l'implémentation elle-même, jamais
 * dérivée d'un nom de fichier fourni par l'appelant (protection path traversal,
 * docs/10-security-privacy.md section 22) - aucune méthode de cette interface n'accepte de
 * nom de fichier utilisateur.
 */
interface StorageInterface
{
    /** @return string référence opaque, à conserver par l'appelant pour retrieve()/delete() */
    public function store(string $content): string;

    /** @throws \RuntimeException si la référence est introuvable */
    public function retrieve(string $reference): string;

    public function delete(string $reference): void;
}
