# Phase 19 - Migration de la stack d'observabilité vers /opt/infrastructure/

> **Mise à jour (31/08/2026) : Phase 19 entièrement exécutée et vérifiée en production ET
> staging - Workstream A (migration infra) et Workstream B (secrets/Infisical).** Voir
> « État d'exécution réel » en fin de document pour les écarts précis par rapport au plan
> ci-dessous. Le plan original est conservé tel quel comme historique de la décision, pas
> réécrit silencieusement - les écarts sont documentés séparément.

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

## Rajout - Hygiène des secrets (27/08/2026), pas encore exécuté

**Constat de l'éditeur** : la majorité des secrets de production/staging (`APP_SECRET`,
`JWT_PASSPHRASE`, `PLATFORM_ADMIN_JWT_PASSPHRASE`, `POSTGRES_PASSWORD`,
`METRICS_SCRAPE_TOKEN`, `GRAFANA_ADMIN_PASSWORD`, clé SMTP Brevo, `MISTRAL_API_KEY`) ont été
générés dans l'urgence pendant la clôture de la Phase 18, directement sur le serveur
(`openssl rand -hex ...`), pour débloquer des fonctionnalités cassées depuis le premier
déploiement - jamais consignés dans un gestionnaire de secrets, uniquement dans les fichiers
`.env.production`/`.env.staging` du serveur. Objectif de ce rajout : régénérer l'ensemble de
ces secrets proprement et les faire vivre dans un vrai gestionnaire, pas seulement des
fichiers texte non versionnés.

**Gap supplémentaire découvert au passage, non corrigé** : `PLATFORM_ADMIN_TOTP_ENCRYPTION_KEY`
(`backend/.env`) a exactement le même défaut que `APP_SECRET` avait avant la Phase 18 - une
vraie valeur de développement committée, jamais surchargée en production
(`docker-compose.prod.yml` ne la référence nulle part). Cette clé chiffre les secrets TOTP
(MFA) des `PlatformAdministrator` en base (`docs/10-security-privacy.md`, section 17 bis) -
la production utilise donc actuellement la clé de dev, publique dans le dépôt Git. Décision
explicite de l'éditeur (27/08/2026) : traiter ce correctif dans ce même chantier de secrets
plutôt que dans l'urgence en fin de session Phase 18.

### Inventaire des secrets réels (vérifié via `.env.prod.example` et le code, pas deviné)

| Secret                             | Où il vit aujourd'hui                                                          | Nature                                                    |
| ----------------------------------- | -------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| `POSTGRES_USER`/`PASSWORD`/`DB`     | `.env.production`/`.env.staging`                                                 | Identifiants base de données                              |
| `APP_SECRET`                        | `.env.production`/`.env.staging`                                                 | `kernel.secret` Symfony (URLs signées, etc.)               |
| `JWT_PASSPHRASE`                    | `.env.production`/`.env.staging`                                                 | Passphrase du trousseau JWT tenant                         |
| `PLATFORM_ADMIN_JWT_PASSPHRASE`     | `.env.production`/`.env.staging`                                                 | Passphrase du trousseau JWT platform admin                 |
| `config/jwt/*.pem` (4 fichiers)     | Volume Docker nommé `jwt_keys`, généré au démarrage par `backend`                | Clés RSA elles-mêmes (pas une simple chaîne)                |
| `PLATFORM_ADMIN_TOTP_ENCRYPTION_KEY`| **Nulle part en production actuellement** (gap ci-dessus, à corriger ici)        | Chiffrement des secrets TOTP (MFA) en base                 |
| `METRICS_SCRAPE_TOKEN`              | `.env.production` **et** `docker/observability/secrets/metrics_scrape_token`     | Jeton de scrape Prometheus - deux emplacements pour la même valeur, jamais désynchronisés manuellement |
| `GRAFANA_ADMIN_USER`/`PASSWORD`     | `.env.production` uniquement (jamais staging, observabilité = prod seule)        | Identifiants admin Grafana                                 |
| `MAILER_DSN` (clé SMTP Brevo)       | `.env.production`/`.env.staging` (deux clés Brevo distinctes déjà générées)       | Identifiants d'envoi d'email                                |
| `MISTRAL_API_KEY`                   | `.env.production`/`.env.staging`                                                 | Clé API fournisseur IA                                     |
| `SSH_PRIVATE_KEY`                   | Secret GitHub Actions (Environment), jamais sur le serveur                       | Déploiement CI/CD                                           |
| `BACKUP_GPG_PASSPHRASE`             | Fourni uniquement à l'invocation manuelle (`docker/backup/README.md`), jamais stocké | Chiffrement des sauvegardes                              |

