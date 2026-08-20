#!/bin/sh
set -e

# Le worker Messenger (Phase 7, docker-compose.yml) partage la même image et le même
# bind-mount source que "backend" (docs/06-technical-architecture.md, section 30 : "Symfony
# (API + worker)") : il ne refait jamais lui-même composer install/génération de clés JWT
# (dont il n'a de toute façon pas besoin, aucune route HTTP ici) - il attend simplement que
# "backend" ait fini sa propre installation (docker/entrypoint-dev.sh), sur le var/cache/
# partagé. Un simple sleep-poll, pas flock (verrouillage de fichier entre conteneurs
# distincts non fiable sur ce type de bind-mount, constaté à l'implémentation de la Phase 7).
READY_MARKER=/app/var/.backend-ready
ELAPSED=0
TIMEOUT=120

while [ ! -f "$READY_MARKER" ]; do
    if [ "$ELAPSED" -ge "$TIMEOUT" ]; then
        echo "worker: timed out waiting for $READY_MARKER (backend setup never completed)" >&2
        exit 1
    fi
    sleep 1
    ELAPSED=$((ELAPSED + 1))
done

exec "$@"
