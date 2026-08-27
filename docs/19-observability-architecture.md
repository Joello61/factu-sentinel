# Architecture d'observabilité - Phase 18

> Ce document détaille les choix techniques de la Phase 18 (`docs/12-roadmap.md` §41) et
> journalise les étapes réellement franchies, au fur et à mesure - jamais rédigé par
> anticipation d'une étape non encore livrée.

## Contexte et portée

Phase 17 a fermé la roadmap MVP avec une décision explicite : "pas de stack
d'observabilité disproportionnée" (`docs/12-roadmap.md` §41), Uptime Kuma suffisant pour
la disponibilité. Cette décision reste valable *pour un besoin de production* - l'objectif
de la Phase 18 est différent et explicitement assumé par l'éditeur : **apprendre à
instrumenter une vraie API** (métriques, logs centralisés, traces distribuées), pas
combler un manque de production.

**Portée : production uniquement.** Staging garde ses outils actuels (logs Docker, logs
Symfony, `/api/health`, Uptime Kuma) - pas la stack complète. Les deux environnements
tournent sur le même VPS OVHcloud (4 vCPU/8 Go) ; doubler la stack d'observabilité aurait
été une mauvaise optimisation pour un objectif d'apprentissage.

**Ordre de construction, un signal à la fois, mesuré avant de passer au suivant** :
1. Alloy + Loki (logs)
2. Prometheus (métriques)
3. Grafana (dashboards)
4. OpenTelemetry + Tempo (traces) - en dernier, le plus délicat

## Décisions techniques actées (recherche du 26-27/08/2026)

- **Promtail est EOL** (2 mars 2026, plus aucun correctif) - remplacé par **Grafana
  Alloy**, le collecteur unifié recommandé par Grafana Labs aujourd'hui.
