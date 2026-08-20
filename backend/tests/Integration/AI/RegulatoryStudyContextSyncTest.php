<?php

declare(strict_types=1);

namespace App\Tests\Integration\AI;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fou demandé en revue du plan Phase 8 : backend/src/AI/Resources/
 * regulatory_study_context.md doit toujours être une copie byte-pour-byte de
 * docs/02-regulatory-study.md (backend/bin/sync-regulatory-context.php), jamais une
 * seconde source de vérité qui pourrait diverger silencieusement. S'appuie sur le dépôt
 * complet monté/checkouté (vrai en local et en CI), jamais sur l'image de production, qui
 * ne contient pas docs/ (contexte de build Docker limité à ./backend).
 */
final class RegulatoryStudyContextSyncTest extends TestCase
{
    public function testBundledResourceMatchesSourceDocument(): void
    {
        $sourcePath = __DIR__.'/../../../../docs/02-regulatory-study.md';
        $bundledPath = __DIR__.'/../../../src/AI/Resources/regulatory_study_context.md';

        if (!is_file($sourcePath)) {
            // Attendu dans le conteneur "backend" (docker-compose.yml, contexte de build
            // limité à ./backend : docs/ n'y est jamais monté, voir plan Phase 8, section
            // "Regulatory content packaging") - ce test ne peut alors rien comparer. Il
            // s'exécute réellement en CI (.github/workflows/lint.yml, checkout complet du
            // dépôt) et sur un lancement direct depuis l'hôte (php backend/bin/phpunit,
            // dépôt complet également visible), qui restent les deux façons de le faire
            // vraiment vérifier la synchronisation.
            self::markTestSkipped("docs/02-regulatory-study.md n'est pas visible depuis cet environnement d'exécution (attendu dans le conteneur backend).");
        }

        self::assertFileExists($bundledPath, 'Lancez php backend/bin/sync-regulatory-context.php.');

        $sourceHash = hash_file('sha256', $sourcePath);
        $bundledHash = hash_file('sha256', $bundledPath);

        self::assertSame(
            $sourceHash,
            $bundledHash,
            'backend/src/AI/Resources/regulatory_study_context.md a divergé de docs/02-regulatory-study.md. '
            .'Relancez php backend/bin/sync-regulatory-context.php et committez le résultat.',
        );
    }
}
