# Revue de sécurité interne - Phase 17

> **Cette revue interne a été effectuée dans le cadre de la préparation de la Phase 17
> (`docs/12-roadmap.md` §10, §43). Elle ne constitue pas un pentest et ne satisfait pas le
> critère de sortie de la Phase 17** (`10-security-privacy.md` section 61 : un pentest
> complet du produit reste requis avant la mise en production commerciale, distinct et non
> substituable par cette revue). Voir `docs/17-pentest-scope.md` pour le dossier de scope
> destiné à un prestataire externe.

Méthode : lecture de code réelle (pas seulement de la documentation), reproduction
effective des points douteux quand c'était possible (compilation du conteneur DI,
exécution de tests), jamais une affirmation non vérifiée. Chaque constat ci-dessous est
classé **Corrigé** (changement livré dans cette phase, avec preuve), **Vérifié conforme**
(examiné, aucun problème trouvé) ou **Signalé, action externe requise** (le code ne peut
pas, à lui seul, résoudre le point).

## 1. Authentification / JWT

### 1.1 Refresh token - `reuse_detection` (Corrigé)

**Constat initial** : `single_use: true` (rotation, un token déjà consommé est rejeté)
était actif, mais `reuse_detection` (révocation de toute la famille de jetons en cas de
rejeu d'un jeton déjà consommé) était désactivée, avec un commentaire indiquant une
erreur de compilation sur `gesdinet/jwt-refresh-token-bundle` v3.0.0, jamais creusée plus
loin.

**Investigation menée** (les quatre questions posées explicitement pour cette phase) :

1. Version actuellement publiée du bundle : **v3.0.0, publiée le 05/08/2026, seule
   version 3.x existante** (`composer show --all`) - aucune version corrigée disponible.
2. Compatibilité d'une version plus récente : sans objet, aucune n'existe.
3. Activation malgré l'absence de correctif amont : **oui, possible et fait** - le bug a
   été isolé précisément dans `vendor/gesdinet/jwt-refresh-token-bundle/config/
   reuse_detection.php` : la définition du service `spent_refresh_token_registry`
   construit son premier argument via `service((string) param('...cache'))`, ce qui
   produit littéralement la chaîne `%gesdinet_jwt_refresh_token.reuse_detection.cache%`
   comme identifiant de service recherché au lieu de résoudre la valeur réelle du
   paramètre (`cache.app`) - confirmé en reproduisant l'erreur de compilation exacte avec
   le conteneur réel. Corrigé par un compilateur pass au niveau du projet
   (`App\Shared\DependencyInjection\GesdinetReuseDetectionCachePass`, enregistré dans
   `Kernel::build()`), sans modification du code vendor.
4. Tests d'intégration nécessaires : ajoutés
   (`RefreshControllerTest::testReplayingASpentTokenRevokesTheWholeFamily`) - prouve que
   rejouer un jeton déjà consommé révoque toute la famille, y compris un jeton de
   rotation légitime jamais lui-même rejoué. `RefreshToken` implémentait déjà
   `FamilyAwareRefreshTokenInterface` (colonne `family`/`family_valid` déjà migrée) et le
   manager par défaut implémente déjà `FamilyRefreshTokenManagerInterface` - aucune
   migration de donnée supplémentaire nécessaire.

Preuve : 287/287 tests backend passants après activation, PHPStan niveau 6 sans erreur,
`composer audit` propre.

### 1.2 Durée de l'access token (Corrigé)

`token_ttl` n'était jamais explicité (valeur par défaut du bundle lexik, 3600s, jamais
lue). Rendu explicite (`config/packages/lexik_jwt_authentication.yaml`) - même principe
que la config déjà explicite du refresh token. Valeur inchangée (1h), jugée cohérente
avec un access token jamais persisté côté frontend.

### 1.3 CSRF sur `/auth/refresh` (Vérifié conforme)

