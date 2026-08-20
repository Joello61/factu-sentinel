#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Régénère backend/src/AI/Resources/regulatory_study_context.md comme copie
 * byte-pour-byte de docs/02-regulatory-study.md (Phase 8, plan "Regulatory content
 * packaging"). Ce script ne s'exécute jamais dans l'image de production (docker-compose.yml
 * : contexte de build limité à ./backend, docs/ n'y existe pas) - uniquement en local ou en
 * CI, sur le dépôt complet, avant de committer la ressource régénérée.
 *
 * La ressource générée n'est jamais éditée à la main : backend/tests/Integration/AI/
 * RegulatoryStudyContextSyncTest.php échoue si elle diverge de docs/02-regulatory-study.md,
 * précisément pour empêcher qu'elle devienne une seconde source de vérité oubliée.
 */

$sourcePath = __DIR__.'/../../docs/02-regulatory-study.md';
$destinationPath = __DIR__.'/../src/AI/Resources/regulatory_study_context.md';

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Source introuvable : {$sourcePath}\n");
    exit(1);
}

$content = file_get_contents($sourcePath);
if (false === $content) {
    fwrite(STDERR, "Impossible de lire : {$sourcePath}\n");
    exit(1);
}

if (false === file_put_contents($destinationPath, $content)) {
    fwrite(STDERR, "Impossible d'écrire : {$destinationPath}\n");
    exit(1);
}

fwrite(STDOUT, "Synchronisé : {$destinationPath}\n");
