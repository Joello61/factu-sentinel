@AGENTS.md

# CLAUDE.md - Frontend (Next.js)

Ce fichier complète `../CLAUDE.md` (règles générales du projet FactuSentinel). **Il ne les recopie pas.** Tout ce qui n'est pas spécifique au frontend (pas d'emoji, pas de tiret cadratin, pas de co-auteur Claude dans les commits, vérification Internet en principe, positionnement produit, réglementation, etc.) s'applique ici sans modification - voir `../CLAUDE.md`.

Le frontend Next.js est responsable de **l'expérience utilisateur**, jamais de l'autorité métier, réglementaire ou d'autorisation - celle-ci appartient exclusivement au backend Symfony (`../backend/CLAUDE.md`). Le frontend ne détermine jamais lui-même une conformité, un statut d'accès, ou une règle réglementaire.

## 1. Sources de vérité spécifiques au frontend

| Document                               | Ce qu'il définit pour le frontend                                                |
| -------------------------------------- | -------------------------------------------------------------------------------- |
| `../docs/04-product-requirements.md`   | Fonctionnalités, parcours utilisateurs, gestion des erreurs (section 15)         |
| `../docs/05-user-stories.md`           | User Stories, Epics, six états de conformité (section 8), critères d'acceptation |
| `../docs/06-technical-architecture.md` | Frontend Architecture (section 28), communication frontend/backend (section 29)  |
| `../docs/07-data-model.md`             | Formes de données consommées (entités, enums, statuts)                           |
| `../docs/08-api-specification.md`      | Contrats API - seule source pour un endpoint, un payload ou un code d'erreur     |
| `../docs/09-test-strategy.md`          | E2E, accessibilité, compatibilité navigateurs                                    |
| `../docs/10-security-privacy.md`       | Contraintes de sécurité côté client                                              |
| `../docs/11-frontend-design-system.md` | Design system complet - palette, composants, patterns, contenu, accessibilité    |
| `../docs/12-roadmap.md`                | Séquencement des écrans et fonctionnalités (section 17, Frontend Roadmap)        |

Note sur les noms de documents : ce dépôt ne contient pas de fichier `08-test-strategy.md` distinct - la stratégie de test réelle est `09-test-strategy.md` (celui utilisé ci-dessus). Les fonctionnalités précises à construire (onboarding, diagnostic, entreprise, upload, analyse, résultats, dashboard, paramètres, IA) ne sont pas listées ici : elles se déduisent de `05-user-stories.md` (Epics, section 4) et `11-frontend-design-system.md` (Page Inventory, section 59) - ne pas les redéfinir depuis ce fichier.

## 2. État réel du projet et stack

Projet Next.js généré, non encore développé au-delà du squelette par défaut (`app/page.tsx`, `app/layout.tsx`). Versions réellement installées (vérifier à nouveau si ce fichier vieillit) : **Next.js 16.3.1**, **React 19.2.8**, **TypeScript ^5**, **Tailwind CSS v4** (via `@tailwindcss/postcss`). Aucune bibliothèque UI complémentaire n'est encore installée alors que `11-frontend-design-system.md` (section 57, 70) acte **Radix UI** pour les primitives accessibles et **Lucide React** pour les icônes - à installer au moment de l'implémentation, en vérifiant leur compatibilité avec React 19 et Next 16 avant de les ajouter.

**Next.js 16 est une version majeure récente** : ne pas supposer que des patterns d'une version antérieure (12-15) restent valides - routing, Server/Client Components, data fetching et caching ont significativement évolué au fil des versions majeures de Next.js. Le fichier `frontend/AGENTS.md`, généré automatiquement par `next dev`, rappelle déjà explicitement ce point et renvoie vers `node_modules/next/dist/docs/` - le traiter comme un signal cohérent avec cette règle, pas comme une source suffisante à elle seule (elle documente la version installée localement, une vérification officielle en ligne reste nécessaire pour le contexte et les évolutions récentes). Convention également actée en 16.0 : `middleware.ts` est déprécié au profit de `proxy.ts` (export nommé `proxy`) - ne pas recréer un `middleware.ts` par réflexe issu d'une version antérieure.