- **Le bundle communautaire `friendsofopentelemetry/opentelemetry-bundle` est
  explicitement en stade "Development"/bêta** (vérifié sur son dépôt GitHub et Packagist)
  - en conflit avec la règle du projet interdisant les dépendances bêta/expérimentales
  (`../CLAUDE.md` section 5). **Décision : ne pas l'utiliser.** À la place (étape 4) : le
  SDK PHP officiel `open-telemetry/sdk` (stable) + `open-telemetry/exporter-otlp` (stable,
  OTLP/HTTP - évite l'extension PECL gRPC), avec de l'instrumentation **manuelle**, pas
  d'auto-instrumentation via l'extension PECL `ext-opentelemetry`. Choix aussi le plus
  cohérent avec l'objectif d'apprentissage - écrire soi-même les spans enseigne davantage
  qu'un paquet qui instrumente tout automatiquement, et évite d'ajouter une extension C au
  `Dockerfile` de production pour ce premier chantier. L'auto-instrumentation officielle
  (`open-telemetry/opentelemetry-auto-symfony`, celle-ci réellement stable) reste une
  amélioration future possible une fois l'approche manuelle éprouvée.
- **Tempo en mode monolithique (`-target=all`, le défaut) ne nécessite pas Kafka** - cette
  exigence ne s'applique qu'au mode microservices/scale de Tempo 3.0.
- **Constat d'architecture (étape 4)** : Mustang et Mistral ne sont **jamais appelés dans la
  même requête HTTP** dans ce produit. Mustang (`extract`/`validate`) n'est appelé que par
  `App\Document\MessageHandler\ExtractDocumentContentHandler`, un worker Messenger
  asynchrone déclenché par l'import d'un document - jamais par
  `RunComplianceAnalysisService`, qui ne lit que des données déjà extraites. Mistral n'est
  appelé que par les deux endpoints IA synchrones (`ExplainComplianceFindingService`,
  `AnswerAssistantQuestionService`). Le plan initial de cette étape supposait à tort une
  seule requête combinant les deux (reproduisant un exemple illustratif donné en
  discussion, pas le code réel) - corrigé en traçant chaque point réel séparément plutôt
  que de forcer artificiellement une structure synchrone qui n'existe pas dans ce produit.
- **Modèle de sécurité : SSH-tunnel uniquement, comme Uptime Kuma** - Grafana, Prometheus,
  Loki, Tempo et Alloy ne passent jamais par Traefik et n'ont aucune route publique. Seule
  exception : `GET /api/metrics` (étape 2), protégé par un jeton applicatif
  (`METRICS_SCRAPE_TOKEN`), puisqu'il est servi par Nginx qui, lui, est déjà exposé
  publiquement pour le reste de l'API.
- Versions exactes des images tierces : vérifiées et pinnées au moment de chaque étape
  (jamais le tag `latest`) - voir le journal ci-dessous pour les valeurs réellement
  utilisées, à revérifier avant toute mise à jour future.

## Critère de complétude de la phase - non négociable

La Phase 18 n'est pas considérée terminée tant que le parcours complet suivant n'a pas été
démontré concrètement, une seule fois suffit mais réellement, sur une vraie requête en
production :

```text
Requête HTTP → request_id (RequestIdListener)
     ↓
Logs Loki (recherche par request_id, étape 1)
     ↓
Trace Tempo (même request_id en attribut de span, étape 4)
     ↓
Décomposition Symfony → Mustang/Mistral visible dans la trace
```

**Statut (27/08/2026) : non satisfait, phase toujours ouverte.** Chaque brique est vérifiée
séparément avec de vrais appels réseau (span réel backend → Tempo avec `request_id`
retrouvé par recherche TraceQL ; vraie requête HTTP authentifiée jusqu'à
`MistralProvider::complete()` déclenchant réellement les spans) - voir le journal de
l'étape 4 ci-dessous. Ce qui manque, cause identifiée et non un doute : en environnement
`dev`, `monolog.yaml` envoie les logs vers un fichier (`dev.log`), jamais vers `stdout` -
Alloy ne peut donc pas les voir localement. Seul l'environnement `prod` réel envoie du JSON
structuré sur `stderr`. La démonstration du parcours complet en un seul geste (chercher un
`request_id` dans Loki, cliquer le champ dérivé, arriver sur la trace Tempo) doit donc être
faite une fois en production - procédure exacte dans `docker/observability/README.md`,
section étape 4. Cette phase reste ouverte jusqu'à ce que cette procédure ait été exécutée
et sa preuve ajoutée à ce document (au réel avec captures ou un extrait texte du log et de
la trace correspondante).

C'est ce parcours - pas seulement "les conteneurs tournent" - qui donne sa valeur au
chantier. Non bloquant pour déployer Tempo lui-même, mais bloquant pour clore cette phase.

## Journal des étapes

### Étape 1 - Logs (Alloy + Loki) - fait le 27/08/2026

- `symfony/monolog-bundle` (v4.0.2) ajouté - recette Symfony standard
  (`backend/config/packages/monolog.yaml`) : JSON structuré sur `stderr` en production
  (déjà capturé par Docker), format lisible en développement.
- `App\Shared\Logging\RequestContextProcessor` (nouveau, `backend/src/Shared/Logging/`) :
  enrichit chaque ligne de log avec `request_id` (`RequestIdListener::ATTRIBUTE`, déjà
  généré/renvoyé par requête) et, quand disponibles, `organization_id`
  (`CurrentOrganizationResolver::ATTRIBUTE`) et `user_id` (`Security::getUser()`) -
  identifiants UUID uniquement, jamais d'email, de SIREN, de montant ou de contenu de
  document/prompt IA (`docs/10-security-privacy.md` section 35).
- `docker/observability/loki-config.yaml` : Loki `grafana/loki:3.7.6`, mode single-binary,
  stockage filesystem sur le volume nommé `loki_data`, rétention 30 jours, télémétrie
  anonyme désactivée.
- `docker/observability/alloy-config.alloy` : Alloy `grafana/alloy:v1.19.2`, découverte via
  le socket Docker, filtrée sur `FACTUSENTINEL_COMPOSE_PROJECT` (voir "Bug constaté"
  ci-dessous), forward vers Loki.
- `docker-compose.prod.yml` : services `loki`/`alloy` ajoutés, tous deux sous
  `profiles: [observability]` (actif uniquement si `COMPOSE_PROFILES=observability`, jamais
  défini dans `.env.staging`) - mécanisme Docker Compose standard, aucune duplication de
  fichier compose nécessaire pour la portée "production uniquement".
- `COMPOSE_PROJECT_NAME` rendu obligatoire par environnement (`.env.prod.example`) - unique
  sur tout le VPS, consommé par Compose lui-même ET par Alloy pour le filtrage ci-dessous.

**Bug constaté et corrigé pendant la vérification** : passer uniquement les `.rules`
(export) d'un composant `discovery.relabel` à l'argument `relabel_rules` de
`loki.source.docker` réétiquette les entrées mais **ne les exclut jamais de la collecte** -
vérifié empiriquement en local (Alloy remontait bien les conteneurs d'un tout autre projet
Compose, avec `service_name=unknown_service` faute de label appliqué). Le filtrage réel doit
passer par la liste de cibles elle-même : `discovery.relabel` doit recevoir les vraies
cibles (`targets = discovery.docker.host.targets`), et c'est son export `.output` (pas
`.rules` seul) qui doit alimenter `targets` sur `loki.source.docker`. Reproduit et
revérifié après correction : plus aucune fuite entre projets Compose, logs réels ingérés et
requêtables via l'API Loki (`/loki/api/v1/query_range`), format JSON de production et
injection de `request_id` par `RequestContextProcessor` vérifiés directement.

### Étape 2 - Métriques (Prometheus) - fait le 27/08/2026

- `promphp/prometheus_client_php` (2.15.1) + `artprima/prometheus-metrics-bundle` (1.22.1) -
  stockage Redis (préfixe `metrics`, réutilise `REDIS_URL` existant). `GET /api/metrics`
  (`App\Shared\Controller\GetMetricsController`) protégé par `METRICS_SCRAPE_TOKEN`, jamais
  le firewall JWT tenant.
