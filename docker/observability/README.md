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
services ne passe par Traefik ni n'a de route publique. Seul Grafana (étape 3) est
accessible, par tunnel SSH, sur un port `127.0.0.1` dédié.

```bash
ssh -L 3000:localhost:3000 <utilisateur>@<serveur>
# puis ouvrir http://localhost:3000 dans un navigateur local
```

## Construction par étapes (plan Phase 18) - un signal à la fois

1. **Logs** (Alloy + Loki) - fait, voir ci-dessous.
2. **Métriques** (Prometheus) - fait, voir ci-dessous.
3. **Dashboards et alerting** (Grafana) - fait, voir ci-dessous.
4. **Traces** (OpenTelemetry SDK manuel + Tempo) - fait, voir ci-dessous. **Le critère de
   clôture de la phase (corrélation request_id → log Loki → trace Tempo démontrée sur une
   vraie requête) reste ouvert** - chaque brique est vérifiée séparément avec des appels
   réseau réels, mais la démonstration complète nécessite l'environnement de production
   (voir "Ce qui reste à démontrer après déploiement" ci-dessous).

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

## Étape 3 - Dashboards et alerting (Grafana)

- `grafana/provisioning/datasources/datasources.yaml` : Prometheus (`uid: prometheus`) et
  Loki (`uid: loki`), tout en code, jamais configuré à la main dans l'UI - `uid` fixes pour
  être référençables explicitement depuis les règles d'alerte.
- `grafana/provisioning/dashboards/dashboards.yaml` + `grafana/dashboards/*.json` : trois
  tableaux de bord committés, chacun répondant à une question précise
  (`allowUiUpdates: false` - toute modification passe par le fichier JSON, jamais par un
  clic UI qui serait perdu au prochain déploiement) :
  - **"API et Compliance Engine"** : débit/erreurs/latence HTTP (fourni gratuitement par
    `AppMetrics`, aucune instrumentation à écrire), analyses de conformité par résultat,
    imports de documents et appels Mistral/Mustang par issue, coût IA 24h, connectivité
    Redis/Mustang.
  - **"Infrastructure (hôte)"** : CPU, mémoire, disque, charge système - métriques Alloy de
    l'étape 2.
  - **"Logs"** : vue Loki avec un sélecteur de service et des requêtes déjà écrites
    (erreurs, rafale de 5xx dérivée des logs).
- `grafana/provisioning/alerting/rules.yaml` : cinq règles d'alerte réelles, reprenant la
  grille de sévérité de `docs/10-security-privacy.md` section 37 dans la mesure de ce qui
  est réellement détectable depuis des métriques génériques - **le niveau "Critique" de
  cette grille (violation d'isolation multi-tenant, faille d'authentification exploitée,
  fuite de secret) n'est PAS couvert ici**, ce sont des événements applicatifs qui
  nécessiteraient une instrumentation dédiée, jamais dérivables d'un taux de 4xx/5xx
  générique : rafale de 5xx, taux d'échec de connexion anormal (`action="POST-auth_login_check"`,
  format de label vérifié dans le code du bundle Prometheus), Mustang/Redis injoignables,
  coût IA anormal sur 24h. Seuils marqués "à ajuster" dans le fichier - valeurs de départ,
  jamais calibrées sur un usage réel.
- Service `grafana` (`docker-compose.prod.yml`) : identifiants admin exclusivement par
  variables d'environnement (`GRAFANA_ADMIN_USER`/`GRAFANA_ADMIN_PASSWORD`,
  `.env.prod.example`), inscription et accès anonyme désactivés explicitement.

### Point de contact Telegram - configuration manuelle, une fois

Comme pour Uptime Kuma (`docker/monitoring/README.md`), **le point de contact et la
politique de notification ne sont jamais provisionnés par fichier** - un jeton de bot
Telegram est un secret, et Grafana ne propose aucun mécanisme pour lire un tel champ depuis
un fichier séparé au moment du provisioning (contrairement à `credentials_file` côté
Prometheus) ; le committer en clair dans `rules.yaml` violerait `../CLAUDE.md` section 15.

1. Ouvrir Grafana (tunnel SSH ci-dessus) → Alerting → Contact points → New contact point.
2. Type "Telegram", **BOT API Token** et **Chat ID** : réutiliser le même bot que celui déjà
   configuré pour Uptime Kuma (`docker/monitoring/README.md`) - un seul bot, deux
   consommateurs, jamais un nouveau canal à créer.
3. Alerting → Notification policies → éditer la politique par défaut → Contact point :
   sélectionner celui créé à l'étape 2.
4. Tester : bouton "Test" du contact point, confirmer la réception sur Telegram avant de
   considérer cette étape terminée.

### Vérification effectuée

- Stack complète (Loki, Alloy, Prometheus, Grafana) démarrée localement : les trois
  sections de provisioning (`datasources`, `dashboards`, `alerting`) se chargent sans
  erreur (`logger=provisioning.* ... finished to provision ...`).
- API Grafana : les trois dashboards et les cinq règles d'alerte sont bien enregistrés ;
  `api/datasources/uid/{prometheus,loki}/health` renvoie `OK` pour les deux ; les règles
  d'alerte évaluent avec `health: ok` (aucune erreur de requête).
- Requêtes réelles testées directement contre les datasources via le proxy Grafana : la
  requête CPU hôte renvoie une valeur réelle, la requête LogQL du dashboard "Logs" s'exécute
  sans erreur (0 résultat normal ici - Alloy/Loki/Prometheus/Grafana n'écrivent pas de log
  JSON `level=ERROR`, contrairement au backend en production), le sélecteur de service du
  dashboard "Logs" renvoie les bons noms de conteneurs.

