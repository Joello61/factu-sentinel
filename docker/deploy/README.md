# Déploiement CI/CD - FactuSentinel

Phase 17 (`docs/12-roadmap.md`). `.github/workflows/deploy.yml` : construit les images une
seule fois en CI, les pousse vers GHCR, déploie automatiquement en staging puis attend une
validation manuelle avant la production - jamais l'inverse, jamais une reconstruction sur
le serveur cible (plan Phase 17, section 5 : "l'image testée doit être exactement l'image
déployée").

**Déclenchement** : après le succès complet du workflow `CI` (`.github/workflows/lint.yml`)
sur `main` - jamais sur une Pull Request. `next build` (Release Gate ajouté en Phase 17)
bloque donc aussi bien la CI que tout déploiement qui en dépendrait.

## Prérequis Bloc B (aucun n'est fait par ce dépôt)

### 1. Serveur(s) provisionnés

Recommandation d'origine : un serveur pour `staging`, un pour `production` (jamais la
même base de données). **Décision retenue au provisionnement réel** : un seul VPS pour les deux, faute d'un second serveur
disponible - deux stacks Compose indépendantes sur ce VPS (répertoires, bases de
données, volumes de stockage et sous-domaines distincts), routées par la même instance
Traefik partagée. Toujours vrai dans les deux cas :

