<?php

declare(strict_types=1);

namespace App\AI\Service;

/**
 * Charge et met en cache le contenu de Resources/regulatory_study_context.md - copie
 * générée mécaniquement de docs/02-regulatory-study.md (backend/bin/sync-regulatory-context.php),
 * jamais éditée à la main (voir ce script et
 * backend/tests/Integration/AI/RegulatoryStudyContextSyncTest.php, qui garantit qu'elle ne
 * diverge jamais du document source). Nécessaire car docs/ n'est pas copié dans l'image
 * backend (contexte de build Docker limité à ./backend, voir docker-compose.yml) : cette
 * classe ne lit donc jamais docs/ directement au runtime.
 *
 * Le cache est purement un cache mémoire de ce fichier déjà synchronisé - il ne constitue en
 * aucun cas une troisième source de vérité.
 */
final class RegulatoryStudyContext
{
    private const string RESOURCE_PATH = __DIR__.'/../Resources/regulatory_study_context.md';

    private ?string $content = null;

    public function get(): string
    {
        if (null === $this->content) {
            $content = file_get_contents(self::RESOURCE_PATH);
            if (false === $content) {
                throw new \RuntimeException('Regulatory study context resource is missing.');
            }
            $this->content = $content;
        }

        return $this->content;
    }
}