### Candidats pour le gestionnaire de secrets - aucun choix fait

À évaluer avant de trancher (même discipline que pour Brevo/Mistral - jamais une décision
silencieuse, voir `../CLAUDE.md` section 21) :

- **Infisical** (self-hosted, MIT, open-source) - le plus adopté comme alternative à Vault
  pour un usage self-hosted sans la complexité opérationnelle de Vault ; CLI et Agent pour
  injecter les secrets au déploiement (compatible avec le modèle actuel `--env-file`, ou en
  remplacement direct).
- **HashiCorp Vault / OpenBao** (fork open-source de Vault depuis son passage en licence
  BSL) - le plus complet et le plus robuste, mais la charge opérationnelle pour un
  développeur solo est réelle (cohérence avec `06-technical-architecture.md` section 3,
  "simplicité opérationnelle pour un développeur solo").
- **Bitwarden Secrets Manager** - self-hosting existe mais historiquement réservé aux offres
  payantes/entreprise à vérifier à l'implémentation (peut avoir changé) ; écosystème
  d'intégrations plus restreint que les deux options ci-dessus.

Aucune décision n'est prise ici - seulement le cadre d'évaluation, à trancher au démarrage
réel de ce chantier.

### Plan à haut niveau (pas encore exécuté)

1. Choisir le gestionnaire (ci-dessus), l'installer (self-hosted, cohérent avec le reste de
   la stack qui ne dépend d'aucun service managé tiers au MVP).
2. Régénérer **chaque** secret de l'inventaire ci-dessus avec une valeur fraîche (jamais
   réutiliser une valeur ayant transité par un terminal partagé ou un historique de
   session), y compris `PLATFORM_ADMIN_TOTP_ENCRYPTION_KEY`.
3. Enregistrer chaque valeur dans le gestionnaire, jamais seulement dans `.env.production`/
   `.env.staging` en clair sur le serveur.
4. Adapter le mécanisme d'injection (`docker-compose.prod.yml`, `.env.prod.example`,
   `docker/deploy/ssh-deploy.sh`) pour lire depuis le gestionnaire plutôt que depuis un
   fichier texte - portée exacte à définir selon l'outil choisi.
5. Redéployer les deux environnements avec les nouvelles valeurs, revérifier le parcours
   complet (inscription, connexion, email, IA, alertes) comme à la clôture de la Phase 18.
6. Documenter la procédure de rotation dans `docs/10-security-privacy.md` section 27.

## Ce que ce document ne fait pas (état au moment de la rédaction du plan ci-dessus)

Le plan ci-dessus ne migrait rien, ne provisionnait rien, ne modifiait aucun fichier de
production - une fois que l'éditeur avait validé en conditions réelles que la Phase 18
(couplée à FactuSentinel) fonctionnait correctement (décision explicite du 27/08/2026). Le
rajout sur l'hygiène des secrets suit toujours le même principe et reste non exécuté (voir
ci-dessous) : rien n'a été régénéré, aucun gestionnaire n'a été installé, seul le périmètre
et l'inventaire sont posés.

## État d'exécution réel (28-31/08/2026)

**Workstream A (migration infra) exécuté et vérifié en production**, avec plusieurs écarts
réels par rapport au plan original ci-dessus - jamais une réécriture silencieuse des
sections précédentes, documentés ici :