Mécanisme : vérification `Origin`/`Referer` (`RefreshOriginCheckListener`) combinée à
`SameSite=Lax` sur le cookie de refresh, plutôt qu'un jeton CSRF classique - cohérent
avec `backend/CLAUDE.md` section 12. Couvre déjà les deux firewalls (tenant et Platform
Admin) depuis une revue Phase 15. Aucun changement nécessaire.

### 1.4 MFA (Vérifié conforme, limitation actée)

Implémentée uniquement pour `PlatformAdministrator` (TOTP RFC 6238). Absente pour les
comptes `User` par décision produit déjà documentée (`10-security-privacy.md:183`,
`04-product-requirements.md:143`), pas un oubli. Reprise telle quelle dans le dossier de
scope pentest comme limitation connue, jamais présentée comme un défaut à corriger avant
le pentest.

## 2. CORS

`nelmio_cors.yaml` : origine pilotée par la seule variable d'environnement
`CORS_ALLOW_ORIGIN`, interprétée comme une regex (`origin_regex: true`), jamais un
wildcard en dur dans le code. Le mécanisme lui-même n'empêche cependant pas
techniquement un opérateur de configurer une regex trop permissive en production - la
restriction "jamais de wildcard" reste une convention documentée
(`backend/CLAUDE.md` section 12), pas une garantie du code. Déjà appliqué correctement
dans les deux environnements existants (dev : `^https?://(localhost|127\.0\.0\.1)...$` ;
beta : `${BETA_PUBLIC_ORIGIN_REGEX}` injecté par variable, jamais en dur). **Signalé,
action externe requise** : la valeur de production réelle ne peut être fixée qu'une fois
le domaine de production connu (Bloc B) - à documenter explicitement dans
`.env.prod.example` au moment de l'infrastructure (Bloc A, étape 4 du plan).

## 3. Secrets

