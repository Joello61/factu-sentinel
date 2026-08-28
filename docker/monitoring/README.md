# Monitoring - FactuSentinel

Phase 17 (`docs/12-roadmap.md` §41). OVHcloud (hébergeur confirmé, plan Phase 17) n'a pas
d'équivalent natif à Scaleway Cockpit (comparaison hébergeur) : ce volet est donc
auto-hébergé par défaut, volontairement minimal (`docs/12-roadmap.md` §41 : "pas de stack
d'observabilité disproportionnée"), jamais un remplacement complet d'un APM/observabilité
complète.

**Disponibilité et opérationnel restent deux préoccupations distinctes, jamais confondues
dans un seul mécanisme** (plan Phase 17) :

- **Disponibilité** : l'application répond-elle ? Couverte par `/api/health` (public,
  vérifie uniquement PostgreSQL - `backend/src/Shared/Controller/HealthController.php`)
  et les `HEALTHCHECK` Docker natifs de `backend`/`frontend`
  (`backend/Dockerfile`/`frontend/Dockerfile`, liveness faible mais suffisante pour
  l'orchestration Compose). Aucune séparation liveness/readiness supplémentaire n'a été
  ajoutée : l'architecture actuelle (un seul point d'entrée Nginx, aucune répartition de
  charge entre plusieurs instances) ne justifie pas la distinction classique
  (Kubernetes) entre les deux - il n'existe qu'une seule instance vers laquelle router de
  toute façon.
- **Opérationnel** : ce que la disponibilité seule ne prouve jamais - connectivité réelle
  à Redis et Mustang (`GET /platform-admin/health`, authentifié MFA,
  `App\PlatformAdmin\Service\PlatformHealthAggregator`, étendu en Phase 17), jobs
  Messenger en échec définitif (`async_jobs_dead_letter_count`, même endpoint, déjà
  existant depuis la Phase 15), et - hors de portée d'un endpoint authentifié MFA qu'un
  outil de monitoring ne peut pas franchir - les sauvegardes (voir plus bas) et
  l'expiration du certificat TLS (voir plus bas).

## Uptime Kuma - déplacé vers le socle partagé (Phase 19)

Uptime Kuma vivait auparavant dans `docker-compose.prod.yml` (service `monitoring`),
couplé au seul projet Compose de FactuSentinel, avec un port dédié par environnement
(`MONITORING_PORT`). Migré vers le socle partagé du VPS (Phase 19,
`docs/20-observability-infrastructure-migration.md`) : une seule instance désormais,
`github.com/Joello61/infrastructure`, qui surveille tous les projets hébergés sur ce
serveur - accès toujours exclusivement par tunnel SSH, jamais exposé publiquement (voir le
README de ce dépôt pour la procédure et le port réel).

Les moniteurs eux-mêmes (API publique, TCP `postgres`/`redis`/`mustang`/`clamav`, push
sauvegarde quotidienne) restent ceux déjà configurés manuellement dans l'interface Uptime
Kuma - migrés avec le volume de données lors de la bascule (Phase 19), jamais recréés à
zéro. Uptime Kuma ne propose toujours pas de provisioning déclaratif par fichier de
configuration (vérifié le 26/08/2026).

## Ce que ce volet ne couvre pas

`async_jobs_dead_letter_count` reste uniquement visible via `GET /platform-admin/health`
(authentifié, MFA) - un outil de monitoring externe simple ne peut pas franchir ce
parcours d'authentification, donc ce signal reste un contrôle manuel/tableau de bord,
jamais une alerte automatique externe à ce stade. À reconsidérer explicitement si ce
volume devient un problème opérationnel réel, jamais anticipé ici sans besoin démontré.