- `App\Shared\Metrics\MetricsRecorder` (nouveau, `backend/src/Shared/Metrics/`) : seul point
  d'entrée vers le registre Prometheus, instrumente `RunComplianceAnalysisService`
  (compteur/histogramme par résultat global), `UploadDocumentService` (compteur par issue),
  `MistralProvider` (compteur/histogramme par issue), `MustangValidatorClient`
  (compteur/histogramme par opération - `extract`/`validate` - et par issue, ajouté après
  coup pour couvrir les deux dépendances externes du produit de façon symétrique) - jamais
  un résultat de conformité et une erreur technique mélangés dans une même métrique, même
  principe que `../CLAUDE.md` section 9 appliqué à l'observabilité.
- Jauges de `App\PlatformAdmin\Service\PlatformHealthAggregator` réutilisées directement
  (nouvelle `PlatformHealthAggregatorInterface`, même pattern d'interface cross-module que
  Phase 15/16, en sens inverse) - jamais dupliquées.
- Métriques hôte via `prometheus.exporter.unix` (Alloy), poussées vers Prometheus par
  `prometheus.remote_write` (`--web.enable-remote-write-receiver`) - comble le trou
  documenté dans `PlatformHealthAggregator` ("jamais de métrique d'infrastructure hôte").
- Détail complet, bugs constatés et corrigés (le firewall JWT interceptait le jeton de
  scrape ; `prometheus.exporter.unix` seul n'expose aucun `/metrics` scrapable de
  l'extérieur) et vérification effectuée : `docker/observability/README.md`.
- Un seul échec de test préexistant et sans rapport constaté après cette étape
  (`IdempotencyStoreTest`, seuil de timing réel de 250ms non tenu dans cet environnement) -
  composant non touché par cette étape, non corrigé ici (hors périmètre).

### Étape 3 - Dashboards et alerting (Grafana) - fait le 27/08/2026

- Datasources Prometheus/Loki et trois dashboards (`API et Compliance Engine`,
  `Infrastructure`, `Logs`) provisionnés en code (`docker/observability/grafana/`) -
  `allowUiUpdates: false`, jamais de configuration perdue au clic.
- Cinq règles d'alerte réelles (`grafana/provisioning/alerting/rules.yaml`), reprenant la
  grille de sévérité `docs/10-security-privacy.md` §37 dans la limite de ce qui est
  détectable depuis des métriques génériques - le niveau "Critique" (violation d'isolation
  multi-tenant, faille d'authentification, fuite de secret) reste explicitement non couvert,
  jamais présenté comme tel.
- Point de contact Telegram et politique de notification : configuration manuelle unique via
  l'UI Grafana, même bot que Uptime Kuma - jamais provisionnable par fichier sans exposer un
  secret dans un fichier versionné.
- Vérification complète (provisioning sans erreur, datasources `OK`, dashboards/règles
  d'alerte présents via l'API, requêtes réelles exécutées) : `docker/observability/README.md`.

### Étape 4 - Traces (OpenTelemetry SDK manuel + Tempo) - fait (déploiement) le 27/08/2026, critère de clôture de la phase encore ouvert

- `open-telemetry/sdk` (1.15.0) + `open-telemetry/exporter-otlp` (1.4.0, OTLP/HTTP en JSON,
  jamais protobuf - évite une extension PECL supplémentaire). `App\Shared\Observability\Tracer`
  (nouveau) : point d'entrée unique, méthode générique `trace()`, `request_id` injecté
  automatiquement, flush explicite sur `kernel.terminate` et les événements de fin de
  message Messenger (jamais un minuteur d'arrière-plan, incompatible avec un processus
  PHP-FPM court-vécu).
- Spans ajoutés : `compliance_analysis` (parent), `ai.explain_compliance_finding`/
  `ai.answer_assistant_question` (parents) avec `mistral.chat_completion` en enfant,
  `document_processing` (parent, worker Messenger - jamais de `request_id` ici, structurellement
  absent d'un contexte sans requête HTTP) avec `mustang.extract`/`mustang.validate` en enfants.
- Bug constaté et corrigé : Tempo 3.0 a supprimé la section `compactor` - la rétention est
  désormais `overrides.defaults.compaction.block_retention` (confirmé par l'erreur réelle du
  conteneur, jamais deviné).
- Datasource Tempo + champ dérivé Loki (`{.request_id="${__value.raw}"}`, recherche TraceQL
  par attribut de span applicatif, jamais une résolution d'identifiant de trace natif).
- Vérifié avec de vrais appels réseau (backend réel → Tempo réel, même réseau Docker que la
  stack de développement) : span réel retrouvé par recherche TraceQL, vraie requête HTTP
  authentifiée jusqu'à `MistralProvider::complete()` déclenchant réellement les spans
  attendus. Détail complet : `docker/observability/README.md`.
- **Non démontré, cause identifiée** : la corrélation Loki↔Tempo en un seul geste, parce que
  l'environnement `dev` envoie les logs vers un fichier, jamais `stdout` - seul `prod` (la
  vraie production) le fait. Procédure exacte à exécuter une fois déployé :
  `docker/observability/README.md`, étape 4. **La Phase 18 reste ouverte jusqu'à
  l'exécution de cette procédure et l'ajout de sa preuve à ce document.**