- **Traefik également migré**, alors que le plan original le laissait explicitement hors
  périmètre ("déjà traité exactement comme l'éditeur le souhaite"). Décision de l'éditeur en
  cours de revue du plan d'exécution : devenir le reverse proxy global versionné du VPS.
  Capture de la configuration réellement active sur le serveur (déjà en place depuis le
  26/08/2026, jamais devinée), `git init` en place sans toucher au conteneur en cours
  d'exécution - zéro coupure, contrairement à la procédure de bascule initialement prévue.
- **Uptime Kuma également migré**, absent du plan original (gap dans le gap, découvert
  pendant la revue du plan d'exécution). Aucune fusion propre possible entre les deux
  instances existantes (production/staging) - Uptime Kuma 2.x a retiré l'export/import JSON
  (vérifié, pas supposé) - décision de l'éditeur : une seule instance partagée, données de
  production migrées 1:1, moniteurs de staging recréés manuellement.
- **`/opt/infrastructure/` devient un dépôt Git privé dédié** (`github.com/Joello61/infrastructure`),
  jamais des fichiers serveur non versionnés - le plan original laissait la question ouverte.
- Le gestionnaire de secrets a été tranché en cours d'exécution : **Infisical** (self-hosted,
  MIT) - voir Workstream B ci-dessous, toujours non exécuté au moment de cette mise à jour.

**Trois bugs réels trouvés et corrigés pendant l'exécution**, aucun anticipé dans le plan
original, tous vérifiés avant/après correction (jamais supposés résolus) :

1. **Réseau `observability-shared` partagé par erreur avec staging.** `docker-compose.prod.yml`
   étant identique entre production et staging, l'ajout direct du réseau prévu par le plan
   original mettait aussi staging dessus - collision d'alias DNS Compose (`nginx`) entre les
   deux environnements, Prometheus scrapait parfois le mauvais environnement. Corrigé par un
   nouveau fichier `docker-compose.prod.observability.yml`, chargé uniquement en production
   par `ssh-deploy.sh` (la fusion de listes réseau Compose étant strictement additive,
   l'exclusion ne peut se faire qu'au niveau du fichier chargé, jamais de son contenu). Un
   second bug (perte du réseau `default` implicite pour `backend`/`worker`) a été attrapé par
   `docker compose config` avant tout commit.
2. **Permissions du fichier `metrics_scrape_token_factusentinel`** - Prometheus tourne en
   UID `65534` (`nobody`) dans son conteneur, pas root ; le fichier créé côté hôte
   appartenait à `ubuntu` avec `600` - `chown 65534:65534` requis.