`backend/.env` (committé) audité ligne par ligne : la majorité des variables sont des
placeholders vides (`APP_SECRET`, `MAILER_DSN`, `MISTRAL_API_KEY`). Trois valeurs
hexadécimales réelles sont committées : `JWT_PASSPHRASE`, `PLATFORM_ADMIN_JWT_PASSPHRASE`,
`PLATFORM_ADMIN_TOTP_ENCRYPTION_KEY` - des valeurs de développement local, cohérentes
avec le trousseau de clés `config/jwt/` généré en local pour ce même environnement, jamais
utilisées par un environnement réel (aucun `.env.prod`/`.env.staging` n'existe encore).
**Signalé, action externe requise** : au moment du Bloc B (provisionnement réel), générer
des valeurs distinctes par environnement (staging, production) et ne jamais réutiliser
ces valeurs de dev - à vérifier explicitement avant le premier déploiement réel, pas
supposé automatique.

Aucun secret de sauvegarde (`BACKUP_GPG_PASSPHRASE`) n'existe dans le dépôt, cohérent avec
la documentation (`docker/backup/README.md`) : il n'est jamais commité, fourni uniquement
à l'invocation.

## 4. Isolation multi-tenant / IDOR / BOLA

Mécanisme centralisé (`TenantFilter`, ADR-004) déjà couvert par 9 scénarios
(`TenantIsolationTest`, TC-TENANT-001 à 009), incluant l'isolation Doctrine directe sur
`Customer`/`Invoice` (TC-TENANT-006) et `ComplianceAnalysis` (TC-TENANT-007), et la
confirmation que Platform Admin n'active jamais ce filtre par conception (TC-TENANT-009 -
la surface Platform Admin a son propre modèle d'autorisation, distinct, revu en Phase 15).

Échantillonnage complémentaire mené cette phase :

- **Document** : `CreateDocumentControllerTest`/`GetDeleteDocumentControllerTest` testent
  déjà explicitement le cas cross-tenant (référencer un `invoice_id` ou un document
  appartenant à une autre organisation → `404`, jamais `403`, cohérent avec la consigne de
  ne jamais confirmer l'existence d'une ressource d'un autre tenant).
- **Invoice** (`GetInvoiceController`, `ListInvoicesController`) : aucun fichier de test
  fonctionnel dédié à ces deux endpoints n'existe (seuls `CreateInvoiceControllerTest` et
  `UpdateInvoiceControllerTest` existent, avec leurs propres cas `404` sur ressource
  inconnue - pas spécifiquement cross-tenant). Le mécanisme sous-jacent qui les protège
  (`TenantFilter`) est néanmoins déjà prouvé au niveau Doctrine (TC-TENANT-006) et
  exercé indirectement via le cas Document ci-dessus. **Recommandation, priorité basse** :
  ajouter un test HTTP direct `GET /invoices/{id-autre-organisation}` → `404` pour
  fermer explicitement cette combinaison, plutôt que de s'appuyer uniquement sur la
  preuve indirecte - non bloquant pour cette phase, le mécanisme central étant déjà
  vérifié.
- **Organization** (`GET`/`PATCH /organizations/current`) : aucun vecteur IDOR possible
  par construction - la ressource n'est jamais adressée par un identifiant fourni par le
  client, toujours résolue depuis la session authentifiée (`CurrentOrganizationResolver`).
  Aucun test cross-tenant dédié nécessaire pour cette raison précise, pas par omission.

## 5. SAST / dépendances

- `composer audit` : aucune vulnérabilité connue (vérifié cette phase).
- `npm audit --audit-level=high` : aucune vulnérabilité connue (vérifié cette phase,
  après le correctif Next.js 16.3.3 - voir le commit séparé `hotfix/nextjs-16.3.3-avif-rce`,
  hors périmètre de ce document).
- PHPStan niveau 6 : aucune erreur.
- CodeQL : frontend (JavaScript/TypeScript) uniquement - PHP non supporté par CodeQL,
  dette déjà documentée (`10-security-privacy.md` section 68). Aucun SAST sécurité dédié
  n'existe donc pour le code PHP au-delà de PHPStan (analyse de type, pas de sécurité) et
  de la revue manuelle - **signalé, sans solution immédiate simple** : évaluer un outil
  SAST PHP dédié (ex. Psalm avec plugin sécurité, ou un service externe) reste une
  question ouverte pour une phase ultérieure si le volume de code le justifie, hors
  périmètre de cette phase de mise en production initiale.
- `@types/node` reste sur `^20` alors que Node 22 est la version réellement utilisée
  (Dockerfile, CI) - désalignement mineur de types, aucun risque de sécurité identifié.

## 6. Docker / Nginx / TLS de production

Non encore applicable : `docker-compose.prod.yml` et la configuration Nginx de
production n'existent pas encore au moment de cette revue (Bloc A, étape 4 du plan
Phase 17, postérieure à cette revue). Cette section sera complétée par une relecture
dédiée avant toute mise en ligne réelle (Bloc B), une fois ces fichiers écrits - jamais
présumée sûre par anticipation.

## Synthèse

| Point | Statut |
|---|---|
| Refresh token reuse_detection | Corrigé, testé |
| Access token TTL explicite | Corrigé |
| CSRF `/auth/refresh` | Vérifié conforme |
| MFA (limitation connue) | Vérifié conforme |
| CORS (mécanisme) | Vérifié conforme - valeur de production signalée pour le Bloc A/infra |
| Secrets committés (dev uniquement) | Vérifié conforme - rotation par environnement signalée pour le Bloc B |
| Isolation tenant / IDOR | Vérifié conforme - un test complémentaire recommandé, non bloquant |
| SAST/dépendances | Vérifié conforme - absence de SAST PHP dédié signalée, hors périmètre |
| Docker/Nginx/TLS production | Non applicable à ce stade - revue différée à l'étape 4 |

**Rappel final, non négociable** : cette revue interne, aussi approfondie soit-elle, ne
constitue pas un pentest et ne satisfait pas le critère de sortie de la Phase 17 ni celui,
distinct, du pentest ciblé Platform Admin hérité de la Phase 15 (`docs/17-pentest-scope.md`).