- Docker + Docker Compose installés.
- Le dépôt cloné une seule fois, manuellement, à un chemin connu (`DEPLOY_PATH`
  ci-dessous - un chemin distinct par environnement si les deux partagent le même
  serveur, ex. `/opt/apps/factusentinel` et `/opt/apps/factusentinel-staging`). Ensuite,
  `ssh-deploy.sh` synchronise lui-même le dépôt (`git fetch` + `git checkout` au commit
  exact dont les images ont été construites, HEAD detached délibérément - ce répertoire
  n'est jamais un espace de développement) à chaque déploiement, jamais un simple `git
  pull` qui suivrait la branche indépendamment des images réellement déployées. Constaté
  au premier déploiement réel (Phase 17) : avant ce correctif, un changement de
  `docker-compose.prod.yml` (rendre le port du service `monitoring` configurable)
  n'atteignait jamais le serveur, cette étape n'existant pas encore - seules les images
  étaient mises à jour. Les fichiers non suivis par Git (`docker-compose.prod.traefik.yml`)
  ne sont jamais affectés par cette synchronisation.
- Secrets applicatifs (`POSTGRES_*`, `APP_SECRET`, `JWT_PASSPHRASE`, etc.) stockés dans
  Infisical (Phase 19 Workstream B) - plus de fichier `.env.staging`/`.env.production`
  contenant des valeurs réelles sur le serveur (voir `.env.prod.example`, désormais
  purement documentaire : inventaire commenté des variables attendues, jamais chargé par
  Compose).
- `docker-compose.prod.traefik.yml` créé une fois par environnement (voir
  `docker-compose.prod.traefik.yml.example`) avec un nom de routeur Traefik **unique sur
  tout le serveur** (ex. `factusentinel` en production, `factusentinel-staging` en
  staging) - jamais committé, jamais le même nom entre deux stacks (Compose n'interpole
  les variables d'environnement que dans les valeurs de labels, jamais dans leurs clés,
  vérifié le 26/08/2026 ; Traefik traite les noms de routeurs comme des identifiants
  globaux, pas par projet Compose).
- **Authentification GHCR configurée une fois** : les images poussées par ce workflow sont
  **privées par défaut** (héritent de la visibilité du dépôt) - le serveur doit
  s'authentifier pour les tirer :
  ```bash
  echo "<personal-access-token, scope read:packages>" | docker login ghcr.io -u <utilisateur-github> --password-stdin
  ```
  Persisté dans `~/.docker/config.json` de l'utilisateur qui exécute les déploiements -
  jamais refait à chaque déploiement, jamais transmis par ce workflow.
- Traefik (infrastructure de niveau serveur, partagée entre projets, hors de ce dépôt)
  déjà installé et démarré sur le serveur, avec le réseau externe `traefik-public` créé
  et le domaine de cet environnement pointant réellement vers le serveur (DNS) - le
  certificat TLS est alors émis automatiquement par Traefik au premier appel HTTPS
  routé, jamais un préalable manuel côté FactuSentinel (voir `docker/nginx/README.md` et
  `github.com/Joello61/infrastructure`, `traefik/`). `docker-compose.prod.yml` (service `nginx`) ne dépend
  plus d'aucun certificat pour démarrer - il ne sert qu'en HTTP interne.

### 2. Clé SSH de déploiement

Une paire de clés **par environnement** (jamais la même clé pour staging et production -
une compromission de l'une ne doit jamais donner accès à l'autre), dont la clé publique
est ajoutée à `~/.ssh/authorized_keys` de l'utilisateur de déploiement sur le serveur
correspondant.

### 3. Environnements GitHub (Settings > Environments)

Créer deux environnements, `staging` et `production`, chacun avec ses **propres** secrets
(mêmes noms, valeurs différentes - résolus automatiquement selon l'environnement déclaré
par le job, `.github/workflows/deploy.yml`) :

| Secret | Contenu |
|---|---|
| `SSH_PRIVATE_KEY` | Clé privée de déploiement de cet environnement (PEM, jamais partagée) |
| `SSH_HOST` | Adresse du serveur (IP ou domaine) |
| `SSH_USER` | Utilisateur SSH de déploiement |
| `DEPLOY_PATH` | Chemin absolu du dépôt cloné sur le serveur (ex. `/opt/factusentinel`) |
| `INFISICAL_CLIENT_ID` | Identité machine Universal Auth (Phase 19 Workstream B) dédiée à cet environnement, projet Infisical "FactuSentinel" |
| `INFISICAL_CLIENT_SECRET` | Client secret associé - accès en lecture seule, jamais partagé entre staging et production |

**Sur l'environnement `production` uniquement** : ajouter une règle **"Required
reviewers"** (au moins toi-même) - c'est ce qui matérialise la "validation manuelle avant
production" du plan Phase 17, jamais configurable depuis le fichier YAML lui-même. Sans
cette règle, `deploy-production` s'exécute automatiquement après `deploy-staging`, ce qui
n'est **pas** le comportement voulu.

### 4. Visibilité des packages GHCR (optionnel)

Par défaut privés (recommandé, cohérent avec `Secure Defaults` - `10-security-privacy.md`
section 3). Rendre un package public (Settings > Packages) dispense le serveur de
`docker login`, mais expose les images construites publiquement - décision à prendre
explicitement, jamais un défaut silencieux.

## Rollback

Jamais automatisé par ce workflow. Rollback applicatif = redéployer le SHA précédent :

```bash
# Depuis un poste avec accès SSH équivalent, ou en relançant manuellement
# docker/deploy/ssh-deploy.sh avec IMAGE_TAG pointant vers un SHA antérieur déjà poussé
# vers GHCR (visible dans l'onglet "Packages" du dépôt).
```

**Jamais de rollback automatique de migration** (plan Phase 17, section 5) : toute
migration destructive doit être scindée en plusieurs déploiements (ajout → migration de
données → bascule du code → suppression dans un déploiement ultérieur) - revue explicite
de chaque nouvelle migration avant fusion, additive vs destructive.

## Vérification avant le tout premier déploiement réel

- [ ] `infisical run --projectId=<id> --env=staging -- docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.prod.traefik.yml config` valide sans erreur sur le serveur lui-même.
- [ ] Traefik installé, démarré, et réseau `traefik-public` créé sur le serveur ; DNS du domaine de cet environnement déjà propagé (`docker/nginx/README.md`, `github.com/Joello61/infrastructure`).
- [ ] `docker login ghcr.io` déjà fait sur le serveur.
- [ ] Secrets des deux environnements GitHub renseignés, "Required reviewers" actif sur `production`.
- [ ] Migrations existantes revues une dernière fois (additives uniquement).
