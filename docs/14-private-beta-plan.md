# Plan Private Beta - Phase 12

> Distinct de `docs/12-roadmap.md` : la roadmap documente ce qui a été construit phase par
> phase ; ce document documente comment la Private Beta est conduite concrètement (accès,
> protocole, feedback) et, au fil des sessions réelles, ce qui en est ressorti - même esprit que
> `docs/13-mvp-validation-report.md` pour la Phase 11.

## 1. Ce que cette phase est - et n'est pas

La Phase 12 confronte le produit à quelques utilisateurs ciblés du persona primaire
(`docs/03-market-analysis.md` section 4, `docs/04-product-requirements.md` ligne 42 : "le
freelance qui pense être déjà en règle"). Elle valide une hypothèse produit
(`docs/12-roadmap.md` section 35), pas une mise en production.

**Terminologie importante, à ne jamais confondre** : cette phase n'utilise **aucun
environnement de staging**. Le vrai staging (`docs/12-roadmap.md` section 27) reste un
chantier Phase 13, non anticipé ici. Ce qui est décrit ci-dessous est **la stack de
développement existante (`docker-compose.yml`), exposée temporairement et sous supervision**
via un tunnel éphémère - jamais une infrastructure durcie, jamais un service disponible en
continu.

Deux décisions produit encadrent tout ce document (`docs/12-roadmap.md`, Decision Log, DL-012) :

1. **Pas d'hébergement public** - `docs/13-mvp-validation-report.md` (section 2, 10, 11)
   documente qu'aucun hébergeur n'est retenu et que `next build` échoue de façon déterministe
   sur un bug amont Next.js toujours ouvert (vérifié le 21/08/2026, y compris sur des versions
   plus récentes que celle installée). Plutôt que d'anticiper la Phase 13, l'accès bêta passe
   par un tunnel temporaire pointant sur la stack de développement existante.
2. **Pas de mécanisme de feedback in-app** - `FR-TRUST-001` (signalement in-app d'un désaccord,
   `04-product-requirements.md` section 18) reste priorité **Future**, inchangée par cette
   phase. Le feedback est collecté par un outil externe et des entretiens directs (section 5).

## 2. Critères de sélection des testeurs

Persona primaire uniquement pour ce premier cycle (`03-market-analysis.md` section 4,
Persona 1) : freelance/consultant indépendant facturant des clients professionnels, sans
logiciel de facturation dédié, ayant entendu parler de la réforme sans être certain de ses
obligations. Quelques utilisateurs (pas de seuil chiffré arbitraire, `docs/12-roadmap.md`
section 12) suffisent pour ce cycle - privilégier la qualité du retour à la quantité de
testeurs.

## 3. Accès : tunnel éphémère, session par session

Procédure, à exécuter avant chaque session supervisée, jamais laissée active en continu :

1. Copier `.env.beta.example` en `.env.beta` (jamais commité).
2. Démarrer la stack bêta :
   ```
   docker compose --env-file .env.beta -f docker-compose.yml -f docker-compose.beta.yml up -d
   ```
3. Récupérer l'URL publique du tunnel dans les logs de `cloudflared` :
   ```
   docker compose logs cloudflared | grep trycloudflare.com
   ```
4. Mettre à jour `.env.beta` (`BETA_PUBLIC_URL`, `BETA_PUBLIC_ORIGIN_REGEX`) avec cette URL,
   puis relancer `backend`/`worker` pour qu'ils prennent en compte `CORS_ALLOW_ORIGIN`/
   `FRONTEND_URL` à jour :
   ```
   docker compose --env-file .env.beta -f docker-compose.yml -f docker-compose.beta.yml up -d backend worker
   ```
5. Conduire la session avec le testeur (voir section 4).
6. À la fin de la session, arrêter le tunnel et la stack bêta :
   ```
   docker compose --env-file .env.beta -f docker-compose.yml -f docker-compose.beta.yml down
   ```

Le tunnel (Cloudflare Quick Tunnel, `cloudflared`) cible exclusivement le service `nginx`
interne (`http://nginx:80`) - jamais `next dev` (port 3000) ni le backend (port 9000)
directement. Cloudflare documente ce mode comme "intended for testing and development only",
sans compte ni token requis, avec un sous-domaine `trycloudflare.com` aléatoire à chaque
lancement, 200 requêtes concurrentes maximum et aucune SLA - cohérent avec un usage ponctuel et
supervisé, pas avec un service permanent.

**Piège opérationnel constaté pendant l'implémentation, à connaître avant toute session** :
`docker/nginx/default.conf` est monté en bind-mount fichier unique (`:ro`) dans
`docker-compose.yml`. Un simple `nginx -s reload` après une modification de ce fichier peut
servir une version **périmée** si Docker n'a pas suivi le remplacement d'inode du fichier hôte
- **toujours vérifier** avec
`docker compose exec nginx cat /etc/nginx/conf.d/default.conf` après toute modification de ce
fichier, et si le contenu ne correspond pas, recréer le conteneur
(`docker compose up -d --force-recreate nginx`) plutôt que de se fier à `reload`/`restart`.

## 4. Vérification de sécurité effectuée avant la première session

- **IP réelle du visiteur à travers nginx** : à travers un Cloudflare Tunnel, l'IP réelle
  arrive dans l'en-tête `CF-Connecting-IP` (posé par l'edge Cloudflare lui-même à partir de la
  connexion TCP réelle, jamais par le client - vérifié sur la documentation officielle
  Cloudflare) - jamais dans `X-Forwarded-For`, explicitement déconseillé par Cloudflare pour cet
  usage (accumulable, moins fiable). `docker/nginx/default.conf` résout désormais l'IP/le
  schéma réels via ce seul en-tête (avec repli sur `$remote_addr`/`$scheme` en accès direct
  local, sans tunnel), et **écrase** (plutôt que d'accumuler) la valeur transmise à Symfony -
  un visiteur ne peut donc pas usurper cette valeur via un `X-Forwarded-For` de son choix.
  Vérifié empiriquement (`curl` avec des valeurs `X-Forwarded-For` forgées différentes à chaque
  requête) : la valeur retenue par nginx reste constante, celle du véritable appelant.
- **`trusted_proxies`** (`backend/config/packages/framework.yaml`) : `PRIVATE_SUBNETS`,
  restrictif dans cette topologie précise car `backend:9000` n'est jamais publié hors du réseau
  Docker interne (`expose`, jamais `ports:`) - `nginx` est structurellement le seul pair TCP
  possible de PHP-FPM. `trusted_headers` limité à `x-forwarded-for`/`x-forwarded-proto`, les
  deux seuls en-têtes réellement positionnés par nginx.
- **Limite assumée, à connaître** : `docker-compose.yml` publie `nginx` sur `0.0.0.0:8080` (pas
  seulement `127.0.0.1`), pour rester utilisable normalement en développement local. Si la
  machine du développeur était elle-même directement joignable depuis Internet sur ce port
  (hors NAT domestique/bureau standard), un tiers pourrait forger `CF-Connecting-IP` en
  contournant totalement le tunnel. Hypothèse retenue pour cette phase : le réseau du
  développeur n'est pas exposé publiquement en dehors du tunnel - à revalider si les sessions
  se déroulent depuis un réseau dont l'exposition n'est pas maîtrisée.
- **`APP_DEBUG=0`** forcé explicitement sur `backend`/`worker` dans `docker-compose.beta.yml` -
  aucune trace d'erreur Symfony détaillée exposée à un testeur externe, indépendamment du
  comportement par défaut de Symfony Runtime en `APP_ENV=dev`.
- **Bug distinct, découvert puis corrigé à la demande explicite** : en testant le rate limiting
  de connexion (`security.yaml`, `login_throttling`, `max_attempts: 5`), aucune requête n'était
  bloquée après 7 tentatives consécutives avec des identifiants invalides, même sans aucune
  usurpation d'en-tête - comportement antérieur à la Phase 12, initialement signalé comme hors
  périmètre puis corrigé sur demande. Cause racine : `LoginThrottlingListener` levait
  correctement une `TooManyLoginAttemptsAuthenticationException` à la 6e tentative, mais
  `App\Shared\Security\AuthFailureEnvelopeListener` écrasait systématiquement toute
  `AuthenticationException` reçue par un `401` générique - le rate limiting était donc actif
  côté Symfony sans jamais être opposable en pratique. Corrigé (`AuthFailureEnvelopeListener`
  distingue désormais ce cas, répond `429` avec `Retry-After`, sans révéler d'information sur
  le compte - US-AUTH-002 toujours respecté), vérifié par un test de régression
  (`LoginControllerTest::testRepeatedFailedLoginsAreThrottledWith429`) et manuellement à travers
  nginx. Détail complet en `docs/10-security-privacy.md` section 36. Le rate limiting des autres
  endpoints (`compliance_analysis_trigger`, `document_upload`, `ai_assistant`,
  `password_reset_request`, tous par `organization_id`,
  `backend/config/packages/rate_limiter.yaml`) n'a pas été réévalué - ce bug concernait
  spécifiquement `login_throttling`.

## 5. Protocole de session supervisée

1. Rappeler au testeur, avant toute manipulation, la règle de données (section 6).
2. Parcourir avec lui le parcours MVP (`docs/12-roadmap.md` section 6) : compte -> entreprise
   -> diagnostic -> client -> facture -> analyse -> compréhension du résultat -> correction.
3. Récupérer le lien de vérification d'email dans Mailpit (`http://127.0.0.1:8025`, accessible
   uniquement au développeur, jamais via le tunnel) et le relayer oralement/par message au
   testeur - aucun vrai fournisseur email n'est utilisé pour cette phase (évite un nouveau
   sous-traitant/DPA, `docs/10-security-privacy.md` section 44, pour quelques utilisateurs en
   session supervisée).
