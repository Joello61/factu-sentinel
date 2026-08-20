<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }

    /**
     * Phase 7 (docs/06-technical-architecture.md, section 30) a introduit le service
     * "worker" (docker-compose.yml), un second processus Symfony long-lived
     * (`messenger:consume`) partageant le même bind-mount source que "backend" - donc, sans
     * cette surcharge, le même var/cache/dev/ physique. Un cache::clear déclenché côté
     * "backend" (redémarrage, débogage) régénère un nouveau hash de conteneur DI et supprime
     * les fichiers de l'ancien - un worker déjà démarré, qui référence encore l'ancien hash
     * en mémoire, plante alors au traitement du message suivant en tentant de charger un
     * fichier de cache qui vient de disparaître (constaté à l'implémentation de la Phase 7).
     * APP_CACHE_DIR/APP_LOG_DIR (docker-compose.yml, service "worker" uniquement) isolent
     * physiquement le cache du worker de celui de "backend", pour la même raison que
     * plusieurs processus Symfony partageant un système de fichiers ont besoin de caches
     * distincts (vérifié sur la documentation Symfony actuelle au moment de
     * l'implémentation - https://symfony.com/doc/current/configuration/override_dir_structure.html).
     */
    public function getCacheDir(): string
    {
        $override = $_SERVER['APP_CACHE_DIR'] ?? $_ENV['APP_CACHE_DIR'] ?? null;

        return \is_string($override) && '' !== $override
            ? $override.'/'.$this->environment
            : parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        $override = $_SERVER['APP_LOG_DIR'] ?? $_ENV['APP_LOG_DIR'] ?? null;

        return \is_string($override) && '' !== $override
            ? $override
            : parent::getLogDir();
    }
}
