# Observabilité - FactuSentinel

Phase 18 (`docs/12-roadmap.md` §41, `docs/19-observability-architecture.md`). Distinct de
`docker/monitoring/` (Uptime Kuma, disponibilité - Phase 17) : ce volet répond à des
questions différentes - "pourquoi cette requête est-elle lente ?", "qu'est-ce que disent
mes logs pour ce `request_id` ?" - jamais un remplacement de la disponibilité, un
complément.

**Décision de portée (27/08/2026)** : objectif d'apprentissage explicitement assumé par
l'éditeur, pas un besoin de production (`docs/12-roadmap.md` §41 révise sur ce point la
décision MVP "pas de stack d'observabilité disproportionnée"). **Production uniquement** -
staging garde ses outils actuels (logs Docker bruts, `/api/health`, Uptime Kuma) ; activé
via `COMPOSE_PROFILES=observability`, jamais présent dans `.env.staging`
(`.env.prod.example`).

Même modèle de sécurité que Uptime Kuma (`docker/monitoring/README.md`) : aucun de ces
services ne passe par Traefik ni n'a de route publique. Seul Grafana (étape 3) sera
accessible, par tunnel SSH, sur un port `127.0.0.1` dédié.

## Construction par étapes (plan Phase 18) - un signal à la fois

1. **Logs** (Alloy + Loki) - fait, voir ci-dessous.
2. **Métriques** (Prometheus) - à venir.
3. **Dashboards** (Grafana) - à venir.
4. **Traces** (OpenTelemetry SDK manuel + Tempo) - à venir, avec pour objectif de démontrer
   concrètement la corrélation requête → `request_id` → log Loki → trace Tempo →
   décomposition Symfony/Mustang/Mistral (critère de clôture de la phase, pas une
   amélioration facultative).

## Étape 1 - Logs (Alloy + Loki)

- `loki-config.yaml` : mode single-binary (`-target=all`, le défaut), stockage filesystem
  sur le volume nommé `loki_data`, rétention 30 jours (`compactor.retention_enabled`),
  télémétrie anonyme vers Grafana Labs désactivée (`analytics.reporting_enabled: false`).
- `alloy-config.alloy` : remplace Promtail (EOL 2 mars 2026). Découvre les conteneurs via
  le socket Docker, **filtré explicitement sur `FACTUSENTINEL_COMPOSE_PROJECT`**
  (`COMPOSE_PROJECT_NAME` de l'environnement courant) - le socket Docker est partagé par
  tout le VPS, staging inclus ; sans ce filtre Alloy remonterait aussi les conteneurs de
  l'autre environnement. Vérifié empiriquement (pas seulement supposé) : filtrer uniquement
  via l'argument `relabel_rules` de `loki.source.docker` réétiquette les entrées mais ne
  les exclut jamais de la collecte - le filtrage réel doit passer par la liste de cibles
  elle-même (`discovery.relabel` alimenté par les vraies cibles, export `.output` consommé
  par `loki.source.docker`, jamais seulement `.rules`).
- Format des logs backend : JSON structuré sur `stderr` en production
  (`backend/config/packages/monolog.yaml`, recette Symfony standard), déjà capturé par
  Docker - `App\Shared\Logging\RequestContextProcessor` enrichit chaque ligne avec
  `request_id` (corrélation avec l'en-tête `X-Request-ID` déjà renvoyé au client,
  `RequestIdListener`) et, quand disponibles, `organization_id`/`user_id` (identifiants
  UUID uniquement - **jamais** d'email, de SIREN, de montant ou de contenu de
  document/prompt IA dans le contexte de log, `docs/10-security-privacy.md` section 35).
- `request_id` n'est **jamais** un label Loki indexé (cardinalité bien trop élevée - un
  par requête) : il reste dans le corps JSON de la ligne, filtrable à la requête via
  `| json | request_id="..."` en LogQL, jamais dans les labels de stream.

### Vérification effectuée

- `docker compose logs alloy` sans erreur après correction du filtrage (voir ci-dessus).
- Requête directe à l'API Loki (`/loki/api/v1/query_range`) confirmant des lignes réelles,
  correctement labellisées (`environment=production`, `service_name=<service compose>`),
  et confirmant qu'aucune ligne d'un autre projet Compose ne remonte.
- Format JSON du handler de production et injection de `request_id` par
  `RequestContextProcessor` vérifiés directement (formatteur Monolog réel, processor réel,
  requête HTTP simulée).