## Étape 4 - Traces (OpenTelemetry SDK manuel + Tempo)

- `open-telemetry/sdk` (1.15.0) + `open-telemetry/exporter-otlp` (1.4.0, OTLP/HTTP en JSON
  - `ContentTypes::JSON`, jamais `application/x-protobuf` - évite l'extension PECL
  `protobuf`, cohérent avec la décision de ne pas alourdir le `Dockerfile` pour ce chantier).
  Jamais le bundle communautaire (bêta) ni l'auto-instrumentation PECL - voir
  `docs/19-observability-architecture.md` pour la décision complète.
- `App\Shared\Observability\Tracer` (nouveau, `backend/src/Shared/Observability/`) : point
  d'entrée unique vers le SDK, même principe que `MetricsRecorder`. Une seule méthode
  générique `trace(string $spanName, callable $callback, array $attributes = [])` -
  `request_id` injecté automatiquement sur chaque span depuis une requête HTTP (jamais
  laissé à la discrétion de l'appelant). `BatchSpanProcessor` + flush explicite sur
  `kernel.terminate` (après l'envoi de la réponse - jamais sur le chemin critique) et sur
  les deux événements de fin de message Messenger (`WorkerMessageHandledEvent`/
  `WorkerMessageFailedEvent`) pour le worker - un minuteur d'arrière-plan ne survivrait pas
  à la fin d'un processus PHP-FPM court-vécu.
- Spans ajoutés aux points réels du pipeline :
  - `compliance_analysis` (`RunComplianceAnalysisService`) - span parent, synchrone.
  - `ai.explain_compliance_finding` / `ai.answer_assistant_question` (les deux endpoints
    IA synchrones) - span parent, avec `mistral.chat_completion` (`MistralProvider`) comme
    enfant.
  - `document_processing` (`ExtractDocumentContentHandler`, **worker Messenger, jamais une
    requête HTTP**) - span parent, avec `mustang.extract`/`mustang.validate`
    (`MustangValidatorClient`) comme enfants. **Aucun `request_id` ici** - ce contexte n'a
    structurellement jamais de requête HTTP active ; `organization_id`/`document_id`
    servent d'attributs de corrélation à la place.
- `tempo.yaml` : mode monolithique (`-target=all`, le défaut - jamais Kafka, réservé au mode
  microservices/scale de Tempo 3.0, vérifié avant de choisir cet outil), stockage
  filesystem, rétention 7 jours. **Bug constaté et corrigé** : Tempo 3.0 a supprimé la
  section `compactor` entièrement - la rétention est désormais
  `overrides.defaults.compaction.block_retention`, jamais plus une config dédiée (constaté
  via l'erreur réelle `field compactor not found in type app.Config`, jamais deviné).
- Datasource Tempo ajoutée à Grafana + champ dérivé sur le datasource Loki
  (`{.request_id="${__value.raw}"}`, une recherche TraceQL par attribut - `request_id` est
  un attribut de span applicatif, jamais l'identifiant de trace natif OpenTelemetry, donc
  jamais une résolution directe d'identifiant).

### Vérification effectuée - et ce qui reste à démontrer après déploiement

**Vérifié avec des appels réseau réels, pas supposé** :
- Un span envoyé depuis le vrai conteneur `backend` vers un vrai Tempo (même réseau Docker
  que la stack de développement réelle, pas un projet Compose isolé) : parents/enfants
  correctement imbriqués, `request_id` présent sur chaque span, retrouvé par une recherche
  TraceQL `{.request_id="..."}` - exactement le mécanisme utilisé par le champ dérivé Grafana.
- Une vraie requête HTTP authentifiée (`POST /api/v1/assistant/questions`, jusqu'au bout,
  jusqu'à l'appel réel à `MistralProvider::complete()`) déclenche bien les spans
  `ai.answer_assistant_question`/`mistral.chat_completion` - `MISTRAL_API_KEY` étant vide en
  développement, l'appel échoue (503) mais le chemin tracé est identique en succès comme en
  échec (`Tracer::trace()` enregistre et transmet le span dans les deux cas).
- Le format JSON du handler de production et l'injection de `request_id` par
  `RequestContextProcessor` (étape 1) restent corrects, vérifiés directement.

**Non démontré en développement, à faire une fois déployé** : la corrélation complète en
un seul geste (chercher un `request_id` dans Loki, cliquer le champ dérivé, arriver sur la
trace Tempo correspondante) n'a pas pu être vérifiée en local. Cause identifiée, pas un
doute : `config/packages/monolog.yaml` envoie les logs vers un **fichier**
(`%kernel.logs_dir%/dev.log`) en environnement `dev`, jamais vers `stdout` - un choix
délibéré et déjà documenté (étape 1, "format plus verbeux en dev"), mais qui signifie
qu'Alloy ne voit jamais ces lignes en local, seulement les logs d'accès bruts de PHP-FPM.
Seul l'environnement `prod` (utilisé réellement en production, `docker-compose.prod.yml`,
`APP_ENV: prod`) envoie du JSON structuré sur `stderr`. **Procédure à exécuter une fois en
production, avec preuve à conserver dans `docs/19-observability-architecture.md`** :
1. Faire une vraie requête authentifiée (ex. `POST /assistant/questions`).
2. Relever `X-Request-ID` dans la réponse.
3. Dashboard "Logs" (Grafana) : chercher ce `request_id`, confirmer que la ligne JSON existe.
4. Cliquer le champ dérivé "Voir la trace Tempo" sur cette ligne, confirmer l'arrivée sur la
   trace correspondante avec la décomposition `ai.*`/`mistral.*` (ou `document_processing`/
   `mustang.*` pour un import de document) visible.
5. Seulement à ce moment, considérer le critère de clôture de la Phase 18 satisfait.