4. Observer sans intervenir sur les points de blocage/incompréhension (validation d'usabilité,
   `docs/12-roadmap.md` section 35).
5. À la fin, faire remplir le formulaire de feedback (section 7) et/ou conduire un court
   entretien direct.

## 6. Règle de données - point de sécurité le plus important de cette phase

**Données synthétiques uniquement, jamais de donnée réelle**, tant que la stack reste une
stack de développement temporairement exposée (section 1) et non une infrastructure durcie.
Interdits en bêta : nom/prénom réels, SIREN réel, coordonnées client réelles, factures réelles,
montants réels, documents PDF réels, données d'entreprise réelles. Une adresse email
dédiée/jetable est préférable à l'email personnel réel du testeur (uniquement nécessaire pour
recevoir le lien de vérification, relayé de toute façon par le développeur via Mailpit).

Cette règle doit être communiquée au testeur **avant** la session, jamais découverte après
coup.

## 7. Formulaire de feedback (externe)

Contenu prêt à coller dans un outil externe (Google Form/Typeform ou équivalent, choisi par
l'utilisateur - hors périmètre technique de ce document) :

- **Catégorie du retour** (reprise exacte de `docs/12-roadmap.md` section 36) : bug / UX /
  fonctionnalité manquante / doute sur la conformité d'un résultat / performance / sécurité.
- Parcours réalisé.
- Facilité d'utilisation perçue (échelle).
- Problème rencontré.
- Résultat attendu vs résultat obtenu.
- Gravité perçue.
- Capture d'écran (si l'outil le permet).
- Commentaire libre.
- Volonté de continuer à utiliser le produit.

Complété si besoin par des entretiens directs avec les testeurs. **`FR-TRUST-001` (signalement
in-app) reste `Future`, non modifié par cette phase** - aucune demande de feedback n'est
automatiquement transformée en priorité (`docs/12-roadmap.md` section 36) ; chaque retour est
confronté au périmètre déjà défini (`04-product-requirements.md` section 30) avant intégration
éventuelle au backlog Post-MVP.

## 8. Limitations explicitement assumées

- Pas de build de production Next.js (`next build` cassé, bug amont) - le testeur utilise
  `next dev`, dont l'overlay d'erreur React est plus verbeux qu'une vraie production. Non
  corrigé ici, réservé à la Phase 13.
- Pas de vrai fournisseur email - Mailpit + relais manuel par le développeur (section 5).
- Aucune haute disponibilité - le Quick Tunnel n'offre aucune SLA, cohérent avec un usage
  ponctuel supervisé.

## 9. Bilan

_À compléter au fil des sessions réelles._
