# Rapport de validation MVP - Phase 11

> Distinct de `docs/12-roadmap.md` : la roadmap documente ce qui a été construit phase par
> phase ; ce rapport documente si le MVP fonctionne réellement et quelles preuves l'attestent.
> Le verdict final ne s'appuie que sur des preuves citées (test, run, capture) - jamais une
> supposition, conformément à `CLAUDE.md`.

## 1. Périmètre MVP

Parcours validé (`docs/12-roadmap.md`, section 6) : créer un compte → configurer son
entreprise (statut TVA, taille) → obtenir un diagnostic d'éligibilité → créer un client →
saisir/importer une facture → lancer une analyse de conformité → comprendre le résultat et
chaque problème détecté → corriger et relancer l'analyse. Périmètre MVP repris de la section
7 de la roadmap - aucune émission ou transmission réelle de facture, aucune fonctionnalité
hors périmètre (comptabilité, paie, CRM, paiement intégré).

## 2. Environnement de validation

- Pile complète : `docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d`
  (nginx, backend Symfony, frontend Next.js, PostgreSQL, Redis, worker Messenger, Mustang,
  Mailpit - ce dernier ajouté par l'overlay `docker-compose.e2e.yml`, test-only, jamais en
  production).
- Frontend servi en `next dev` (target `dev` de `frontend/Dockerfile`), jamais un build de
  production : `next build` échoue de façon déterministe sur Next.js 16.0.x-16.3.1 (bug amont
  documenté, `frontend/CLAUDE.md` section 2, vercel/next.js#86178 et associés). **Ce rapport
  valide donc le MVP tel qu'il tourne réellement en développement/CI aujourd'hui, pas un
  déploiement en build de production** - voir section 10 (Limites connues).
- Backend en `APP_ENV=dev` (comme `docker-compose.yml` de base) - pas d'environnement de
  staging distinct (non encore choisi, `docs/12-roadmap.md` section 27 : Staging = Phase
  10-11 sur le papier, en pratique toujours Phase 13 faute d'hébergeur retenu, cohérent avec
  le bilan Phase 10).
- Navigateur : Chromium uniquement (Playwright, décision produit validée en revue de plan
  Phase 11).

## 3. Résultats des 6 parcours E2E (`docs/09-test-strategy.md`, section 38)

| Parcours | Statut | Preuve |
| --- | --- | --- |
| E2E-001 (onboarding) | **Vert** | `frontend/e2e/specs/e2e-001-onboarding.spec.ts`, exécuté avec succès |
| E2E-002 (facture conforme) | **Vert** | `frontend/e2e/specs/e2e-002-compliant-invoice.spec.ts` |
| E2E-003 (non conforme → corrigée) | **Vert** | `frontend/e2e/specs/e2e-003-non-conforme-corrected.spec.ts` |
| E2E-004 (import PDF simple → finding format) | **Vert** | `frontend/e2e/specs/e2e-004-document-format.spec.ts` |
| E2E-005 (isolation multi-tenant, deux contextes navigateur) | **Vert** | `frontend/e2e/specs/e2e-005-tenant-isolation.spec.ts` |
| E2E-006 (non-rétroactivité d'une version de règle) | **Vert (déjà clos en Phase 9)** | `backend/tests/Functional/Compliance/RuleVersionNonRetroactivityTest.php` - `docs/12-roadmap.md` section 25 documente explicitement pourquoi ce parcours a été validé au niveau backend avant le reste du socle E2E |

Les 5 parcours restants (E2E-001 à E2E-005) ont été exécutés **ensemble, dans une seule
exécution Playwright** (`npx playwright test`, pile complète) : 5 passed en 1.1 minute. Chaque
parcours pilote l'UI réelle de bout en bout (navigateur → Next.js → API Symfony →
PostgreSQL/Redis/Mustang → retour UI) - jamais d'appel API direct pour contourner une étape
testée. **Confirmé en CI réelle** (pas seulement en local) : job `e2e` de
`.github/workflows/lint.yml`, PR #10, vert en 5m41s
(https://github.com/Joello61/factu-sentinel/actions/runs/32450291546/job/96677411430) - voir
anomalie 5 (section 8) pour le problème d'environnement CI découvert et corrigé au premier run.

**Comportement observé, non un défaut** : exécuter la suite plusieurs fois de suite en moins
de 15 minutes depuis la même machine peut déclencher le rate limiter IP `login_throttling`
(`backend/config/packages/security.yaml`, 5 tentatives/15 min par couple username+IP, 25/15
min par IP - comportement par défaut de Symfony, confirmé contre la documentation officielle).
Une exécution CI fraîche (5-7 connexions par run) reste très en dessous de ce plafond ; ce
comportement n'est apparu que lors des multiples relances manuelles effectuées pendant le
développement de cette phase, jamais lors d'une exécution unique et propre. Le pool de cache
`cache.rate_limiter` a été vidé une fois dans l'environnement de développement local
(`php bin/console cache:pool:clear cache.rate_limiter`, conteneur `backend` de
`docker-compose.e2e.yml`) pour débloquer la vérification finale de cette phase après ces
relances répétées - action locale, sans effet sur `main`/CI/production, jamais un contournement
du contrôle de sécurité lui-même (`login_throttling` reste actif et inchangé dans le code).

## 4. Résultats accessibilité

**Automatisé (`@axe-core/playwright`, tags `wcag2a`/`wcag2aa`/`wcag22aa`)** : scans intégrés
aux specs E2E sur les pages critiques (email vérifié, page entreprise + diagnostic, détail
facture avant/après analyse, résultat de conformité) - **0 violation** après correction (voir
section 8, anomalie 2). Ne couvre qu'un sous-ensemble de critères automatisables.

**Navigation clavier scriptée** : E2E-001 vérifie que le bouton d'enregistrement du formulaire
entreprise est atteignable au Tab et activable au clavier (Entrée). Les parcours de
correction (E2E-003) utilisent exclusivement des locators accessibles (`getByLabel`,
`getByRole`), preuve indirecte que labels/rôles sont correctement exposés.

**Manuel - non effectué dans cette session** : revue au lecteur d'écran, jugement fin de
contraste au-delà de ce qu'axe calcule, exploration clavier libre au-delà des chemins déjà
scriptés. **Ce rapport n'affirme donc pas une conformité WCAG 2.2 AA complète** - seulement
l'absence de violation détectée par le sous-ensemble automatisable sur les pages scannées, cohérent avec la distinction demandée en revue de plan (« axe passe » ≠ « WCAG 2.2 AA conforme
à 100 % »). Reste à faire avant une certification d'accessibilité complète.

## 5. Résultats Design QA (`docs/11-frontend-design-system.md`, section 66)

Passage de la checklist sur le sous-ensemble MVP du Page Inventory (Connexion/Inscription,
Vérification email, Configuration entreprise, Diagnostic, Clients, Factures, Détail
facture/Résultat, Historique, Dashboard) :

| Item | Statut | Note |
| --- | --- | --- |
| Responsive (mobile/tablette/desktop) | Conforme | Voir ci-dessous (tableaux → cartes) |
| Navigation clavier complète | Vérifié (E2E) | Section 4 |
| États de focus visibles | Vérifié (axe + revue de code, `focus-visible:ring-2` systématique) | - |
| États d'erreur (technique vs conformité) | Conforme | `role="alert"` distinct du badge `ComplianceResultBadge`, jamais confondus (revue de code) |
| États de chargement | Conforme | "Chargement…" explicite sur chaque vue asynchrone |
| Empty states | Conforme | Dashboard `AUCUNE_ANALYSE`, listes vides avec message explicite |
| Accessibilité (contrastes, labels, ARIA) | Vérifié pour les pages scannées | Section 4 |
| Espacement/typographie | Conforme (revue de code, tokens Tailwind) | - |
| Couleurs sémantiques vs conformité | Conforme, jamais confondues (revue de code) | - |
| Comportement mobile (tableaux) | Conforme | Voir ci-dessous |
| États de permission | Conforme (404 systématique cross-tenant, jamais un message de permission - vérifié E2E-005) | - |
| IA visuellement distincte | Conforme (revue de code, `ComplianceFindingCard.tsx` : bandeau "Explication assistée" dédié) | - |

**Gap fermé dans cette phase** : `InvoiceList.tsx`, `CustomerList.tsx` et
`ComplianceAnalysisHistory.tsx` utilisaient un tableau dans un conteneur `overflow-x-auto` sans
transformation en liste de cartes sur mobile, contrairement à la règle explicite de la section
24 du design system (« jamais une table horizontale scrollable illisible par défaut »).
Identifié une première fois lors de la revue de plan (revue de code uniquement, pas de preuve
visuelle - l'outil de redimensionnement de fenêtre du navigateur ne s'est pas montré fiable
dans cet environnement), puis explicitement demandé comme correction à part entière plutôt
que comme dette différée. Chaque page expose désormais deux rendus : le tableau existant,
inchangé, sous `hidden sm:block`, et une nouvelle liste de cartes sous `sm:hidden`, reprenant
les colonnes prioritaires (numéro/nom, statut ou résultat, montant ou date), chaque carte
entièrement cliquable. Vérifié par lecture directe du DOM/CSS calculé dans un navigateur réel
(`getComputedStyle`, `display: none` sur la liste à largeur desktop, `display: block` sur le
tableau - confirmé programmatiquement, pas seulement supposé) et par les 5 specs E2E, qui
continuent de passer avec les deux rendus désormais présents simultanément dans le DOM
(`filter({ visible: true })` ajouté aux locators concernés, seule façon fiable de désambiguïser
deux copies du même contenu dont une seule est visible selon le viewport réel - `getByText` ne
filtre pas par visibilité contrairement à `getByRole`, comportement Playwright documenté).
Aucune vérification visuelle réelle à un viewport mobile effectif (< 640px) n'a en revanche pu
être obtenue dans cette session : l'outil de contrôle navigateur disponible n'a jamais permis
de changer effectivement la largeur de rendu malgré plusieurs tentatives. La preuve retenue est
donc la vérification programmatique du CSS calculé, pas une capture d'écran à largeur mobile
réelle - point à confirmer visuellement dès qu'un environnement le permettra.

## 6. Résultats sécurité

- `composer audit` / `npm audit --audit-level=high` : aucune vulnérabilité (backend inchangé
  depuis la Phase 10 ; frontend re-testé après ajout de `@playwright/test`/`@axe-core/playwright`,
  0 vulnérabilité).
- `TenantIsolationTest` (backend) inchangé et toujours vert ; **complété** par E2E-005, qui
  vérifie pour la première fois l'isolation cross-tenant **au niveau du rendu frontend réel**
  (ressource d'une autre organisation → page "introuvable", jamais un message de permission).
- gitleaks/CodeQL (jobs GitHub Actions dédiés) : **confirmés verts sur la PR #10**, run réel
  sur push de cette branche - aucun secret détecté, aucune alerte CodeQL JS/TS.
- Décision pentest (`10-security-privacy.md` section 61) : **DL-011** (`docs/12-roadmap.md`
  section 50) - non requis avant la Private Beta, requis avant la Phase 13.
- Production Security Checklist (`10-security-privacy.md` section 68) : aucune case rouverte
  cette phase n'a régressé ; les items déjà `DIFFÉRÉ - Phase 13 - nécessite une infrastructure
  hébergée` le restent (aucune infrastructure inventée pour les cocher).
- Comportement du rate limiter `login_throttling` observé et documenté (section 3) - contrôle
  de sécurité fonctionnant comme prévu (SEC-AUTH-002), pas une régression.

## 7. Résultats backend/frontend

- **Backend** : aucun fichier sous `backend/` modifié en Phase 11 (`git diff --stat` sur la
  branche `phase-11/mvp-validation`) - suite de 201 tests inchangée depuis le bilan Phase 10
  (`docs/12-roadmap.md`). Backend exercé uniquement en tant que cible des parcours E2E.
- **Frontend** : `npm run lint` (0 erreur), `npx tsc --noEmit` (0 nouvelle erreur - les deux
  erreurs préexistantes, `.next/types/validator.ts` et `CompanyForm.test.tsx`, confirmées
  antérieures à cette phase par comparaison avec `git stash`, hors périmètre), `npm run test`
  (Vitest, 74/74 tests verts, 17 fichiers), `npx playwright test` (5/5 specs vertes).
- **CI (PR #10)** : les 6 checks sont verts - `Backend (PHP)`, `Frontend (Next.js)`,
  `CodeQL`, `CodeQL (JavaScript/TypeScript)`, `Secret scanning (gitleaks)`, `E2E (Playwright)`.

## 8. Anomalies découvertes

1. **`frontend/proxy.ts`** ne listait pas `/verify-email` dans ses chemins publics : tout
   utilisateur cliquant son lien de vérification d'email sans session active (cas nominal,
   US-AUTH-001) était silencieusement redirigé vers `/login` sans jamais vérifier son
   compte. La page `/verify-email/[id]` elle-même **n'existait pas encore**, alors que son
   contrat est spécifié depuis la Phase 2 (`docs/08-api-specification.md`, section 7).
   Découvert en pilotant réellement le lien reçu par email via Mailpit (E2E-001) - un test
   API seul n'aurait rien révélé, la page frontend n'étant jamais sollicitée par un test
   backend.
2. **4 des 6 couleurs sémantiques** (`success`, `warning`, `error`, `info`) échouaient au
   ratio de contraste WCAG 2.2 AA (4.5:1) dans leurs deux contextes d'usage réels (alerte à
   10 % d'opacité de fond, badge à 15 %) - jusqu'à 2.64:1 pour `warning`. Découvert par un
   scan `@axe-core/playwright` automatisé sur E2E-001.
3. Dérive documentaire : `docs/09-test-strategy.md` section 52 indiquait encore
   `format-facture-electronique` à `confidence_level = MOYEN`, alors que la Phase 7 l'avait
   déjà fait passer à `ÉLEVÉ` (v2). Corrigé (section 6 de ce rapport).
4. Gap Design QA (tableaux non transformés en cartes sur mobile, section 24 du design
   system) - identifié par revue de code, corrigé dans cette même phase sur demande explicite
   (voir section 5 et section 9, anomalie 4).
5. **Job CI `e2e` en échec au premier run réel** (PR #10) : `npm ci` échouait avec `EACCES`
   sur `frontend/node_modules`. Cause : le service `frontend` monte `./frontend:/app` en bind
   mount, et sur les runners GitHub-hosted ce chemin hôte est le même répertoire de travail
   que celui du job - un conteneur (root) écrivant dans `/app` avant que `npm ci` ne
   s'exécute côté hôte y laissait des fichiers appartenant à root. Invisible en local (Docker
   Desktop y isole différemment le montage), donc uniquement détectable par un run CI réel -
   raison précise pour laquelle ce rapport ne présume jamais qu'un job non exécuté en CI
   passerait.

Uniquement l'anomalie 5 a été découverte après la rédaction initiale de ce rapport, sur le
premier run CI réel de la PR #10 - corrigée avant la version présentée ici (section 9).

## 9. Anomalies corrigées

Les anomalies 1 et 2 (P0/Critical au sens `09-test-strategy.md` section 44 - un P0 réel du
produit, jamais un simple ajustement de test) ont été corrigées dans cette branche, jamais
contournées dans un test :

- `frontend/proxy.ts` : nouvelle catégorie `ALWAYS_ACCESSIBLE_PATHS`, distincte de
  `PUBLIC_PATHS` (qui redirige un compte déjà connecté) - `/verify-email` reste accessible
  quel que soit l'état de session.
- `frontend/app/(public)/verify-email/[id]/page.tsx` + `VerifyEmailView.tsx` : nouvelle page,
  relaie les paramètres signés tels quels vers `GET /api/v1/auth/verify-email/{id}` (contrat
  déjà documenté), jamais reconstruits champ par champ.
- `frontend/app/globals.css` : les quatre tokens de couleur assombris avec une marge réelle
  (jamais au seuil pile) ; `docs/11-frontend-design-system.md` mis à jour en conséquence
  (section 5, 70).
- L'anomalie 3 (dérive documentaire) a été corrigée directement dans la documentation
  (section 6).
- **Anomalie 4 (gap Design QA)** : initialement consignée comme dette volontairement différée
  (cohérent avec le principe « ne jamais inventer de fonctionnalité pour fermer un écart de
  checklist »), puis explicitement demandée comme correction à part entière par décision
  produit - fermée dans cette même phase (voir section 5 pour le détail technique et les
  limites de vérification).
- **Anomalie 5 (EACCES en CI)** : `.github/workflows/lint.yml`, job `e2e` - `npm ci` et
  l'installation de Playwright déplacés avant le build/démarrage de la pile Docker, pour que
  `frontend/node_modules` soit créé proprement par l'utilisateur du runner avant qu'un
  conteneur ne puisse toucher au même chemin hôte. Reconfirmé vert sur un second run CI
  (section 3, section 7).

## 10. Limites connues

- `next build` reste cassé (bug amont Next.js 16.0.x-16.3.1, non lié à ce projet) : ce
  rapport valide le MVP tel qu'il tourne en `next dev`, pas un build de production. Point à
  ré-évaluer avant la Phase 13 (soit le bug amont sera corrigé, soit une décision de stack
  explicite sera nécessaire - jamais silencieuse).
- Chromium uniquement en E2E (décision produit) - Firefox/Safari desktop et mobile
  (`09-test-strategy.md` section 40) non couverts par l'automatisation, à valider
  manuellement si un signal réel le justifie.
- Performance non testée cette phase (non bloquant au MVP, `09-test-strategy.md` section 45).
- Revue d'accessibilité manuelle (lecteur d'écran) non effectuée cette phase - voir section 4.
- Vues carte mobile (section 5, section 9) vérifiées par CSS calculé (`getComputedStyle`)
  dans un navigateur réel, jamais par une capture d'écran à une largeur mobile réelle - l'outil
  de contrôle navigateur disponible dans cette session n'a jamais permis de changer
  effectivement la largeur de rendu malgré plusieurs tentatives. À confirmer visuellement dès
  qu'un environnement le permettra.

## 11. Verdict MVP

**MVP validé pour l'entrée en Private Beta (Phase 12)**, sur la base des preuves ci-dessus.
Tous les Release Gates bloquants de `09-test-strategy.md` (section 45) et
`10-security-privacy.md` (section 62) sont au vert, **confirmés par un run CI réel** (PR #10,
tous les checks verts) et non seulement en local : build (backend inchangé ; frontend testé
en topologie `next dev`, seule topologie réellement utilisée à ce stade - voir limite
ci-dessus), tests unitaires/intégration/API/multi-tenant (inchangés, backend non modifié),
tests de sécurité de niveau critique (audits dépendances, gitleaks, CodeQL tous verts en CI),
déterminisme (Compliance Engine non modifié), **E2E critiques (les 5 parcours restants, verts
pour la première fois via un navigateur réel, en local et en CI)**, régression réglementaire
(aucune règle modifiée cette phase). Les deux gates non bloquants (performance,
accessibilité) restent explicitement "surveillés", pas complets - cohérent avec leur statut
documenté, jamais présentés comme achevés.

Ce verdict ne s'étend pas à la Phase 13 (mise en production commerciale) : `next build`
cassé, environnements staging/production inexistants, et l'ensemble des points RGPD/juridiques
de `10-security-privacy.md` section 68 restent hors périmètre de cette phase, comme documenté
depuis la Phase 10.

## 12. Preuves / références CI

- Specs E2E : `frontend/e2e/specs/e2e-00{1,2,3,4,5}-*.spec.ts`.
- Overlay E2E : `docker-compose.e2e.yml`.
- Nouveau job CI : `.github/workflows/lint.yml`, job `e2e` (rapport Playwright HTML téléchargé
  en artefact CI sur chaque run).
- PR : https://github.com/Joello61/factu-sentinel/pull/10 - 6/6 checks verts (Backend,
  Frontend, CodeQL, CodeQL JS/TS, gitleaks, E2E).
- Run CI E2E de référence :
  https://github.com/Joello61/factu-sentinel/actions/runs/32450291546/job/96677411430
  (5m41s, après correction de l'anomalie 5).
- Décisions produit : `docs/12-roadmap.md`, DL-011 (section 50), bilan Phase 11 (section 10).
- Correctifs : voir section 9 de ce rapport pour les fichiers modifiés.