3. **`METRICS_SCRAPE_TOKEN` jamais câblé dans `docker-compose.prod.yml` depuis la Phase 18**
   (absent du bloc `environment` de `backend`, contrairement à `APP_SECRET`/`MAILER_DSN`/
   `JWT_PASSPHRASE`/`MISTRAL_API_KEY`, déjà corrigés à l'époque) - la production acceptait
   silencieusement le jeton de développement commité dans `backend/.env` au lieu de la
   vraie valeur de `.env.production`. Trouvé en vérifiant le scrape Prometheus depuis le
   nouveau socle, jamais une régression de cette migration - un gap préexistant depuis la
   Phase 18, jamais repéré avant.

Autre correction apportée en cours de route, indépendante du contenu de ce plan mais
nécessaire pour l'exécuter sans risque : ajout de `--remove-orphans` à
`docker/deploy/ssh-deploy.sh` (`docker compose up -d` ne retire jamais de lui-même les
conteneurs d'un service supprimé d'un fichier Compose).

Détail complet des vérifications (traces Tempo, alertes Grafana, datasources) :
`docs/19-observability-architecture.md`, section « Phase 19 - migration vers le socle
partagé ».

## Workstream B (secrets/Infisical) - exécuté et vérifié (31/08/2026)

**Infisical déployé** dans `github.com/Joello61/infrastructure` (base Postgres/Redis
dédiées, réseau Docker isolé) - deux projets : `FactuSentinel` (environnements `staging`/
`production`) et `Infrastructure` (secrets du socle partagé lui-même, `GRAFANA_ADMIN_*`).
Trois identités machine Universal Auth créées, une par usage (`factusentinel-staging-deploy`,
`factusentinel-production-deploy`, `infrastructure-deploy`), chacune scopée en lecture seule
à son seul environnement (rôle projet `No Access` + Additional Privilege `Describe Secret` +
`Read Value` sur l'environnement concerné - les deux permissions sont nécessaires ensemble,
`describeSecret` seul ou `readValue` seul ne suffisent pas, vérifié sur la documentation
officielle Infisical après un vrai `403` en production).

**Tous les secrets régénérés** (staging d'abord, puis production) et stockés exclusivement
dans Infisical - `POSTGRES_*`, `APP_SECRET`, `JWT_PASSPHRASE`, `PLATFORM_ADMIN_JWT_PASSPHRASE`,
`PLATFORM_ADMIN_TOTP_ENCRYPTION_KEY` (nouveau, corrige le gap), `METRICS_SCRAPE_TOKEN`,
`PUBLIC_DOMAIN`/`CORS_ALLOW_ORIGIN`/`FRONTEND_URL`/`HSTS_ENABLED` (non secrets mais migrés
aussi, pour permettre la suppression complète des fichiers `.env`). `MAILER_DSN`/
`MISTRAL_API_KEY` d'abord migrés tels quels (décision explicite de l'éditeur : pas de
régénération via les tableaux de bord Brevo/Mistral dans le même geste que le reste), puis
réellement régénérés séparément le 31/08/2026 - clé Mistral distincte par environnement
(staging/production, jamais partagée, cohérent avec le principe déjà appliqué à toutes les
autres paires de secrets de ce document) et nouvelle clé SMTP Brevo par environnement.
Import en masse via `infisical secrets set --file=... --env=... --projectId=...` (CLI
officiel, jamais saisi secret par secret dans l'interface) - génération et poussée dans un
seul script shell qui n'affiche jamais aucune valeur en clair, fichier temporaire détruit
immédiatement après.

**`ssh-deploy.sh` réécrit pour l'injection au runtime** (`infisical run`, Universal Auth,
un jeton par déploiement) - plus aucun fichier `.env.production`/`.env.staging` sur le
serveur, supprimés après validation complète des deux environnements. `docker/backup/automated-backup.sh`
migré du même principe (remplace un `source .env.production` qui serait devenu impossible).

**Volumes réinitialisés** (`jwt_keys`, `postgres_data`) pour les deux environnements, avant
la bascule vers les nouveaux secrets - environnements confirmés vierges de données
utilisateur réelles par l'éditeur, migration de données donc non nécessaire (contrairement
à ce que `POSTGRES_PASSWORD`/`JWT_PASSPHRASE` auraient autrement exigé - voir la note
technique sur l'ordre `ALTER ROLE` dans une version antérieure de ce document, devenue sans
objet ici).

**Quatre bugs réels trouvés et corrigés pendant l'exécution**, tous vérifiés avant/après
correction :
1. `export VAR="$(commande)"` sur une seule ligne avale le code de sortie de la
   substitution de commande sous `set -e` (le code de sortie devient celui d'`export`
   lui-même) - un échec d'authentification Infisical n'arrêtait jamais le script,
   continuait avec un jeton vide. Vérifié empiriquement (`export FOO=$(false)` sort en 0,
   `FOO=$(false); export FOO` sort en 1). Affectation et export désormais toujours séparés.
2. `403 "not allowed to describeSecret"` - `describeSecret` et `readValue` sont deux
   permissions Infisical distinctes, toutes deux nécessaires pour lire une valeur de
   secret (vérifié sur la documentation officielle après l'avoir constaté en production) -
   seule `readValue` avait été accordée initialement.
3. `docker compose down`/`exec` échoue en "invalid compose project" sans
   `BACKEND_IMAGE`/`FRONTEND_IMAGE`/`MUSTANG_IMAGE` valides, même pour des opérations qui
   ne créent aucun conteneur à partir de ces images - `automated-backup.sh` les déduit
   désormais des conteneurs réellement en cours d'exécution (`docker inspect`) plutôt que
   de les supposer connues à l'avance (contrairement à `ssh-deploy.sh`, qui connaît le SHA
   testé).