**Limitation connue de `next build` (Next.js 16.0.x à 16.3.1, dernière stable vérifiée le 19/08/2026)** : la commande échoue de façon déterministe lors de la pré-génération de la page interne `/_global-error` (`TypeError: Cannot read properties of null (reading 'useContext')`), y compris sur une application sans provider ni composant custom - bug amont non résolu (vercel/next.js#86178, #84994, #95741), reproduit indépendamment du bundler (`--turbopack` par défaut ou `--webpack`, tous deux affectés) et vérifié comme non lié au code de ce projet (isolé en testant le code d'avant Phase 2, identique). `next dev` (utilisé par `docker-compose.yml`) n'est pas affecté ; seul le stage `builder` de `frontend/Dockerfile` (image de production) l'est - voir le commentaire à cet endroit. Ne pas retenter de corriger ce point sans revérifier d'abord si une version stable plus récente de Next.js le résout.

## 3. Architecture frontend

Organisée autour des Epics (`05-user-stories.md` section 4), pas un miroir un-à-un des modules backend (`06-technical-architecture.md`, section 28) : Auth, Onboarding, Company, Customers, Invoices, Compliance, Documents, Dashboard, AI Assistant, Notifications, Settings.

Flux attendu, controllers/pages minces :

```text
Page / Feature → Application logic → API client → Backend
```

La logique réglementaire n'existe jamais côté frontend - un composant affiche un résultat déjà produit par le Compliance Engine, il ne recalcule, ne devine, ni n'infère jamais lui-même une conformité. State management à distinguer clairement (`06-technical-architecture.md`, section 28) : état de session (authentification), état des données métier (entreprise, factures, résultats), état des traitements en cours (`NON_ANALYSEE`, `ANALYSE_EN_COURS` - voir section 8).

## 4. API

`08-api-specification.md` est la source de vérité. Ne jamais inventer un endpoint ; vérifier le contrat, les types, les codes d'erreur, l'authentification et les états asynchrones avant d'utiliser une API. Centraliser les appels HTTP dans un client API unique - jamais d'appel `fetch` brut dispersé dans les composants.

Conventions du contrat à respecter dans le client API : enveloppe `{ "data", "meta" }` en succès, `{ "error": { "code", "message", "details", "request_id" } }` en erreur - ce contrat d'erreur n'est **jamais** utilisé pour un résultat `NON_CONFORME` (voir section 8) ; le tenant n'apparaît jamais dans l'URL ni n'est envoyé dans un payload (résolu côté serveur depuis la session) ; montants reçus en chaînes décimales, jamais parsés en flottant pour un calcul côté client puis renvoyés arrondis différemment ; dates ISO 8601 UTC en provenance de l'API, converties au fuseau et au format `fr-FR` uniquement à l'affichage (section 10) ; `Idempotency-Key` à générer côté client pour `POST /invoices`, `POST /invoices/{id}/compliance-analyses`, `POST /documents` ; `If-Match`/`ETag` à transmettre sur `PATCH /invoices/{id}`, avec gestion explicite du `409 Conflict` (concurrence, distinct de la transition `ANALYSIS_STALE`, qui est un `200 OK` normal - voir section 8).

Traitement asynchrone : `POST /invoices/{id}/compliance-analyses` et `POST /documents` peuvent répondre `202 Accepted` avec un `status_url` - le client interroge cette ressource par polling jusqu'à `COMPLETED`/`FAILED` ou `VALIDATED`/`FAILED` (pas de WebSocket au MVP, `06-technical-architecture.md` section 29). Toute valeur d'énumération inconnue reçue de l'API doit être traitée avec un comportement de repli explicite (jamais un crash) - l'ajout d'une nouvelle valeur d'enum est un changement non cassant côté contrat (`08-api-specification.md` section 19).

## 5. Authentication

Le mécanisme est défini par le backend (`../backend/CLAUDE.md` section 8) : access token JWT conservé **en mémoire côté frontend uniquement** (jamais `localStorage` ni `sessionStorage`), refresh token en cookie `HttpOnly`/`Secure`/`SameSite` géré automatiquement par le navigateur et invisible en JavaScript. Ne jamais tenter de lire, stocker ou manipuler le refresh token côté client - son inaccessibilité en JavaScript est une propriété de sécurité voulue, pas une limitation à contourner.

Avant toute implémentation ou modification de la gestion d'authentification (état de session, redirection, protection de route) : vérifier l'architecture actée côté backend, consulter la documentation officielle Next.js actuelle pour la version installée (routing, layouts, middleware/proxy - la terminologie et les mécanismes ont évolué entre versions majeures), vérifier les recommandations de sécurité actuelles pour la gestion de jetons côté client. Ne jamais appliquer une méthode de stockage de JWT connue par ailleurs sans l'avoir vérifiée pour ce contexte précis.

## 6. Authorization

Toute protection frontend (route protégée, bouton caché, condition de rendu) est un **confort d'expérience uniquement**, jamais un contrôle de sécurité réel - le backend revalide systématiquement (`10-security-privacy.md` section 43, `11-frontend-design-system.md` section 43). Ne jamais coder une fonctionnalité en supposant que masquer un élément à l'écran suffit à empêcher une action.

Un accès refusé côté API pour une ressource inexistante ou appartenant à une autre organisation revient toujours en `404` - le frontend l'affiche comme une ressource introuvable (« Cette facture n'existe pas ou n'est plus disponible »), jamais comme un message évoquant une permission manquante, pour ne jamais laisser deviner l'existence de la ressource chez un autre tenant (`11-frontend-design-system.md` section 41).

## 7. Asynchronous processing

Redis/Messenger vivent côté backend uniquement. Le frontend expose leur résultat via polling sur les ressources concernées (section 4). États de progression à toujours nommer explicitement, jamais un spinner générique indéfini (`11-frontend-design-system.md` section 36) : import du document → traitement en cours → analyse de conformité en cours → terminé. Ne jamais utiliser d'UI optimiste pour le résultat d'une analyse de conformité - le résultat n'est jamais présumé avant confirmation du Compliance Engine (`11-frontend-design-system.md` section 36).

## 8. Compliance UI

Principe directeur repris de `../CLAUDE.md` : **« Pourquoi, jamais seulement si. »** Un résultat n'est jamais affiché comme un simple `Conforme`/`Non conforme` binaire lorsque le backend fournit davantage (règle, source, explication, conséquence, action de correction) - présenter systématiquement, dès que disponibles, statut, règle, explication, source, conséquence et correction recommandée (progressive disclosure en 3 niveaux, `11-frontend-design-system.md` section 28).

Six états de résultat, jamais un binaire (`05-user-stories.md` section 8) : `CONFORME`, `NON_CONFORME`, `AVERTISSEMENT`, `NON_APPLICABLE`, `A_VERIFIER`, `INCERTAIN_REGLEMENTAIRE` - plus deux états de traitement distincts d'un résultat : `NON_ANALYSEE`, `ANALYSE_EN_COURS`. Chaque état est systématiquement rendu avec **couleur + icône + label**, jamais la couleur seule (accessibilité, daltonisme - `11-frontend-design-system.md` section 5-6). `A_VERIFIER` n'est jamais visuellement traité comme une erreur (donnée manquante, pas un problème constaté) ; `INCERTAIN_REGLEMENTAIRE` signale explicitement l'incertitude, jamais masquée sous un ton assuré.

**Règle absolue** : ne jamais mélanger visuellement un résultat `NON_CONFORME` et une **erreur technique** - couleurs, icônes, libellés et actions proposées strictement distincts (corriger une donnée vs réessayer/contacter le support). Un `NON_CONFORME` s'intègre au flux normal de l'interface (badge de statut), il n'est jamais présenté dans un encart d'erreur système (`11-frontend-design-system.md` sections 27, 37).

Wording : ne jamais affirmer plus que ce que garantit le résultat du moteur (proscrit : « Votre facture est légalement parfaite » ; préférer : « Aucun problème détecté sur les points vérifiés ») - `11-frontend-design-system.md` section 50.

## 9. AI UI

Distinction visuelle systématique et permanente entre trois niveaux, jamais confondus : règle réglementaire (source `02-regulatory-study.md`) ≠ résultat du Compliance Engine (déterministe) ≠ explication de l'assistant IA (reformulation). Le texte produit par l'IA est toujours visuellement étiqueté distinctement (par exemple « Explication assistée »), jamais avec la même apparence que le message par défaut d'un `ComplianceFinding` (`11-frontend-design-system.md` sections 30-31).

Déclenchement à la demande de l'utilisateur uniquement, jamais une reformulation automatique systématique. En cas d'échec ou d'indisponibilité (`503`), replier immédiatement vers le message par défaut du finding (`ComplianceFinding.message`) - l'utilisateur ne doit jamais se retrouver sans aucune explication. Une question libre à l'assistant est visuellement délimitée du résultat de conformité lui-même, pour ne jamais laisser penser qu'elle le modifie. L'IA n'est jamais présentée comme une autorité juridique.

## 10. Design System

`11-frontend-design-system.md` est la source de vérité - ne pas inventer de direction visuelle alternative. Décisions déjà actées à respecter, pas à redécider :

- Palette : `Primary #00695C`, `Success #2E7D32`, `Warning #ED6C02`, `Error #D32F2F`, `Info #0288D1` - contraste WCAG à valider à l'implémentation, mais les valeurs elles-mêmes ne sont pas à renégocier sans décision produit explicite. Les couleurs de conformité (section 5 du design system) restent un système **distinct** des couleurs sémantiques génériques, même si des teintes de base sont proches.
- Typographie : **Inter**, fallback `system-ui, sans-serif` ; chiffres tabulaires pour tout montant/TVA.
- UI : **Tailwind CSS v4** + composants internes + **Radix UI** (primitives accessibles) + **Lucide React** (icônes) - pas de bibliothèque de composants imposant son propre design visuel.
- Dark mode : **non MVP**, mais les tokens doivent rester structurés pour un ajout futur (ne pas coder une couleur en dur hors du système de tokens).
- Graphiques : **aucun au MVP** - dashboard limité à KPI simples, listes, alertes, actions.
- Langue/devise : **français uniquement** (`fr-FR`, EUR) - textes jamais codés en dur dans la structure visuelle (prévoir une longueur variable), pour rester compatible avec un ajout futur de langue.
- Accessibilité cible : **WCAG 2.2 AA** (section 44) - voir section 14 de ce fichier.

Un composant n'est créé que lorsqu'un pattern se répète au moins deux fois de façon identique ; sinon composer les primitives existantes (`11-frontend-design-system.md` section 67). Ne jamais transformer un tableau dense en grille de cards par réflexe sur desktop ; sur mobile, transformer un tableau en cartes priorisées plutôt que forcer un défilement horizontal (section 24).

## 11. TypeScript

Strict, éviter `any` - ne jamais l'utiliser simplement pour supprimer une erreur de type. Modéliser les six états de conformité et les statuts de traitement comme des types/enums stricts (union de littéraux ou discriminated union), jamais une simple `string`, pour que le compilateur signale un état non géré dans un `switch`. Typer les réponses API à partir du contrat de `08-api-specification.md`, pas en inférant les types depuis une réponse observée une fois.

## 12. React / Next.js

Utiliser les patterns actuellement recommandés par la documentation officielle pour la version installée (Next.js 16.3.1, React 19.2.8) - ne jamais supposer qu'un pattern Next.js connu par ailleurs reste recommandé. Vérifier la documentation officielle actuelle avant d'utiliser : Server Components, Client Components, Server Actions, routing (App Router), data fetching, caching, revalidation, middleware/proxy, metadata, image optimization. Ces mécanismes ont changé de comportement à plusieurs reprises entre les versions majeures de Next.js - un exemple correct en mémoire pour une version antérieure peut être silencieusement incorrect ou sous-optimal pour la version 16.

## 13. Tailwind CSS

Le projet utilise **Tailwind CSS v4**, dont la configuration et certaines conventions diffèrent significativement de la v3 (configuration CSS-first notamment). Avant de modifier la configuration Tailwind ou d'utiliser une fonctionnalité avancée : consulter la documentation officielle Tailwind v4 actuelle, vérifier la syntaxe actuelle plutôt que de reproduire un pattern v3 par habitude.

## 14. Accessibility

Cible **WCAG 2.2 AA** (`11-frontend-design-system.md` section 44, `09-test-strategy.md` section 39), non négociable pour les parcours critiques (consultation d'un résultat de conformité et de son explication en priorité).

Respecter systématiquement : HTML sémantique, labels toujours visibles et associés programmatiquement (jamais un placeholder seul), navigation clavier complète sans exception, focus visible en permanence (jamais supprimé par un reset de style), gestion explicite du focus (ouverture/fermeture de modale, changement de page vers le H1, premier champ en erreur après une soumission invalide), `aria-live` pour les notifications non bloquantes sans déplacer le focus, contrastes conformes, `prefers-reduced-motion` respecté pour toute animation non essentielle.

## 15. Forms

Gérer systématiquement : validation (au blur/à la soumission, pas à chaque frappe), erreurs (message sous le champ concerné, formulé en indiquant quoi corriger - jamais un simple « champ invalide », cohérent avec le format `field`/`issue` de `08-api-specification.md` section 15), état de chargement (bouton désactivé pendant la soumission pour prévenir la double soumission, cohérent avec l'idempotence), succès (discret), accessibilité (section 14). La validation frontend améliore l'expérience mais ne remplace jamais la validation backend, qui reste la source de vérité.

## 16. Security

Suivre `10-security-privacy.md` pour ce qui s'applique côté client. Ne jamais mettre de secret dans le frontend (clé API, credential) - tout ce qui est envoyé au navigateur doit être considéré comme public. Échapper systématiquement toute donnée utilisateur réaffichée (nom de client, description de ligne de facture) - vecteur XSS potentiel. Ne jamais construire une URL de téléchargement de document à partir d'une donnée non revalidée par le backend. Traiter tout document comme confidentiel par défaut, jamais de lien de partage public généré sans contrôle explicite (`11-frontend-design-system.md` section 63).

## 17. Performance

Surveiller bundle size, requêtes API dupliquées (particulièrement lors du polling asynchrone, section 7 - éviter les appels redondants entre composants), re-rendus inutiles, optimisation d'images, listes volumineuses (pagination cohérente avec `08-api-specification.md` section 41), stratégie de cache/revalidation. Avant toute optimisation Next.js spécifique (streaming, prefetching, cache), vérifier les recommandations actuelles pour la version installée plutôt qu'une technique qui aurait été recommandée dans une version antérieure.

## 18. Tests

Suivre `09-test-strategy.md`. Composants, formulaires, services et client API à tester au niveau unitaire/intégration. Parcours E2E de référence, à ne pas dupliquer inutilement mais à couvrir (`09-test-strategy.md` section 38) : inscription → configuration entreprise → diagnostic ; création client + facture conforme → résultat `CONFORME` ; facture avec donnée manquante → `NON_CONFORME` → correction → nouvelle analyse → `CONFORME` ; import PDF simple → finding explicite sur le format non conforme ; isolation entre deux organisations ; stabilité d'une analyse historique après publication d'une nouvelle version de règle.

Couvrir spécifiquement en test d'accessibilité : présence systématique de label + icône pour chaque statut de conformité, navigation clavier complète des parcours critiques, distinction visuelle effective erreur technique/résultat de conformité, annonce correcte des erreurs de formulaire. Matrice de compatibilité : dernières versions stables de Chrome, Firefox, Safari (desktop et mobile) - pas de support de navigateurs obsolètes.

## 19. Workflow frontend

En complément du workflow général de `../CLAUDE.md` (section 25) :

1. Lire la documentation concernée (section 1 de ce fichier), en particulier `11-frontend-design-system.md` pour tout ce qui touche à l'UI.
2. Inspecter le code existant sous `frontend/app/`.
3. Inspecter le contrat API concerné dans `08-api-specification.md`.
4. Vérifier la version Next.js/React/Tailwind réellement installée (section 2).
5. Vérifier la documentation officielle actuelle sur Internet pour toute API Next.js/React/Tailwind non déjà utilisée ailleurs dans le code.
6. Identifier les impacts UX (design system) et accessibilité (WCAG 2.2 AA).
7. Identifier les impacts sécurité (section 16).
8. Implémenter le changement minimal, en respectant l'architecture par Epic (section 3).
9. Ajouter les tests (section 18).
10. Exécuter lint/format/tests.
11. Vérifier le diff.
12. Mettre à jour `../docs/` si la tâche a fait évoluer une décision qui y était documentée.

Règle finale, reprise de `../CLAUDE.md` : **« Do not guess when the answer can be verified. »**
