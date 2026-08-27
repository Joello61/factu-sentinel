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
2. **Métriques** (Prometheus) - fait, voir ci-dessous.
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

## Étape 2 - Métriques (Prometheus)

- `promphp/prometheus_client_php` (2.15.1) + `artprima/prometheus-metrics-bundle` (1.22.1) -
  recette Flex ignorée (`recipes-contrib`, `extra.symfony.allow-contrib: false` du projet) :
  bundle enregistré à la main (`backend/config/bundles.php`), config écrite à la main
  (`backend/config/packages/artprima_prometheus_metrics.yaml`).
- Stockage Redis (`REDIS_URL` déjà existant, préfixe de clé dédié `metrics` - jamais de
  nouvelle instance Redis, jamais de collision avec le stream Messenger `messages`).
- `GET /api/metrics` (`App\Shared\Controller\GetMetricsController`) protégé par un jeton
  dédié (`METRICS_SCRAPE_TOKEN`) vérifié dans le contrôleur lui-même (`hash_equals`),
  **jamais le firewall JWT tenant** - nécessite un firewall Symfony dédié
  (`security: false` sur `^/api/metrics`, voir "Bug constaté" ci-dessous), pas seulement une
  règle `access_control`.
- Métriques métier ajoutées aux points réels du pipeline (jamais dispersées) :
  `compliance_analyses_total`/`compliance_analysis_duration_seconds` (par résultat global,
  `RunComplianceAnalysisService`), `document_uploads_total` (par issue,
  `UploadDocumentService`), `ai_calls_total`/`ai_call_duration_seconds` (par issue,
  `MistralProvider`), `mustang_calls_total`/`mustang_call_duration_seconds` (par opération -
  `extract`/`validate` - et par issue, `MustangValidatorClient`, ajouté après coup pour
  couvrir les deux dépendances externes de façon symétrique plutôt que seulement Mistral) -
  toutes via `App\Shared\Metrics\MetricsRecorder`, seul point d'entrée vers le registre
  Prometheus (isole promphp du reste du code métier).
- Jauges calculées à chaque scrape en réutilisant directement
  `App\PlatformAdmin\Service\PlatformHealthAggregator` (via une nouvelle
  `PlatformHealthAggregatorInterface`, même pattern d'interface cross-module déjà en place
  pour ce service depuis la Phase 15/16, en sens inverse) - jamais une seconde
  implémentation de ce calcul : taux d'échec du Compliance Engine, jobs en échec définitif,
  volume/coût IA 24h, connectivité Redis/Mustang.
- Métriques hôte (CPU/RAM/disque) : `prometheus.exporter.unix` dans Alloy, avec accès au
  vrai `/proc`/`/sys`/`/` de l'hôte (bind-mounts en lecture seule,
  `docker-compose.prod.yml`) - sans eux, l'exporter ne verrait que la vue cgroup limitée du
  conteneur Alloy lui-même. Comble le trou explicitement documenté dans
  `PlatformHealthAggregator` ("jamais de métrique d'infrastructure hôte... relève du
  monitoring auto-hébergé externe").
- `prometheus.yml` : rétention 15 jours (`--storage.tsdb.retention.time`), jamais le tag
  `latest` (`prom/prometheus:v3.14.0`), pas de port hôte (accès seulement via Grafana,
  étape 3).

### Le jeton de scrape - jamais dans un fichier versionné

`docker/observability/prometheus.yml` référence `credentials_file:
/run/secrets/metrics_scrape_token` plutôt que le jeton en clair (Prometheus ne supporte
aucune substitution de variable d'environnement dans son fichier de config - vérifié avant
d'écrire cette config). Procédure de mise en place sur le serveur, une fois par
environnement :

```bash
mkdir -p docker/observability/secrets
echo -n "<même valeur que METRICS_SCRAPE_TOKEN dans .env.production>" > docker/observability/secrets/metrics_scrape_token
chmod 600 docker/observability/secrets/metrics_scrape_token
```

Ce fichier est explicitement ignoré par Git (`.gitignore`) - jamais committé, jamais
reconstruit automatiquement par le déploiement.

### Bug constaté et corrigé pendant la vérification

Le firewall JWT tenant (`security.yaml`, `jwt: ~`) tente d'authentifier **toute** requête
portant un en-tête `Authorization: Bearer ...`, y compris sur une route marquée
`access_control: PUBLIC_ACCESS` - l'authenticator s'exécute avant que l'access_control ne
soit évalué. Le jeton de scrape (qui n'est pas un JWT) était donc systématiquement rejeté en
401 par le firewall `api`, jamais par `GetMetricsController` lui-même. Corrigé en donnant à
`^/api/metrics` son propre firewall (`security: false`), même patron déjà utilisé pour les
routes `dev` (profiler/wdt) - la règle `access_control` devenue redondante a été retirée.

### Vérification effectuée

- `curl` sans jeton / jeton invalide / jeton correct sur `/api/metrics` via Nginx : `401`,
  `401`, `200` avec un corps Prometheus réel.
- Jauges de connectivité (`redis_reachable`, `mustang_reachable`) confirmées à `1` sur un
  scrape réel, prouvant la réutilisation effective de `PlatformHealthAggregator`.
- Stack complète (Loki, Alloy, Prometheus) démarrée localement : cible
  `factusentinel-backend` configurée, métriques hôte réelles (`node_cpu_seconds_total`,
  `node_memory_*`, `node_filesystem_*`) reçues côté Prometheus via le chemin
  remote-write (`prometheus.exporter.unix` → `prometheus.scrape` → `prometheus.remote_write`
  → `--web.enable-remote-write-receiver`) - constaté que `prometheus.exporter.unix` seul
  n'expose aucun `/metrics` qu'un Prometheus externe pourrait directement scraper.
- Suite PHPUnit complète (290 tests) et PHPStan (niveau du projet) exécutés après
  l'instrumentation des trois services - un seul échec, préexistant et sans rapport avec
  cette étape : `IdempotencyStoreTest::testConcurrentReservationBlocksUntilFirstTransactionCommits`
  mesure un délai réel (sous-processus PHP séparé) contre un seuil fixe de 250ms, et échoue
  de façon reproductible dans cet environnement (mesures 200-243ms) - composant non touché
  par cette étape, hors périmètre de cette phase.