4. **Cron de sauvegarde silencieusement en échec depuis sa mise en place** (pas une
   régression de cette phase, un gap préexistant révélé par la surveillance nouvellement
   fonctionnelle d'Uptime Kuma) - `/var/log/` appartient à `root:syslog`, jamais
   inscriptible par l'utilisateur de déploiement ; le cron se déclenchait bien chaque nuit
   (confirmé dans les journaux système) mais échouait à la redirection du log elle-même,
   avant même que le script ne démarre - aucun log n'était donc jamais produit pour le
   remarquer. Log redirigé vers `backups/backup.log`, déjà inscriptible.

**Ajout hors périmètre initial, décision de l'éditeur en cours d'exécution** : pgAdmin,
partagé entre tous les projets hébergés sur ce VPS, même modèle qu'Uptime Kuma (tunnel SSH
uniquement, rejoint le réseau interne de chaque projet plutôt que `observability-shared` -
jamais une base de données exposée sur un réseau partagé générique). Aucune connexion
PostgreSQL pré-provisionnée - configurée manuellement par serveur/environnement, mot de
passe récupéré dans Infisical au moment de la connexion.

**Vérification complète effectuée** : parcours applicatif (inscription/connexion/email/IA)
sur les deux environnements après rotation, création et enrôlement MFA réel du premier
`PlatformAdministrator` sur les deux environnements (confirme `PLATFORM_ADMIN_TOTP_ENCRYPTION_KEY`),
sauvegarde production réelle produite et envoyée vers le stockage distant, nettoyage
résiduel confirmé (aucun fichier `.env`/jeton en clair restant sur le serveur, volumes
orphelins de l'ancienne stack Phase 18 supprimés).

## Le socle partagé lui-même branché sur Infisical (31/08/2026)

Dernier point resté différé à la clôture initiale de la Phase 19 : le lancement de
`/opt/infrastructure/` lui-même (`GRAFANA_ADMIN_*`, `PGADMIN_DEFAULT_*`,
`OBSERVABILITY_ALLOWED_PROJECTS`) lisait encore un fichier `.env` en clair sur le serveur.
Corrigé avec exactement le même mécanisme que côté FactuSentinel : un second projet
Infisical (`Infrastructure`, distinct de `FactuSentinel` - moindre privilège, une identité
compromise dans un projet n'expose jamais l'autre), une identité machine dédiée
(`infrastructure-deploy`, resserrée de `Member` vers `No Access` + `Describe Secret` +
`Read Value` en cours de route pour rester cohérente avec le modèle de moindre privilège
déjà appliqué partout ailleurs), et un nouveau `deploy.sh` dans le dépôt
`github.com/Joello61/infrastructure` qui enveloppe `docker compose up -d` avec
`infisical run` - même patron que `docker/deploy/ssh-deploy.sh`.

**Exception de bootstrap conservée, jamais éliminée** : `.env` sur le serveur contient
toujours les cinq identifiants nécessaires à Infisical pour démarrer lui-même
(`INFISICAL_POSTGRES_USER`/`PASSWORD`/`DB`, `INFISICAL_ENCRYPTION_KEY`,
`INFISICAL_AUTH_SECRET`, `INFISICAL_SITE_URL`) plus `GRAFANA_PORT` (non secret, purement
local à ce serveur) - rien ne peut structurellement injecter ces valeurs depuis Infisical
avant qu'il soit lui-même démarré. Documenté comme cas particulier assumé depuis le début
de ce chantier, jamais une régression découverte après coup.

Vérifié : `deploy.sh` injecte bien les 5 secrets non-bootstrap au lancement ("Injecting 5
Infisical secrets"), les 10 conteneurs du socle démarrent sains, Grafana/pgAdmin/Uptime
Kuma répondent normalement, Alloy continue de filtrer correctement avec
`OBSERVABILITY_ALLOWED_PROJECTS` reçu depuis Infisical plutôt que d'un fichier local.
Ancien `.env` (avec les valeurs désormais migrées) sauvegardé le temps de la vérification
puis supprimé.

**Phase 19 - plus aucun élément différé.**
