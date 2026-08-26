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

## Uptime Kuma (`docker-compose.prod.yml`, service `monitoring`)

Auto-hébergé (`louislam/uptime-kuma`), jamais exposé publiquement - un tableau de bord de
supervision révèle la topologie interne du service et n'a d'utilité que pour l'opérateur,
jamais pour un utilisateur final. Publié uniquement sur `127.0.0.1:3001` du serveur :
accès par tunnel SSH.

```bash
ssh -L 3001:localhost:3001 <utilisateur>@<serveur>
# puis ouvrir http://localhost:3001 dans un navigateur local
```

Uptime Kuma ne propose pas de provisioning déclaratif par fichier de configuration
(vérifié le 26/08/2026) - les moniteurs ci-dessous se créent une fois, manuellement, via
son interface, au moment du Bloc B (premier déploiement réel avec un domaine confirmé).

### Moniteurs à créer

| Moniteur | Type | Cible | Ce qu'il prouve |
|---|---|---|---|
| API publique | HTTP(s) | `https://<domaine>/api/health` | Disponibilité de bout en bout (Nginx → backend → PostgreSQL), certificat TLS (activer "Certificate Expiry Notification" - alerte intégrée, aucun script séparé nécessaire) |
| PostgreSQL | TCP Port | `postgres:5432` (réseau Docker interne - Uptime Kuma tourne sur le même réseau) | Le conteneur accepte des connexions |
| Redis | TCP Port | `redis:6379` | Idem |
| Mustang | TCP Port | `mustang:8080` | Idem |
| ClamAV | TCP Port | `clamav:3310` | Idem |
| Sauvegarde quotidienne | Push (passif) | URL générée par Uptime Kuma, renseignée dans `BACKUP_MONITORING_PUSH_URL` (`docker/backup/README.md`) | Détecte une sauvegarde silencieusement absente (cron qui ne se déclenche plus, échec avant le point d'envoi) - configurer l'intervalle attendu à un peu plus de 24h, jamais moins que la fréquence réelle du cron |

### Notifications

À configurer une fois un canal choisi (email, Slack, Discord, autre - Uptime Kuma
supporte plusieurs canaux nativement) - aucun canal n'est retenu par les décisions
produit actuelles, ce choix reste au Bloc B.

## Ce que ce volet ne couvre pas

`async_jobs_dead_letter_count` reste uniquement visible via `GET /platform-admin/health`
(authentifié, MFA) - un outil de monitoring externe simple ne peut pas franchir ce
parcours d'authentification, donc ce signal reste un contrôle manuel/tableau de bord,
jamais une alerte automatique externe à ce stade. À reconsidérer explicitement si ce
volume devient un problème opérationnel réel, jamais anticipé ici sans besoin démontré.
