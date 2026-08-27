# Phase 19 - Migration de la stack d'observabilité vers /opt/infrastructure/

> Ce document ne migre rien : il documente un écart constaté, sa cause, et un plan de
> migration concret - rien de ce qui suit n'a été exécuté. La Phase 18 (`docs/12-roadmap.md`
> §41, `docs/19-observability-architecture.md`) reste la source de vérité pour ce qui est
> réellement déployé aujourd'hui.

## Le gap constaté

Dès la toute première proposition de ce chantier, l'éditeur a explicitement décrit un socle
partagé :

> `/opt/infrastructure/` avec `traefik/`, `grafana/`, `loki/`, `tempo/`, `prometheus/`,
> `uptime-kuma/`, `backups/` - "chaque projet (FactuSentinel, futur SaaS...) envoie
> automatiquement ses logs, traces et métriques vers cette infrastructure commune."

La Phase 18 n'a **pas** construit ce socle partagé. Loki, Alloy, Prometheus, Grafana et
Tempo ont été ajoutés directement dans `docker-compose.prod.yml` de FactuSentinel, sur son
réseau Docker par défaut, avec des configurations qui ne connaissent que ce seul projet.

**Cause identifiée, pas une décision délibérée** : au moment de construire le plan de la
Phase 18, l'agent (Claude) n'a jamais repris ni écarté explicitement la proposition
`/opt/infrastructure/` de l'éditeur - il est parti par défaut sur le pattern déjà établi par
Uptime Kuma en Phase 17 (ajouté directement dans `docker-compose.prod.yml`), sans vérifier
que ce choix correspondait encore à l'intention de l'éditeur pour ce chantier précis. C'est
exactement le type de décision qui aurait dû être soumise à l'éditeur plutôt que tranchée
silencieusement (`../CLAUDE.md`, section 1 : "Claude ne doit jamais remplacer silencieusement
une décision documentée par sa propre préférence"). Constaté et reconnu en cours de
Phase 18 (27/08/2026), après que l'étape 3 (Grafana) était déjà commitée - décision de
l'éditeur à ce moment : terminer la Phase 18 couplée à FactuSentinel (moindre risque,
permet de valider que le chantier fonctionne avant de le retravailler), puis documenter
cette migration séparément - c'est l'objet de ce document.

## Ce qui est réellement couplé aujourd'hui

- Les cinq services (`loki`, `alloy`, `prometheus`, `grafana`, `tempo`) sont définis dans
  `docker-compose.prod.yml` de FactuSentinel, jamais dans un fichier séparé.
- Ils tournent sur le réseau `default` du projet Compose FactuSentinel - un autre projet
  (avec son propre `docker-compose`) ne serait pas sur ce réseau et ne pourrait pas
  joindre `loki:3100`/`prometheus:9090`/`tempo:4318` par leur nom sans pont réseau.
- `docker/observability/prometheus.yml` scrape une cible unique en dur
  (`nginx:80/api/metrics`, avec le jeton spécifique de FactuSentinel) - un autre projet
  demanderait d'éditer ce fichier committé dans ce dépôt.
- `docker/observability/alloy-config.alloy` ne remonte que les conteneurs du projet Compose
  désigné par `FACTUSENTINEL_COMPOSE_PROJECT` - un autre projet serait exclu par
  construction, jamais par accident (c'est précisément ce filtre qui protège aujourd'hui
  staging d'un mélange avec production sur le même VPS, voir `docs/19-observability-architecture.md`
  étape 1).
- Les dashboards et règles d'alerte Grafana interrogent des métriques nommées
  `factusentinel_*` (namespace du registre Prometheus, `backend/config/packages/artprima_prometheus_metrics.yaml`).

## Ce qui n'est PAS couplé - réutilisable tel quel

C'est la majorité du travail réel de la Phase 18 :

- Tout le code applicatif : `App\Shared\Logging\RequestContextProcessor`,
  `App\Shared\Metrics\MetricsRecorder`, `App\Shared\Controller\GetMetricsController`,
  `App\Shared\Observability\Tracer`. Ce code produit des logs JSON sur `stdout` et expose
  `/api/metrics` - peu importe qui vient les lire ensuite, un Loki/Prometheus partagé ou
  dédié au projet.
- Le contenu des configurations Loki/Prometheus/Grafana/Tempo (rétention, dashboards,
  règles d'alerte) - à déplacer, jamais à réécrire.
- Le principe de sécurité (SSH-tunnel uniquement, jamais de route publique) - identique
  dans un socle partagé.

## Précédent déjà existant dans ce dépôt : Traefik

Traefik est **déjà** traité exactement comme l'éditeur le souhaite pour l'observabilité :
"infrastructure de niveau serveur, hors de ce dépôt" (`docker/nginx/README.md`). Le service
`nginx` de FactuSentinel le rejoint via un réseau Docker externe partagé
(`traefik-public`, déclaré `external: true` dans `docker-compose.prod.yml`) plutôt que
d'avoir Traefik dans son propre `docker-compose.yml`. La migration proposée ci-dessous
applique exactement ce même schéma à Loki/Alloy/Prometheus/Grafana/Tempo.

## Plan de migration proposé

### 1. Nouveau socle `/opt/infrastructure/`

Un répertoire sur le serveur (pas nécessairement un dépôt Git séparé - Traefik lui-même n'en
a pas), avec son propre `docker-compose.yml` reprenant les cinq services actuels de
`docker-compose.prod.yml`, déplacés tels quels :

```text
/opt/infrastructure/
├── docker-compose.yml
├── .env                          # GRAFANA_ADMIN_USER/PASSWORD, GRAFANA_PORT, etc.
├── loki-config.yaml              # déplacé depuis docker/observability/
├── prometheus.yml                # déplacé, devient multi-job (voir point 4)
├── tempo.yaml                    # déplacé tel quel
├── alloy-config.alloy            # déplacé, filtre élargi (voir point 3)
├── grafana/
│   ├── provisioning/             # déplacé tel quel (datasources, dashboards, alerting)
│   └── dashboards/                # déplacé tel quel
└── secrets/
    └── metrics_scrape_token_factusentinel   # un fichier de jeton par projet scrapé
```

### 2. Réseau Docker externe partagé

Un réseau créé une fois sur le serveur (même geste que `traefik-public` aujourd'hui) :

```bash
docker network create observability-shared
```

`/opt/infrastructure/docker-compose.yml` déclare ce réseau comme le réseau par défaut de ses
services. Chaque projet qui veut être scrapé/tracé (FactuSentinel, un futur SaaS) ajoute ce
réseau **en plus** de son réseau `default`, sur le seul service qui doit être joignable
(`nginx` pour le scrape HTTP, `backend`/`worker` pour l'export OTLP sortant vers Tempo -
un export sortant n'a en réalité pas besoin d'être sur le même réseau si Tempo publie un
port accessible sur ce réseau partagé, à trancher à l'implémentation) :

```yaml
# docker-compose.prod.yml de FactuSentinel, service "nginx" (même pattern que
# "traefik-public" juste en dessous)
networks:
  - default
  - traefik-public
  - observability-shared

networks:
  observability-shared:
    external: true
```

### 3. Filtre Alloy : d'un projet unique à une liste autorisée

`docker/observability/alloy-config.alloy` (étape 1, Phase 18) filtre aujourd'hui sur
`sys.env("FACTUSENTINEL_COMPOSE_PROJECT")`, une égalité stricte. Remplacer par une variable
d'environnement listant les projets autorisés (ex. `OBSERVABILITY_ALLOWED_PROJECTS`,
séparés par `|`) et une regex dans la règle `keep` - changement contenu à quelques lignes,
jamais une réécriture du composant.

### 4. Prometheus : d'une cible unique à un job par projet

`docker/observability/prometheus.yml` passe d'un seul `job_name: factusentinel-backend` à
un `scrape_configs` par projet, chacun avec sa propre cible et son propre
`credentials_file` (un jeton par projet, jamais partagé). Fichier désormais maintenu dans
`/opt/infrastructure/`, pas dans le dépôt d'un projet applicatif.

### 5. Dashboards et alertes Grafana

Contenu inchangé pour FactuSentinel (déjà namespacé `factusentinel_*`) - un futur projet
ajoute ses propres dashboards/règles à côté, sans toucher aux existants. Envisager des
dossiers Grafana séparés par projet si le nombre de dashboards grandit (`folder:` dans
`grafana/provisioning/dashboards/dashboards.yaml`), pas nécessaire tant qu'il n'y a qu'un
seul projet réel.

### 6. Ce qui ne change jamais

- Le modèle de sécurité (SSH-tunnel uniquement, jamais de route publique pour
  Loki/Prometheus/Grafana/Tempo).
- Le principe "jamais de secret dans un fichier versionné" (`../CLAUDE.md` section 15) -
  chaque jeton de scrape reste un fichier créé une fois manuellement sur le serveur.
- Le code applicatif de FactuSentinel (`RequestContextProcessor`, `MetricsRecorder`,
  `Tracer`, `GetMetricsController`) - aucune modification requise, il ignore complètement
  qui consomme ses logs/métriques/traces.

## Ce que ce document ne fait pas

Il ne migre rien, ne provisionne rien, ne modifie aucun fichier de production. C'est un
plan à exécuter plus tard, une fois que l'éditeur aura validé en conditions réelles que la
Phase 18 (couplée à FactuSentinel) fonctionne correctement - décision explicite de
l'éditeur (27/08/2026) de séquencer les choses ainsi plutôt que de retravailler
l'architecture avant d'avoir un signal de fonctionnement réel.
