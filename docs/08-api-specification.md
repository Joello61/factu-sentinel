# API Specification - Assistant de conformité à la facturation électronique

> Ce document définit le contrat API du système, à partir de `01-intent-note.md` à `07-data-model.md`. Il ne contient ni code backend, ni migrations, ni stratégie de tests ou de sécurité complète, ni fichier `openapi.yaml` intégral. Chaque endpoint est justifié par une exigence du PRD (`04-product-requirements.md`) ou une User Story (`05-user-stories.md`) ; aucun endpoint CRUD n'est créé par simple réplication d'une table du modèle de données.

## 1. Introduction

L'API est le contrat stable entre le frontend, le backend et, à terme, d'éventuels consommateurs externes (`06-technical-architecture.md`, section 18-19). Elle expose des **opérations orientées domaine métier** - diagnostiquer, analyser, expliquer, corriger - plutôt qu'un accès direct aux tables du modèle de données (`07-data-model.md`).

## 2. API Goals

- Permettre au frontend de réaliser l'intégralité des parcours définis dans `05-user-stories.md` sans devoir contourner l'API.
- Garantir l'isolation multi-tenant à chaque appel (`06-technical-architecture.md`, section 20).
- Distinguer sans ambiguïté une erreur technique d'un résultat de conformité (`06-technical-architecture.md`, section 25 ; `04-product-requirements.md`, section 15).
- Exposer la traçabilité réglementaire (règle, version, source) à chaque résultat de conformité (`04-product-requirements.md`, section 18).
- Rester un contrat suffisamment stable et documenté pour être traduit directement en spécification OpenAPI (section 50).

## 3. API Principles

| Principe                 | Application                                                                                               |
| ------------------------ | --------------------------------------------------------------------------------------------------------- |
| Cohérence                | Mêmes conventions de nommage, pagination, erreurs sur toute l'API (sections 9-19)                         |
| Prévisibilité            | Une ressource se comporte de la même façon quel que soit le domaine auquel elle appartient                |
| Idempotence ciblée       | Appliquée aux opérations qui déclenchent un traitement coûteux ou ayant un effet métier (section 20)      |
| Sécurité par défaut      | Authentification requise partout sauf inscription/connexion ; tenant vérifié systématiquement (section 8) |
| Explicabilité            | Toute réponse de conformité porte la règle, sa version et sa source (section 48)                          |
| Pagination systématique  | Toute collection potentiellement volumineuse est paginée (`07-data-model.md`, section 38)                 |
| Observabilité            | Chaque requête est traçable via un identifiant de requête (section 49)                                    |
| Compatibilité ascendante | Les évolutions non cassantes sont privilégiées (section 44-45)                                            |

## 4. Architecture Context

Rappel de `06-technical-architecture.md` (section 18) : l'API est de type REST/JSON/HTTPS, exposée par le monolithe modulaire, organisée par domaine (Identity & Access, Organization, Customers, Invoicing, Documents, Compliance, Regulatory Rules, Notifications). Chaque groupe d'endpoints de ce document correspond à un module backend déjà défini architecturalement - aucune ressource n'introduit de nouveau module.

## 5. Base URL & Versioning

```text
https://api.<domaine>.fr/api/v1
```

- **Pourquoi versionner** : garantir que le frontend (et d'éventuels consommateurs futurs) ne soient pas cassés par une évolution du contrat.
- **Ce qui déclenche une nouvelle version majeure (`v2`)** : un changement incompatible (suppression d'un champ, changement de type, changement de comportement d'un endpoint existant) - voir section 44.
- **Stratégie de compatibilité** : une seule version majeure active à la fois au MVP (`v1`) ; aucune version supplémentaire n'est créée tant qu'aucun changement cassant n'est nécessaire, conformément à la consigne de ne pas verser dans une gestion de version prématurée.
- **Dépréciation** : un endpoint déprécié reste fonctionnel et documenté comme tel (en-tête `Deprecation`, section 44) pendant une période de transition, avant suppression en version majeure suivante.

## 6. Principes de conception API

Voir section 3 - non dupliqué ici.

## 7. Authentication

Cohérent avec `06-technical-architecture.md` (section 19, ADR-007) et `07-data-model.md` (entité `User`, section 5) : authentification par identifiants. **Mécanisme retenu (décision produit, 2026)** : un `access_token` JWT à durée de vie courte, conservé en mémoire côté frontend (**jamais** en `localStorage`) et présenté en en-tête `Authorization: Bearer <token>` sur toute requête authentifiée, complété par un **Refresh Token** transporté dans un cookie **HttpOnly, Secure, SameSite** - le backend Symfony reste l'autorité d'authentification. La durée de vie exacte de chaque jeton, la politique de rotation et de révocation restent renvoyées à `10-security-privacy.md` (détail d'implémentation non couvert ici) ; le principe d'architecture (access en mémoire + refresh en cookie HttpOnly) est en revanche tranché et fait partie du contrat.

**Conséquence directe sur le contrat** : le cookie HttpOnly portant le refresh token n'étant jamais lisible ni manipulable en JavaScript mais transmis automatiquement par le navigateur, une protection CSRF ciblée est nécessaire sur l'endpoint qui le consomme (`/auth/refresh`, voir section 55) - même si l'access token porté en en-tête `Authorization` réduit l'exposition CSRF générale du reste de l'API.

**Vérification d'email (décision produit, 2026)** : obligatoire avant toute fonctionnalité sensible (upload de document, déclenchement d'une analyse persistante, usage de l'assistant IA, fonctionnalités avancées), mais **pas nécessairement bloquante** avant un usage basique du compte (consultation, configuration initiale de l'organisation). `/auth/verify-email` fait donc partie du contrat actif dès le MVP (voir ci-dessous), et non d'une réserve conditionnelle.

| Endpoint                | Méthode | Description                                      | Auth requise                   |
| ----------------------- | ------- | ------------------------------------------------ | ------------------------------ |
| `/auth/register`        | POST    | Créer un compte (US-AUTH-001)                    | Non                            |
| `/auth/login`           | POST    | Se connecter (US-AUTH-002)                       | Non                            |
| `/auth/logout`          | POST    | Mettre fin à la session courante                 | Oui                            |
| `/auth/password/forgot` | POST    | Initier une récupération de compte (US-AUTH-003) | Non                            |
| `/auth/password/reset`  | POST    | Finaliser la récupération avec un jeton reçu     | Non (jeton porté dans le body) |

**Confirmé par le choix de JWT (`06-technical-architecture.md`, ADR-007)** : `/auth/refresh` fait désormais partie du contrat actif - un mécanisme JWT suppose par nature un access token à durée de vie courte et un refresh token permettant de le renouveler sans réauthentification complète.

| Endpoint        | Méthode | Description                                                                                                           | Auth requise                                                                                                                             |
| --------------- | ------- | --------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `/auth/refresh` | POST    | Échanger un refresh token valide (porté par le cookie `HttpOnly`, `Secure`, `SameSite`) contre un nouvel access token | Non - le refresh token est porté par le cookie dédié, jamais par le body (décision produit ; protection CSRF ciblée requise, section 55) |

**Fait partie du contrat actif dès le MVP (décision produit, 2026)** : `/auth/verify-email` - la vérification d'email est obligatoire avant les fonctionnalités sensibles (voir section 7), ce n'est donc plus une réserve conditionnelle.

| Endpoint                      | Méthode | Condition d'activation                                                                                                               |
| ----------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `/auth/verify-email/{userId}` | GET     | Toujours actif - déclenché après inscription (US-AUTH-001), requis avant l'accès aux fonctionnalités sensibles (upload, analyse, IA) |

**Implémentation (Phase 2, vérifiée)** : `symfonycasts/verify-email-bundle` valide par **URL signée** (`expires`, `signature` en query string), sans jeton stocké en base - la vérification se fait donc par un `GET` sur un lien complet, jamais par un `POST` portant un jeton dans le body. L'email envoyé au compte pointe vers `{FRONTEND_URL}/verify-email/{userId}?{query signée}` ; la page frontend correspondante relaie ces paramètres tels quels vers `GET /api/v1/auth/verify-email/{userId}`.

## 8. Authorization

**Historique (MVP, Phases 0-12)** : rôle unique **OWNER** (`04-product-requirements.md`, section 21 historique). Toute opération authentifiée était donc implicitement autorisée pour l'`OWNER` sur les ressources de sa propre organisation.

**Depuis la Phase 14 (DEC-009)** : trois rôles d'organisation - **OWNER**, **ADMIN**,
**COLLABORATOR** (`04-product-requirements.md` section 21.1 ; `07-data-model.md` section 5).
Chaque endpoint de ce document indique la permission logique requise (par exemple
`invoice:create`) - c'est précisément cette table de permissions par rôle, jamais le contrat
d'URL lui-même, qui absorbe l'ajout de ces rôles (anticipation confirmée,
`06-technical-architecture.md` section 39). Sauf indication contraire explicite dans
l'endpoint concerné, une permission `xxx:read`/`xxx:create`/etc. sur une ressource métier est
accordée aux trois rôles ; les permissions `team:*` et `notification:send_team` sont réservées
à `OWNER`/`ADMIN` (jamais `COLLABORATOR`, matrice complète en
`04-product-requirements.md` section 21.1).

**Depuis la Phase 15 (DEC-010)** : un quatrième rôle, **`PlatformAdministrator`**, structurellement
séparé des trois rôles d'organisation ci-dessus - jamais porté par un `Membership`, jamais
accordé sur les ressources d'une organisation précise (section 38.2). Les permissions
`platform:*` ne sont **jamais** accordées à un rôle d'organisation, quel qu'il soit.

## 9. Multi-tenancy

**Décision retenue : le tenant n'apparaît pas dans l'URL.**

```text
Retenu :        GET /api/v1/invoices
Rejeté :         GET /api/v1/organizations/{organizationId}/invoices
```

**Justification** : au MVP (Phases 0-12), un `User` n'appartenait qu'à une seule `Organization`. **Depuis la Phase 14 (DEC-009)**, un `User` peut appartenir à plusieurs `Organization` via plusieurs `Membership` (`07-data-model.md` section 5) - le tenant courant reste néanmoins déterminé **à partir de la session authentifiée**, jamais de l'URL, ce qui évite toute possibilité pour un client de manipuler un `organizationId` dans l'URL pour tenter d'accéder aux données d'une autre organisation (protection structurelle contre l'IDOR, section 60).

**Sélection de l'organisation active (Phase 14, nouveau)** :

```text
POST /auth/select-organization
Request: { "organization_id": "uuid" }
Response: 200 OK - nouvel access token portant la Membership sélectionnée comme organisation active.
Errors: 403 (l'appelant n'a pas de Membership sur cette organisation - jamais 404, l'existence de l'organisation elle-même n'est pas une information sensible ici puisque l'appelant en connaît déjà l'id via GET /auth/me/organizations).
```

```text
GET /auth/me/organizations
Response: 200 OK
{ "data": [ { "organization_id": "uuid", "legal_name": "string | null", "role": "OWNER" | "ADMIN" | "COLLABORATOR" } ] }
Description: liste des organisations auxquelles l'utilisateur connecté appartient, avec son rôle dans chacune - nécessaire pour construire l'écran de sélection avant/après POST /auth/select-organization.
```

**Comment le tenant est déterminé** : à l'authentification (ou après `POST /auth/select-organization`), le jeton de session porte une référence à la `Membership` active, qui résout l'`organization_id` courant. Chaque module backend (`06-technical-architecture.md`, section 6-7) applique ensuite ce `organization_id` comme filtre systématique - jamais optionnel - à toute lecture ou écriture.

**Organisation active par défaut à la connexion (`POST /auth/login`, précision Phase 14, implémentée et vérifiée)** : lorsqu'un `User` a plusieurs `Membership`, le claim `org` du token émis porte le `Membership` **le plus ancien** (`createdAt` minimal) - un simple repli déterministe, jamais présenté comme "l'organisation de l'utilisateur" ou une organisation privilégiée sur le plan produit. Aucun mécanisme de préférence persistée (dernière organisation utilisée) n'est documenté ailleurs dans `05-user-stories.md`/`11-frontend-design-system.md` : ce comportement n'est donc pas une richesse fonctionnelle attendue, seulement le point de départ avant un `POST /auth/select-organization` explicite. Une organisation sélectionnée explicitement survit à un rafraîchissement de l'access token (voir ci-dessous) - elle n'est jamais recalculée silencieusement vers ce défaut tant que la session (le refresh token) reste valide.

**Le claim `org` n'est jamais, à lui seul, une preuve d'appartenance** - qu'il s'agisse de l'access token ou, en amont, du refresh token qui en détermine le contenu au renouvellement. Chaque requête authentifiée revalide que l'utilisateur porté par le token dispose réellement d'un `Membership` sur l'organisation revendiquée (`App\Shared\Security\TenantFilterActivationListener`) ; un jeton valide et correctement signé mais dont le claim ne correspond plus à aucun `Membership` réel (ex. retrait d'un membre après émission d'un token encore valide pendant sa courte durée de vie résiduelle) est rejeté (`401`), jamais accepté sur la seule foi de la signature.

**Continuité de la sélection au rafraîchissement (Phase 14, implémentée et vérifiée)** : le refresh token (cookie `HttpOnly`, `06-technical-architecture.md` ADR-007) porte lui-même l'organisation active associée (`organization_id`, renseigné à l'émission/rotation) - sans quoi `POST /auth/select-organization` ne durerait que la durée de vie de l'access token en cours (quelques minutes) avant de silencieusement revenir au défaut ci-dessus. Cette valeur du refresh token n'est, elle non plus, jamais une preuve suffisante : `POST /auth/refresh` revalide systématiquement `User -> Membership -> Organization` avant d'émettre le nouvel access token, et refuse (`401`) si le `Membership` correspondant n'existe plus.

**`PlatformAdministrator` (Phase 15)** : n'a, par construction, **aucune** `Membership` ni `organization_id` courant - son authentification et son autorisation (section 38.2) sont structurellement distinctes de ce mécanisme de sélection de tenant, jamais une simple extension de celui-ci (ADR-009).

**Ressources globales** (`RegulatoryRule`, `RuleVersion`, `07-data-model.md` section 25) : accessibles sans filtre de tenant, car elles ne sont pas tenant-scoped - voir section 34.

## 10. Convention des identifiants

Cohérent avec `07-data-model.md` (section 32) :

- **Identifiant technique** (`resource_id`) : UUID, dans le chemin de l'URL (`/invoices/{invoiceId}`).
- **Identifiant métier** (`invoice_number`) : exposé comme un champ du payload, jamais utilisé comme clé d'URL - une facture est toujours adressée par son `id` technique. `invoice_number` reste une donnée métier extraite/saisie, optionnelle, unique seulement **au sein d'une organisation** lorsqu'elle est renseignée (contrainte bloquante résolue, `07-data-model.md` sections 28 et 34) - cette unicité intra-tenant ne fait pas de `invoice_number` un identifiant technique fiable pour l'ensemble du système, d'où le choix de conserver `id` comme seule clé d'URL.
- **`RegulatoryRule.id`** fait exception : identifiant métier stable (ex. `mention-siren-client`), utilisé directement comme clé d'URL pour cette ressource globale et référentielle (`/regulatory-rules/{ruleId}`), cohérent avec `07-data-model.md` section 32.

## 11. Resources

| Ressource                                              | Exposée publiquement (frontend) ?                               | Justification                                                                                                                                                                |
| ------------------------------------------------------ | --------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `users` (compte courant uniquement, pas de collection) | Oui, restreint à soi-même                                       | US-SETTINGS-001                                                                                                                                                              |
| `organizations/current`                                | Oui, singleton                                                  | US-COMPANY-001/002/003                                                                                                                                                       |
| `fiscal-contexts`                                      | Non exposée séparément - accessible via `organizations/current` | `FiscalContext` fait partie de l'agrégat Organization (`07-data-model.md` section 27)                                                                                        |
| `customers`                                            | Oui                                                             | US-CUSTOMER-001/002/003                                                                                                                                                      |
| `invoices`                                             | Oui                                                             | US-INVOICE-001/002                                                                                                                                                           |
| `invoices/{id}/lines`                                  | Non exposée séparément - incluse dans le payload `Invoice`      | Les lignes sont une partie interne de l'agrégat Invoice (`07-data-model.md` section 27) ; voir section 19                                                                    |
| `documents`                                            | Oui                                                             | US-DOCUMENT-001/002                                                                                                                                                          |
| `eligibility-diagnostics`                              | Oui, singleton par organisation avec historique                 | US-COMPLIANCE-001                                                                                                                                                            |
| `compliance-analyses`                                  | Oui                                                             | US-COMPLIANCE-002 à 007                                                                                                                                                      |
| `compliance-analyses/{id}/findings`                    | Oui, sous-ressource                                             | US-COMPLIANCE-003/004                                                                                                                                                        |
| `regulatory-rules`                                     | Oui, en lecture seule et périmètre limité (section 34)          | Support de US-COMPLIANCE-003 (source affichée)                                                                                                                               |
| `notifications`                                        | Oui                                                             | US-NOTIFICATION-001                                                                                                                                                          |
| `compliance-findings/{id}/explanations`, `assistant/questions` | Oui                                                       | US-AI-001/002                                                                                                                                                                |
| `integrations`                                         | Non exposée au MVP                                              | Aucune intégration active au MVP (`06-technical-architecture.md` section 16)                                                                                                 |
| `subscriptions`                                        | Non exposée au MVP                                              | Non implémentée au cœur du MVP, architecture extensible prévue (`07-data-model.md` section 24) - orientation Freemium + abonnement Pro provisoire, validation marché requise |
| `audit-events`                                         | Oui, restreint et en lecture seule                              | US-HISTORY-001 (partiellement - voir section 41)                                                                                                                             |
| `admin/rule-versions`                                  | Non exposée dans l'API utilisateur - API interne séparée        | Fonction interne (`05-user-stories.md`, Epic Administration)                                                                                                                 |

**Aucune ressource `invoice_lines` séparée** n'est créée, conformément à l'exemple explicitement donné dans la mission - les lignes sont manipulées uniquement comme partie du payload `Invoice` (section 19).

## 12. Request Conventions

- Format : `application/json` pour tout payload, sauf upload de document (`multipart/form-data`, section 32).
- En-têtes obligatoires : `Authorization` (hors endpoints publics d'authentification), `Content-Type`.
- En-tête recommandé : `Idempotency-Key` pour les opérations concernées (section 20).
- Toute date envoyée en entrée suit la convention de la section 17.

## 13. Response Conventions

**Format retenu** :

```json
{
  "data": {},
  "meta": {}
}
```

pour les réponses de collection (avec `meta.pagination`, section 43) ; pour une ressource unique :

```json
{
  "data": {}
}
```

**Justification** : l'enveloppe `data` uniforme permet d'ajouter des métadonnées (pagination, avertissements non bloquants) sans jamais casser la forme de la réponse - un ajout de champ dans `meta` reste toujours non cassant (section 56), alors qu'une réponse nue (`{}`) rendrait toute évolution vers une enveloppe future intrinsèquement cassante.

## 14. Error Contract

```json
{
  "error": {
    "code": "INVOICE_NOT_FOUND",
    "message": "La facture demandée est introuvable.",
    "details": [],
    "request_id": "a1b2c3d4-..."
  }
}
```

- `code` - identifiant stable et machine-readable, utilisable par le frontend pour un traitement conditionnel.
- `message` - message lisible, en français, destiné à un affichage de secours si le frontend ne traduit pas le `code` localement.
- `details` - tableau optionnel, utilisé notamment pour les erreurs de validation (section 15).
- `request_id` - corrélé à `X-Request-ID` (section 49), pour le support et le débogage.

**Distinction stricte, rappelée explicitement (règle absolue n°8 de la mission)** : ce contrat d'erreur n'est **jamais** utilisé pour représenter un résultat de conformité `NON_CONFORME` - voir section 46.

## 15. Validation Errors

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "La requête contient des champs invalides.",
    "details": [
      { "field": "customer.siren", "issue": "REQUIRED" },
      { "field": "invoice.issue_date", "issue": "INVALID_FORMAT" }
    ],
    "request_id": "..."
  }
}
```

Statut HTTP associé : `422 Unprocessable Entity` (section 44). Le champ `field` utilise la notation pointée correspondant à la structure du payload envoyé, pour que le frontend puisse associer directement l'erreur au champ de formulaire concerné (cohérent avec `06-technical-architecture.md` section 28, Forms).

## 16. Filtering & Sorting

- **Filtrage** : `?status=NON_CONFORME`, `?customer_id=...` - un paramètre par attribut filtrable, jamais de langage de requête générique (cohérent avec le principe de ne pas exposer directement le modèle de données, section 3).
- **Tri** : `?sort=-created_at` (préfixe `-` pour ordre décroissant), limité aux champs pour lesquels un index existe conceptuellement (`07-data-model.md`, section 33) - par exemple `created_at`, `status`, jamais un champ non indexé qui dégraderait la performance.
- **Recherche** : `?search=...` uniquement sur les ressources qui le justifient (`customers`, par nom ou SIREN) - non générique à toute l'API.

## 17. Date & Time Conventions

| Type                     | Convention                | Exemple                | Usage                                                                  |
| ------------------------ | ------------------------- | ---------------------- | ---------------------------------------------------------------------- |
| Date métier (sans heure) | ISO 8601, `YYYY-MM-DD`    | `2026-09-01`           | `Invoice.issue_date`, `EligibilityDiagnostic.emission_obligation_date` |
| Horodatage technique     | ISO 8601, UTC, avec heure | `2026-08-17T21:45:00Z` | `created_at`, `triggered_at`, `occurred_at`                            |

Toutes les dates sont émises et acceptées en UTC ; la conversion vers le fuseau horaire de l'utilisateur relève du frontend, jamais de l'API.

## 18. Monetary Values

Cohérent avec `07-data-model.md` (section 11, `Decimal`) : tout montant est représenté comme une **chaîne de caractères décimale** dans le JSON (par exemple `"120.50"`, pas `120.50` en nombre flottant), pour éviter toute perte de précision liée à la représentation binaire des flottants. Chaque montant est accompagné explicitement de sa devise (`currency`, code ISO 4217) au niveau de la ressource `Invoice`, jamais implicite.

```json
{
  "unit_price_ht": "45.00",
  "vat_rate": "0.20",
  "line_amount_ht": "45.00",
  "line_amount_vat": "9.00",
  "line_amount_ttc": "54.00"
}
```

## 19. Enum Conventions et Invoice Lines

Toute valeur d'énumération est une chaîne stable en `SCREAMING_SNAKE_CASE` (par exemple `NON_CONFORME`, `PROFESSIONNEL_FRANCAIS`), cohérente avec les valeurs conceptuelles définies dans `07-data-model.md`. **Évolution sans casser les clients** : l'ajout d'une nouvelle valeur à une énumération existante est considéré comme un changement **non cassant** à condition que le frontend traite toute valeur inconnue avec un comportement de repli explicite (jamais un crash) - cette exigence de tolérance côté client est documentée ici comme une attente du contrat.

**Lignes de facture** : incluses directement dans le payload de création/modification de `Invoice` (tableau `lines`), jamais manipulées via un endpoint séparé - cohérent avec la section 11 et l'exemple explicite de la mission (éviter `GET/POST/DELETE /invoice_lines`), les lignes n'ayant pas de cycle de vie indépendant de la facture qui les contient (`07-data-model.md`, agrégat `Invoice`, section 27).

## 20. Idempotency

Opérations nécessitant une clé d'idempotence (`Idempotency-Key` en en-tête) :

- `POST /invoices` (éviter la création dupliquée d'une même facture en cas de double soumission réseau).
- `POST /invoices/{id}/compliance-analyses` (éviter le déclenchement redondant d'une analyse coûteuse, cohérent avec `06-technical-architecture.md` section 18).
- `POST /documents` (upload).

**Comportement** : une requête envoyée avec une `Idempotency-Key` déjà vue pour cette ressource et cet utilisateur, dans une fenêtre de conservation à définir en implémentation, retourne la **réponse précédemment produite** plutôt que de recréer la ressource. Un conflit (même clé, payload différent) retourne `409 Conflict`. **Durée de conservation de la clé résolue (décision produit)** : **24 heures par défaut** - cette durée pourra être ajustée en implémentation si une contrainte métier l'impose, mais sert de valeur de référence pour le MVP.

**Mécanisme de stockage (précisé en Phase 5, décision produit)** : non figé à Redis. `POST /invoices/{id}/compliance-analyses` l'implémente depuis la Phase 5 via un store PostgreSQL transactionnel (`backend/src/Shared/Idempotency/`), avec un `UPSERT` atomique (`INSERT ... ON CONFLICT ... WHERE expires_at < NOW()`) garantissant qu'une seule opération métier s'exécute sous requêtes concurrentes portant la même clé - la seconde requête concurrente bloque nativement sur la contrainte d'unicité PostgreSQL jusqu'au commit de la première, puis reçoit sa réponse déjà figée, jamais une seconde exécution. Ce choix évite d'introduire Redis avant son besoin réel : le traitement asynchrone (`06-technical-architecture.md`, ADR-006), qui n'arrive qu'en Phase 7. Redis reste l'option prévue pour cette intégration asynchrone future, pas une condition du mécanisme d'idempotence lui-même.

## 21. Concurrency / Optimistic Locking

Risque identifié : deux sessions modifiant la même `Invoice` en franchissement de la même fenêtre temporelle (peu probable au MVP compte tenu du rôle unique par organisation, mais possible si plusieurs onglets sont ouverts par le même utilisateur).

**Mécanisme retenu, proportionné au risque réel** : en-tête `If-Match` avec un `ETag` correspondant à la version courante de la ressource, exigé sur `PATCH /invoices/{id}` uniquement (la ressource la plus susceptible d'un conflit d'édition). Une requête sans `If-Match` correspondant reçoit `409 Conflict`. **Aucun mécanisme de verrouillage plus lourd** (verrou pessimiste, session exclusive) n'est retenu, jugé disproportionné pour le volume et le contexte d'usage du MVP (cohérent avec le principe de simplicité de `06-technical-architecture.md` section 3).

## 22. Rate Limiting

Stratégie conceptuelle, sans chiffres fixés arbitrairement :

| Catégorie                                       | Nécessité d'une limite                                                 | Calibrage                                                                                  |
| ----------------------------------------------- | ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| Authentification (`/auth/*`)                    | Oui - protection contre les tentatives de force brute                  | À calibrer pendant les tests de charge et en cohérence avec `10-security-privacy.md`       |
| API générale (lecture)                          | Faible priorité au MVP                                                 | À calibrer si un usage abusif est observé                                                  |
| Déclenchement d'analyse (`compliance-analyses`) | Oui - opération coûteuse en ressources et potentiellement en appels IA | À calibrer, cohérent avec le contrôle de coût de `06-technical-architecture.md` section 15 |
| Upload de documents                             | Oui - protection contre l'abus de stockage                             | À calibrer                                                                                 |
| Assistant IA (`assistant/*`)                    | Oui - dépendance externe coûteuse                                      | À calibrer en cohérence avec les limites de coût de l'AI Gateway                           |
| Endpoints administratifs internes               | Oui, mais périmètre d'accès déjà restreint (section 40)                | À calibrer                                                                                 |
| Invitation de membre (Phase 14)                 | Oui - protection contre l'abus d'invitation (spam email)               | Calibré à l'implémentation : 30/heure par organisation (`limiter.team_invite`, `backend/config/packages/rate_limiter.yaml`), même ordre de grandeur que `document_upload` |
| `GET /invitations/{token}` / `POST /invitations/{token}/accept` (Phase 14, revue de complétude) | Oui - seuls endpoints de cette phase où l'appelant n'appartient à aucune organisation, `organization_id` inutilisable comme clé | Calibré à l'implémentation : 20/15 minutes par IP (`limiter.invitation_token_access`), compteur partagé entre les deux endpoints - même clé que `password_reset_request` |
| Notification plateforme (Phase 15)              | Oui - une diffusion globale mal maîtrisée impacte tous les utilisateurs | À calibrer, par `PlatformAdministrator`, avec confirmation explicite côté frontend pour `target_type=ALL` |

## 23. Authentication API

Voir section 7 pour le tableau complet ; détail de deux endpoints représentatifs :

**POST /auth/register**

```text
Description: Créer un compte utilisateur et son organisation initiale (Organization vide,
  Membership OWNER). N'authentifie pas automatiquement l'utilisateur (register et login
  restent deux appels distincts) ; déclenche l'envoi de l'email de vérification.
Authentication: Non requise.
Request:
{
  "email": "string",
  "password": "string"
}
Response: 201 Created
{
  "data": { "id": "uuid", "email": "string" }
}
Errors: 422 (email invalide, mot de passe trop faible - 15 caractères minimum, NIST 2026),
  409 (email déjà utilisé)
Async: Non.
Idempotency: Non requise (l'unicité de l'email fait déjà office de garde-fou).
Audit: Oui - AuditLogEntry(event_type="user_registered").
```

**POST /auth/login**

```text
Description: Authentifier un utilisateur existant (US-AUTH-002). Pose le refresh token dans
  un cookie HttpOnly/Secure/SameSite=Lax ; l'access token est renvoyé dans le corps de la
  réponse, jamais dans un cookie lisible.
Authentication: Non requise.
Request: { "email": "string", "password": "string" }
Response: 200 OK - { "data": { "token": "string (JWT)" } }
Errors: 401 (identifiants invalides - message volontairement non spécifique, cf. US-AUTH-002 critère d'acceptation)
Async: Non.
Audit: Oui - AuditLogEntry(event_type="login").
```

**GET /users/current**

```text
Description: Identité du compte authentifié - source fiable pour l'état de vérification
  d'email côté frontend (le JWT ne porte volontairement aucun claim email_verified, qui
  deviendrait obsolète entre deux rafraîchissements de token).
Authentication: Requise.
Response: 200 OK
{
  "data": {
    "id": "uuid",
    "email": "string",
    "email_verified_at": "string (ISO 8601) | null",
    "created_at": "string (ISO 8601)"
  }
}
Audit: Non (lecture simple).
```

**PATCH /users/current**

```text
Description: Modifier l'email et/ou le mot de passe du compte authentifié (US-SETTINGS-001,
  Phase 13). current_password est requis dès que email ou new_password est fourni (défense en
  profondeur sur une action sensible d'une session déjà authentifiée). Un changement d'email
  remet email_verified_at à null et déclenche un nouvel envoi de l'email de vérification
  (VerifyEmailMailer, déjà utilisé par POST /auth/register). Un changement de mot de passe
  révoque tous les refresh tokens du compte (docs/10-security-privacy.md, section 14).
Authentication: Requise.
Request:
{
  "email": "string (optionnel)",
  "current_password": "string (requis si email ou new_password fourni)",
  "new_password": "string (optionnel, 15-128 caractères, NIST 2026)"
}
Response: 200 OK
{
  "data": {
    "id": "uuid",
    "email": "string",
    "email_verified_at": "string (ISO 8601) | null",
    "created_at": "string (ISO 8601)"
  }
}
Errors: 422 (rien à modifier, current_password manquant/incorrect, new_password trop court/long,
  email invalide), 409 (email déjà utilisé par un autre compte)
Async: Non.
Idempotency: Non requise (PATCH naturellement idempotent sur ce contrat).
Audit: Oui - AuditLogEntry(event_type="USER_UPDATED", newState contient email et
  password_changed uniquement - jamais de hash ni de mot de passe en clair).
```

**DELETE /users/current**

```text
Description: Demander la suppression du compte authentifié (US-SETTINGS-002, Phase 13). Soft
  delete uniquement (docs/07-data-model.md, section 30) : perte d'accès immédiate (login,
  refresh, et jeton d'accès déjà émis - rejeté dès la requête authentifiée suivante), tous les
  refresh tokens révoqués. L'Organization et ses données (Customer, Invoice, ...) ne sont pas
  supprimées ni anonymisées par cet endpoint (aucun mécanisme de soft delete sur Organization à
  ce stade ; voir docs/10-security-privacy.md sections 38-39 pour la tension conservation
  légale / droit à l'effacement). L'email redevient disponible pour une nouvelle inscription.
Authentication: Requise.
Request: { "current_password": "string (requis)" }
Response: 204 No Content
Errors: 422 (current_password manquant ou incorrect)
Async: Non.
Idempotency: Non requise (un compte déjà soft-deleted ne peut plus s'authentifier pour
  rejouer la requête).
Audit: Oui - AuditLogEntry(event_type="USER_DELETED", newState=null).
```

## 24. Organization API

**GET /organizations/current**

```text
Description: Consulter l'entreprise de l'utilisateur connecté, incluant son FiscalContext
  courant une fois celui-ci configuré (Phase 3, PATCH /organizations/current).
Authentication: Requise.
Permission: organization:read
Response: 200 OK
{
  "data": {
    "id": "uuid",
    "legal_name": "string | null",
    "trade_name": "string | null",
    "siren": "string | null",
    "siret": "string | null",
    "legal_form": "string | null",
    "country": "string | null",
    "configured": "boolean",
    "created_at": "string (ISO 8601)",
    "role": "OWNER" | "ADMIN" | "COLLABORATOR",  // rôle de l'appelant dans cette organisation (Phase 14) - confort d'affichage frontend uniquement, jamais l'autorité d'autorisation (OrganizationPermissionVoter)
    "fiscal_context": { ... } | absent tant que non configuré (Phase 3)
  }
}
Audit: Non (lecture simple).
```

**Créée vide à l'inscription (Phase 2, vérifié)** : `legal_name`/`siren`/`country` sont nullables - une `Organization` est créée sans identité légale au moment de `POST /auth/register`, avant toute saisie utilisateur. `configured` (`true` dès que `legal_name` est renseigné) permet au frontend de distinguer une organisation à configurer d'une organisation déjà identifiée, sans dépendre d'un champ dédié non prévu par `07-data-model.md`.

**PATCH /organizations/current**

```text
Description: Modifier l'identité légale et/ou le contexte fiscal de l'entreprise (US-COMPANY-001/002/003).
Permission: organization:update
Request: {
  "legal_name": "string?",
  "fiscal_context": {
    "vat_status": "string?",
    "employees_count": "integer?",
    "annual_turnover": "string?",            // décimal-en-chaîne, section 18
    "annual_balance_sheet_total": "string?"  // décimal-en-chaîne, section 18
  }
}
Response: 200 OK - organisation mise à jour + eligibility_diagnostic recalculé (voir section 30)
```

**Correction Phase 3** (voir plan Phase 3, gap 1) : le payload accepte les trois valeurs brutes
saisies par l'utilisateur (`employees_count`, `annual_turnover`, `annual_balance_sheet_total`,
US-COMPANY-002), jamais `company_size_category` directement : cette valeur est **toujours
dérivée par le backend**, jamais acceptée en entrée (`07-data-model.md`, section 7). La
version antérieure de ce contrat, qui acceptait `company_size_category` en entrée,
contredisait `07-data-model.md` section 7 et US-COMPANY-002 ; c'est la version ci-dessus qui
fait foi.

**Règle de complétude** : `fiscal_context` est fusionné avec le contexte existant de
l'organisation (valeurs déjà connues si non fournies dans la requête). Si, après fusion,
`vat_status` ou l'une des trois valeurs numériques reste manquante (première configuration
incomplète), la requête échoue en `422 VALIDATION_ERROR` avec un `details[]` par champ
manquant : rien n'est persisté, `company_size_category` n'étant jamais calculable sans les
trois valeurs.

```text
Side effects: Si fiscal_context change, une nouvelle version de FiscalContext est créée (07-data-model.md §7) - l'ancienne reçoit effective_until ; un nouveau EligibilityDiagnostic est calculé.
Async: Non.
Audit: Oui : AuditLogEntry(event_type="organization_updated"), delta des champs modifiés ; plus AuditLogEntry(event_type="eligibility_diagnostic_computed") référençant le diagnostic recalculé.
```

## 25. Members & Roles API

**Engagée en Phase 14** (décision produit du 21/08/2026, DEC-009) - historiquement non exposée
au MVP (un seul rôle `OWNER`), désormais nécessaire pour `FR-TEAM-001/002/003`
(`04-product-requirements.md` section 21.1).

| Endpoint                       | Méthode | Description                                  | Permission (OWNER / ADMIN / COLLABORATOR) |
| ------------------------------- | ------- | --------------------------------------------- | ------------------ |
| `/organizations/current/invitations` | POST | Inviter un membre (US-TEAM-001)               | `team:invite` (Oui / Oui / Non)      |
| `/organizations/current/invitations` | GET  | Lister les invitations en attente             | `team:read` (Oui / Oui / Oui)        |
| `/organizations/current/invitations/{id}` | DELETE | Révoquer une invitation en attente       | `team:invite` (Oui / Oui / Non)      |
| `/organizations/current/members` | GET     | Lister les membres de l'organisation          | `team:read` (Oui / Oui / Oui)        |
| `/organizations/current/members/{id}` | PATCH | Modifier le rôle d'un membre (US-TEAM-002) | `team:manage_roles` (Oui / Non / Non) |
| `/organizations/current/members/{id}` | DELETE | Retirer un membre (US-TEAM-003)          | `team:remove` (Oui / Oui, jamais l'OWNER / Non) |
| `/invitations/{token}` | GET | Aperçu public d'une invitation (plan Phase 14, gap de spécification comblé) | Publique, aucune authentification |
| `/invitations/{token}/accept` | POST | Accepter une invitation (plan Phase 14, gap de spécification comblé) | Authentification requise, aucune permission de rôle (l'appelant ne peut par définition pas encore être membre de l'organisation cible) |

`team:read` détenu par les trois rôles (précision d'implémentation, section 21.1 du PRD mise à
jour en conséquence) : la restriction du `COLLABORATOR` porte sur la gestion d'équipe, jamais
sur sa simple consultation.

```text
POST /organizations/current/invitations
Request: { "email": "string", "role": "ADMIN" | "COLLABORATOR" }
Response: 201 Created
{ "data": { "id": "uuid", "email": "string", "role": "string", "status": "pending", "created_at": "..." } }
Errors: 422 VALIDATION_ERROR (role invalide ou absent, ou une invitation "pending" existe déjà pour cet email dans cette organisation) ; 403 (appelant COLLABORATOR, jamais autorisé - matrice PRD §21.1) ; 429 (limite team_invite dépassée, section 22).
Idempotency-Key: requise (même convention que POST /invoices, §20).
Audit: Oui - AuditLogEntry(event_type="MEMBER_INVITED").
```

```text
PATCH /organizations/current/members/{id}
Request: { "role": "ADMIN" | "COLLABORATOR" }
Response: 200 OK - Membership mis à jour.
Errors: 403 (appelant non-OWNER, jamais autorisé - seul un OWNER modifie un rôle, matrice PRD §21.1) ; 409 CONFLICT (tentative de modifier le rôle de l'OWNER lui-même, toujours refusée).
Audit: Oui - AuditLogEntry(event_type="MEMBER_ROLE_CHANGED"), ancien/nouveau rôle.
```

```text
DELETE /organizations/current/members/{id}
Response: 204 No Content.
Errors: 403 (appelant COLLABORATOR ; ou appelant ADMIN tentant de retirer l'OWNER - toujours refusé, matrice PRD §21.1).
Audit: Oui - AuditLogEntry(event_type="MEMBER_REMOVED").
```

**Acceptation d'une invitation (plan Phase 14, endpoints non couverts par la version
précédente de cette section - `05-user-stories.md` US-TEAM-001 ne décrit que l'émission côté
`OWNER`/`ADMIN`, jamais le parcours de l'invité)** :

```text
GET /invitations/{token}
Description: Aperçu public d'une invitation (organisation, rôle, email invité, expiration) -
  jamais authentifié, pour que le frontend puisse orienter l'invité vers une connexion ou une
  inscription avant l'acceptation elle-même.
Response: 200 OK
{ "data": { "organization_name": "string | null", "email": "string", "role": "ADMIN" | "COLLABORATOR", "expires_at": "..." } }
Errors: 404 uniforme pour un jeton invalide, inconnu, expiré ou révoqué - jamais de distinction
  observable depuis cet endpoint public (éviter toute énumération).
Audit: Non (lecture publique).
```

```text
POST /invitations/{token}/accept
Description: Transforme l'Invitation en Membership pour l'utilisateur authentifié, si (et
  seulement si) son email correspond exactement à celui de l'invitation.
Authentication: Requise.
Response: 201 Created
{ "data": { "organization_id": "uuid", "role": "ADMIN" | "COLLABORATOR" } }
Errors: 404 (jeton invalide/inconnu) ; 409 CONFLICT (invitation expirée, révoquée, déjà acceptée,
  ou l'appelant est déjà membre de cette organisation) ; 403 (l'email du compte authentifié ne
  correspond pas à celui de l'invitation - jamais un rattachement silencieux à un autre compte).
Audit: Oui - AuditLogEntry(event_type="MEMBER_INVITATION_ACCEPTED") - ne porte jamais le jeton,
  en clair ou haché, dans previousState/newState.
```

**Isolation** : ces endpoints n'opèrent jamais que sur l'organisation courante de l'appelant (résolue depuis la session, jamais un `organization_id` en paramètre) - même principe que le reste de l'API (section 9). `GET /invitations/{token}` et `POST /invitations/{token}/accept` font exception par nature (l'appelant n'appartient par définition pas encore à l'organisation cible) - jamais un précédent pour un autre endpoint de cette section.

## 26. Customers API

| Endpoint          | Méthode | Description                                                | Permission        |
| ----------------- | ------- | ---------------------------------------------------------- | ----------------- |
| `/customers`      | GET     | Lister les clients (paginé, filtrable par `customer_type`) | `customer:read`   |
| `/customers`      | POST    | Créer un client (US-CUSTOMER-001/002)                      | `customer:create` |
| `/customers/{id}` | GET     | Consulter un client                                        | `customer:read`   |
| `/customers/{id}` | PATCH   | Modifier un client                                         | `customer:update` |
| `/customers/{id}` | DELETE  | Supprimer (logique, `07-data-model.md` §30) un client      | `customer:delete` |

```text
POST /customers
Request:
{
  "customer_type": "PROFESSIONNEL_FRANCAIS",
  "name": "string",
  "siren": "string?",   // optionnel, y compris si customer_type = PROFESSIONNEL_FRANCAIS
  "country": "FR"
}
Response: 201 Created
Errors: 422 uniquement pour une violation de format (ex. SIREN ne comportant pas 9 chiffres) ou un champ requis manquant (name, country)
Idempotency: recommandée mais non bloquante (création peu coûteuse)
Audit: Oui
```

**Correction (Phase 4, décision D1)** : la version précédente de ce contrat indiquait un `422` si `siren` était manquant pour un `PROFESSIONNEL_FRANCAIS`, en citant US-CUSTOMER-002 comme justification. C'était une erreur de rédaction, contredisant directement le texte même d'US-CUSTOMER-002 (`05-user-stories.md`) qui décrit une absence de SIREN comme devant produire un état `A_VERIFIER` au moment de l'analyse de conformité, "pas comme une non-conformité automatique" - et contredisant BR-COMPLIANCE-003/ADR-002 (`CLAUDE.md` racine, section 9) : une donnée manquante ne doit jamais être rejetée en amont du Compliance Engine. `POST /customers` accepte donc un `PROFESSIONNEL_FRANCAIS` sans `siren` (`201 Created`, `siren: null`) ; la qualification de cette absence relève exclusivement de la Phase 5 (Compliance Engine).

## 27. Invoices API

Cohérent avec la distinction fondamentale de `07-data-model.md` (section 10) : `Invoice` est une facture **à des fins d'analyse uniquement**.

| Endpoint         | Méthode | Description                                                         | Permission       |
| ---------------- | ------- | ------------------------------------------------------------------- | ---------------- |
| `/invoices`      | GET     | Lister les factures (paginé, filtrable par `status`, `customer_id`) | `invoice:read`   |
| `/invoices`      | POST    | Créer une facture par saisie manuelle (US-INVOICE-002)              | `invoice:create` |
| `/invoices/{id}` | GET     | Consulter une facture, avec ses lignes et ses documents associés (Phase 7) | `invoice:read`   |
| `/invoices/{id}` | PATCH   | Modifier une facture (`If-Match` requis, section 21)                | `invoice:update` |

**`documents` (Phase 7, docs/12-roadmap.md)** : `GET /invoices/{id}` inclut un tableau `documents` (forme identique à `data` de `GET /documents/{id}`, section 31) - liste des documents actifs (non supprimés) rattachés à la facture, cohérent avec `docs/11-frontend-design-system.md` section 32. Ajout de champ non cassant (section 44-45) ; les autres endpoints Invoice (`POST`, `PATCH`, `GET` paginé) renvoient `documents: []` par défaut, cette composition n'étant utile qu'à la page de détail.

**Aucun endpoint `/invoices/{id}/validate`, `/invoices/{id}/issue` ou `/invoices/{id}/cancel` n'est créé** - contrairement à l'exemple du gabarit de la mission, qui reflète un cycle de vie d'émission. Notre produit n'émet jamais de facture (`04-product-requirements.md` section 7 et 30 ; `07-data-model.md` section 10 et 29) : le cycle de vie exposé par l'API est celui de l'**analyse**, pas de l'émission (voir section 28 de ce document).

```text
POST /invoices
Request:
{
  "customer_id": "uuid",
  "operation_type": "PRESTATION_SERVICE",
  "issue_date": "2026-08-15",
  "currency": "EUR",
  "lines": [
    {
      "description": "string",
      "quantity": "1",
      "unit_price_ht": "45.00",
      "vat_rate": "0.20"
    }
  ]
}
Response: 201 Created
{
  "data": {
    "id": "uuid",
    "status": "READY_FOR_ANALYSIS",
    "total_amount_ht": "45.00",
    "total_amount_ttc": "54.00",
    "lines": [ "..." ]
  }
}
Errors: 422 (incohérence/absence de ligne, §11 de 07-data-model.md) ; 404 si customer_id introuvable ou appartenant à une autre organisation (jamais 422 dans ce cas précis, cohérent avec la règle générale de la section 42 : ressource inexistante ou cross-tenant = 404, jamais confirmée par un autre code)
Idempotency: `Idempotency-Key` **obligatoire et honorée** (section 20 ; `400` si absente) - écart D2 (Phase 4) fermé à l'implémentation de la Phase 7 (docs/12-roadmap.md) via le même store PostgreSQL que `POST /invoices/{id}/compliance-analyses` (`Shared/Idempotency/`), câblé directement dans `App\Invoicing\Controller\CreateInvoiceController` (même précédent que la gestion d'If-Match dans `App\Invoicing\Controller\UpdateInvoiceController` - un en-tête HTTP d'idempotence/concurrence reste une préoccupation du controller, jamais d'`InvoiceService`).
Audit: Oui
```

## 28. Invoice lifecycle

États retenus, conformes à `07-data-model.md` (section 29) - **pas** ceux du gabarit de la mission :

```text
DRAFT
   ↓ (données suffisantes pour analyse)
READY_FOR_ANALYSIS
   ↓ (au moins une analyse COMPLETED)
ANALYZED
   ↓ (modification d'une donnée pertinente pour la conformité via PATCH /invoices/{id})
ANALYSIS_STALE
   ↓ (nouvelle analyse déclenchée et COMPLETED)
ANALYZED
```

| Transition                        | Déclenchée par                                                                                                            | Condition                           | Erreur si non respectée                                                                                                                                                                                                                                                                                                                                                       |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------------------- | ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DRAFT` → `READY_FOR_ANALYSIS`    | Système, automatique dès que les champs requis sont renseignés                                                            | Client et lignes minimales présents | -                                                                                                                                                                                                                                                                                                                                                                             |
| `READY_FOR_ANALYSIS` → `ANALYZED` | `POST /invoices/{id}/compliance-analyses` complété                                                                        | Analyse `COMPLETED` (section 30-31) | -                                                                                                                                                                                                                                                                                                                                                                             |
| `ANALYZED` → `ANALYSIS_STALE`     | `PATCH /invoices/{id}` modifiant une donnée pertinente pour la conformité (client, ligne, montant, nature de l'opération) | -                                   | Aucune - **résolu (décision produit, 2026, harmonisé avec `07-data-model.md` sections 28-29)** : la modification est **acceptée**, jamais bloquée par un `409 Conflict` du seul fait que la facture ait déjà été analysée. La réponse `200 OK` renvoie explicitement `status: "ANALYSIS_STALE"` pour que le frontend affiche l'invalidation (jamais un changement silencieux) |
| `ANALYSIS_STALE` → `ANALYZED`     | `POST /invoices/{id}/compliance-analyses` (nouvelle analyse) complété                                                     | Analyse `COMPLETED`                 | -                                                                                                                                                                                                                                                                                                                                                                             |

**Distinction avec le `409 Conflict` de concurrence (section 21)** : le passage à `ANALYSIS_STALE` n'est **pas** un conflit - c'est une transition d'état normale et attendue. Le `409 Conflict` reste réservé au cas où l'en-tête `If-Match`/`ETag` ne correspond plus à la version courante de la ressource (modification concurrente non vue par le client), un problème orthogonal au statut d'analyse.

**Aucune nouvelle `Invoice` n'est créée** lors de ce passage à `ANALYSIS_STALE` : l'entité reste la même (résolu, `07-data-model.md` section 29) - pas de version ni de duplication de la ressource côté API.

## 29. Compliance API

Ressource la plus importante du contrat (`04-product-requirements.md`, Compliance Engine).

| Endpoint                             | Méthode | Description                                                                         | Permission          |
| ------------------------------------ | ------- | ----------------------------------------------------------------------------------- | ------------------- |
| `/eligibility-diagnostics/current`   | GET     | Consulter le diagnostic d'éligibilité courant de l'organisation (US-COMPLIANCE-001) | `compliance:read`   |
| `/invoices/{id}/compliance-analyses` | POST    | Lancer une analyse de conformité sur une facture (US-COMPLIANCE-002)                | `compliance:create` |
| `/invoices/{id}/compliance-analyses` | GET     | Lister les analyses d'une facture (US-COMPLIANCE-006)                               | `compliance:read`   |
| `/compliance-analyses`               | GET     | Historique paginé, toutes factures confondues (US-HISTORY-001, section 29 bis)      | `compliance:read`   |
| `/compliance-analyses/{id}`          | GET     | Consulter une analyse (statut, résultat global)                                     | `compliance:read`   |
| `/compliance-analyses/{id}/findings` | GET     | Lister les findings détaillés d'une analyse (US-COMPLIANCE-003/004)                 | `compliance:read`   |

```text
GET /eligibility-diagnostics/current
Response: 200 OK
{
  "data": {
    "reception_obligation_date": "2026-09-01",
    "emission_obligation_date": "2027-09-01",
    "computed_at": "2026-08-17T10:00:00Z",
    "explanation": "Votre entreprise est assujettie à la TVA même en franchise en base ; elle reste concernée par la réforme."
  }
}
```

### 29 bis. Historique organisation-wide (Phase 9)

**Écart comblé (Phase 9, `12-roadmap.md`)** : cet endpoint était référencé dans la matrice Endpoint → User Story (section 52, `GET /compliance-analyses (historique)`) et dans `07-data-model.md` (section 44) sans jamais avoir été spécifié dans ce document - contrat désormais documenté ici plutôt que laissé implicite.

`GET /compliance-analyses`, distinct de `GET /invoices/{id}/compliance-analyses` (déjà scopé à une seule facture) : historique paginé de **toutes** les analyses de l'organisation, toutes factures confondues, anciens et nouveaux résultats tous deux consultables, jamais écrasés (US-COMPLIANCE-006 ; US-HISTORY-001).

```text
GET /compliance-analyses?page=1&per_page=20&global_result=NON_CONFORME&from=2026-08-01&to=2026-08-31
Response: 200 OK
{
  "data": [
    {
      "id": "uuid",
      "invoice_id": "uuid",
      "invoice_number": "F-2026-001",
      "status": "COMPLETED",
      "global_result": "NON_CONFORME",
      "triggered_at": "2026-08-15T10:00:00Z",
      "completed_at": "2026-08-15T10:00:01Z"
    }
  ],
  "meta": { "pagination": { "page": 1, "per_page": 20, "total_count": 1, "total_pages": 1 } }
}
```

Filtres (`07-data-model.md`, section 44 : « Filtres (date, statut) ») : `global_result` (une des six valeurs `ComplianceResult`, ignoré silencieusement si absent ou invalide - jamais un `400`, même tolérance que le filtre `status` de `GET /invoices`) ; `from`/`to` (`YYYY-MM-DD`, bornent `triggered_at`, ignorés silencieusement si absents ou mal formés). Contrairement à `GET /invoices/{id}/compliance-analyses`, cette liste porte `invoice_id`/`invoice_number` sur chaque élément - indispensable puisqu'elle n'est jamais déjà scopée à une facture connue de l'appelant.

## 30. Analyse synchrone vs asynchrone

Cohérent avec `06-technical-architecture.md` (section 12) :

```text
POST /invoices/{id}/compliance-analyses

→ Si la facture provient d'une saisie manuelle déjà structurée : traitement synchrone possible.
  200 OK, résultat complet directement dans la réponse.

→ Si la facture dépend d'une extraction de document non encore terminée : traitement asynchrone.
  202 Accepted
  {
    "data": {
      "id": "uuid",
      "status": "PENDING",
      "status_url": "/api/v1/compliance-analyses/{id}"
    }
  }
```

Le frontend interroge ensuite `GET /compliance-analyses/{id}` (polling, cohérent avec `06-technical-architecture.md` section 29 - pas de WebSocket au MVP) jusqu'à obtenir un statut `COMPLETED` ou `FAILED`.

**Distinction stricte, rappel de la section 46** : un statut `COMPLETED` avec `global_result: "NON_CONFORME"` est une réponse `200 OK` - ce n'est jamais une erreur HTTP.

## 31. Documents API

| Endpoint                  | Méthode | Description                                                                    | Permission        |
| ------------------------- | ------- | ------------------------------------------------------------------------------ | ----------------- |
| `/documents`              | POST    | Uploader un document (US-INVOICE-001)                                          | `document:create` |
| `/documents/{id}`         | GET     | Consulter les métadonnées d'un document et son statut de traitement            | `document:read`   |
| `/documents/{id}/content` | GET     | Télécharger le fichier (redirection vers une URL pré-signée du stockage objet) | `document:read`   |
| `/documents/{id}`         | DELETE  | Supprimer un document (US-DOCUMENT-002)                                        | `document:delete` |

**Mécanisme d'upload retenu** : `multipart/form-data` sur `POST /documents` pour le MVP, plus simple à implémenter pour un développeur solo qu'un flux d'URL pré-signée à deux étapes (cohérent avec `06-technical-architecture.md`, section 3, simplicité opérationnelle). Une évolution vers une URL pré-signée directe reste possible sans changement du contrat vu du frontend au-delà de l'endpoint d'upload lui-même (changement interne à l'implémentation).

```text
POST /documents
Content-Type: multipart/form-data
Request: fichier binaire + champ invoice_id (obligatoire)
Response: 202 Accepted
{
  "data": {
    "id": "uuid",
    "invoice_id": "uuid",
    "file_name": "facture-fournisseur.pdf",
    "file_format": null,
    "file_size": 76030,
    "processing_status": "UPLOADED",
    "failure_reason": null,
    "suggestions": null,
    "uploaded_at": "2026-08-20T05:29:40+00:00",
    "status_url": "/api/v1/documents/{id}"
  }
}
Errors: 400 (Idempotency-Key absente), 422 (format non supporté, invoice_id absent/invalide), 404 (invoice_id inexistant ou d'une autre organisation), 409 (invoice_id d'une facture déjà ANALYZED/ANALYSIS_STALE), 413 (fichier trop volumineux, > 20 Mo - limite résolue, décision produit 2026)
Async: Oui (traitement de l'extraction)
Idempotency: Idempotency-Key obligatoire et honorée (même store que POST /invoices/{id}/compliance-analyses)
Audit: Oui
```

**`invoice_id` obligatoire (résolu, décision produit, Phase 7 - corrige la formulation initiale "champ optionnel" de ce document)** : un document ne peut être importé que rattaché à une `Invoice` de l'organisation appelante dont le statut est `DRAFT` ou `READY_FOR_ANALYSIS` (jamais `ANALYZED`/`ANALYSIS_STALE` - `409`). Contrairement au modèle de données générique (`07-data-model.md` section 13, qui documente `invoice_id` comme optionnel au niveau conceptuel), l'API de cette phase n'expose aucun chemin d'upload sans facture cible : l'interaction d'un import de document avec une facture déjà analysée reste hors périmètre.

**`suggestions` (Phase 7)** : résumé des données extraites (Factur-X uniquement, toujours `null` pour un PDF simple ou un document en échec) destiné à préremplir l'Invoice Editor - **jamais une donnée métier**, l'utilisateur doit confirmer/corriger avant toute écriture réelle sur `Invoice`/`Customer` (docs/06-technical-architecture.md section 11, invariant central de la Phase 7).

**`failure_reason` (Phase 7)** : renseigné uniquement quand `processing_status = FAILED`, une des valeurs `FORMAT_NOT_SUPPORTED`, `MUSTANG_UNAVAILABLE`, `MUSTANG_VALIDATION_FAILED`, `INVALID_DOCUMENT`, `SECURITY_REJECTED` - `FORMAT_NOT_SUPPORTED` (UBL/CII, non traités par cette phase) est explicitement distinct d'une erreur technique, jamais présenté comme un jugement sur la validité du fichier (docs/11-frontend-design-system.md section 37).

**Limite de taille et formats acceptés (résolu, décision produit, 2026)** : **20 Mo maximum par fichier** au MVP. Formats explicitement supportés : PDF (simple), Factur-X (PDF avec XML embarqué), et XML CII/UBL détectés mais non traités (`FORMAT_NOT_SUPPORTED` - décision produit Phase 7, `06-technical-architecture.md` section 11, gap connu documenté). Aucun autre type de fichier n'est accepté - la validation de format et de taille intervient avant tout traitement (section 55).

**Suppression** : `DELETE /documents/{id}` supprime physiquement le fichier du stockage et, le cas échéant, les données extraites contenant des données personnelles/sensibles devenues inutiles, mais **conserve** l'enregistrement d'audit et les résultats de conformité déjà produits - **résolu (décision produit, 2026), harmonisé avec `07-data-model.md` section 30** : le document supprimé reste signalé comme tel dans la traçabilité, sans qu'aucun résultat de conformité déjà produit ne soit perdu (voir aussi section 59).

**Statut de traitement** : non exposé comme ressource séparée - le statut est directement porté par `Document.processing_status`, consultable via `GET /documents/{id}` ci-dessus. Créer une ressource `document-processing-records` distincte n'apporterait pas de valeur au frontend, qui n'a besoin que du statut courant (cohérent avec la consigne de ne pas répliquer chaque table du modèle de données en ressource API, section 3).

## 32. Regulatory Rules API

**Périmètre exposé, volontairement limité** : le frontend a besoin d'afficher la règle et sa source associées à un `ComplianceFinding` (US-COMPLIANCE-003), pas d'interroger librement l'ensemble du référentiel réglementaire.

| Endpoint                     | Méthode | Description                                               | Permission             |
| ---------------------------- | ------- | --------------------------------------------------------- | ---------------------- |
| `/regulatory-rules/{ruleId}` | GET     | Consulter une règle et sa version actuellement en vigueur | `regulatory-rule:read` |

**Non exposé** : `GET /regulatory-rules` (liste complète du référentiel) - aucun besoin utilisateur identifié pour parcourir librement l'ensemble des règles ; les règles pertinentes pour l'utilisateur apparaissent toujours dans le contexte d'un `ComplianceFinding` (qui référence déjà `rule_version_id`, section 29). Ce choix évite d'exposer publiquement la structure interne complète du moteur de règles.

```json
GET /regulatory-rules/mention-siren-client
{
  "data": {
    "id": "mention-siren-client",
    "name": "Numéro SIREN du client",
    "category": "MENTION_OBLIGATOIRE",
    "current_version": {
      "version_number": 1,
      "effective_from": "2026-09-01",
      "source_reference": "02-regulatory-study.md, section 10",
      "confidence_level": "ELEVE"
    }
  }
}
```

## 33. Dashboard API

**GET /dashboard**

```text
Description: Vue synthétique de l'état de conformité de l'organisation (US-DASHBOARD-001).
Permission: dashboard:read
Response: 200 OK
{
  "data": {
    "global_status": "ATTENTION_REQUISE",
    "open_issues_count": 3,
    "warnings_count": 1,
    "recent_analyses": [ { "id": "uuid", "invoice_id": "uuid", "global_result": "NON_CONFORME", "triggered_at": "..." } ],
    "recommended_actions": [ { "message": "string", "related_analysis_id": "uuid" } ]
  }
}
```

Cet endpoint agrège des données déjà exposées ailleurs (`compliance-analyses`, `compliance-findings`) sous une forme pré-calculée adaptée à l'affichage du dashboard - il ne renvoie jamais une extraction brute de la base (cohérent avec la consigne de la mission), et ne crée aucune nouvelle entité (`07-data-model.md`, section 42).

**Règles d'agrégation (résolu, décision produit Phase 9)** : cette version ne montrait qu'un seul exemple de valeur pour `global_status`, sans lister l'ensemble des valeurs ni les règles de calcul - comblé ci-dessous, avec la même rigueur que les autres décisions produit de ce document.

**Portée** : le Dashboard agrège la **dernière `ComplianceAnalysis` `COMPLETED` de chaque `Invoice`** de l'organisation, jamais l'historique complet (App\Compliance\Engine\Repository\ComplianceAnalysisRepository::findLatestCompletedPerInvoice()) - une facture corrigée puis réanalysée ne doit plus jamais compter comme un problème ouvert. C'est la différence structurelle avec `GET /compliance-analyses` (section 29 bis), qui liste au contraire l'historique complet, sans jamais écraser une analyse antérieure.

**`global_status`** (`App\Compliance\Engine\Enum\DashboardGlobalStatus`) : enum dédié à 4 valeurs, **jamais une réutilisation de `ComplianceResult`** - sémantique différente, `ComplianceResult` répondant du résultat d'un finding/d'une règle précise, `global_status` d'un agrégat de portefeuille sur plusieurs factures.

| Valeur               | Condition                                                                                   |
| --------------------- | -------------------------------------------------------------------------------------------- |
| `AUCUNE_ANALYSE`      | Aucune `ComplianceAnalysis` `COMPLETED` n'existe encore pour l'organisation                   |
| `ATTENTION_REQUISE`    | `open_issues_count > 0` (précédence la plus forte)                                          |
| `AVERTISSEMENT`        | `open_issues_count = 0` et `warnings_count > 0`                                              |
| `CONFORME`             | `open_issues_count = 0` et `warnings_count = 0` (au moins une analyse existe)               |

`AUCUNE_ANALYSE` n'est **jamais** confondu avec `CONFORME` : distingue explicitement « aucune facture analysée » de « toutes les factures analysées sont conformes » (US-DASHBOARD-001, cohérent avec l'empty state dédié de `11-frontend-design-system.md` section 35).

**Bucketing `open_issues_count` / `warnings_count`**, calculé sur les findings des analyses retenues par la portée ci-dessus :

| `ComplianceResult`         | Bucket             |
| --------------------------- | ------------------- |
| `NON_CONFORME`              | `open_issues_count`  |
| `A_VERIFIER`                 | `open_issues_count`  |
| `INCERTAIN_REGLEMENTAIRE`    | `open_issues_count`  |
| `AVERTISSEMENT`              | `warnings_count`      |
| `CONFORME`                   | Aucun des deux        |
| `NON_APPLICABLE`             | Aucun des deux        |

Justification : les trois états du bucket `open_issues_count` appellent tous une action ou une clarification de l'utilisateur (correction, donnée manquante, incertitude réglementaire à confirmer), même si leur nature diffère - regroupés sous « problème » plutôt que dispersés en trois compteurs distincts, pour rester lisible sur un dashboard volontairement simple (`11-frontend-design-system.md`, section 34 : « à éviter... un dashboard rempli de statistiques génériques »).

**`recommended_actions`** : dérivées uniquement des findings des buckets `open_issues_count` dont `correction_action` est non nul et non vide - un finding sans action concrète n'est jamais transformé en action recommandée. Dédupliquées par message (un même libellé n'apparaît qu'une fois, même sur deux factures distinctes), ordre déterministe (analyse la plus récente d'abord, puis id de finding croissant), plafonné à 5. `App\Compliance\Engine\Service\DashboardAggregator` reste une vue de lecture pure : il ne modifie jamais aucune entité et n'introduit aucune règle de recommandation nouvelle au-delà de ce bucketing.

**`recent_analyses`** : les analyses les plus récentes parmi celles retenues par la portée ci-dessus, triées par `triggered_at` décroissant, plafonné à 5.

## 34. Notifications API

| Endpoint                   | Méthode | Description                       | Permission            |
| -------------------------- | ------- | --------------------------------- | --------------------- |
| `/notifications`           | GET     | Lister les notifications reçues (paginé) | `notification:read` (authentifié + propriétaire uniquement, jamais un rôle - voir précision ci-dessous) |
| `/notifications/{id}/read` | PATCH   | Marquer comme lue                 | `notification:update` (authentifié + propriétaire uniquement, même précision) |
| `/organizations/current/notifications` | POST | Envoyer une notification aux membres de son organisation (US-NOTIFICATION-003, Phase 14) | `notification:send_team` (rôle - OWNER/ADMIN uniquement) |

**Précision (revue de complétude Phase 14)** : contrairement à `team:invite`/`team:manage_roles`/
`notification:send_team` (permissions par **rôle**, vérifiées par
`App\Shared\Security\OrganizationPermissionVoter`), `notification:read` et
`notification:update` ne sont **jamais** des permissions par rôle - n'importe quel membre,
quel que soit son rôle, lit et marque comme lues ses propres notifications. L'autorisation
réelle ici est une vérification de **propriété** (`recipient_user_id = utilisateur courant`),
appliquée au niveau du repository (`App\Notification\Repository\NotificationRepository`),
jamais au niveau d'un Voter de rôle - ces deux noms de permission désignent donc un contrôle
d'appartenance de ressource, pas une entrée de la matrice `04-product-requirements.md` section
21.1.

`GET /notifications`/`PATCH /notifications/{id}/read` : périmètre P2 pour le rappel système
(`05-user-stories.md`, US-NOTIFICATION-001) - endpoints définis pour cohérence du contrat, non
bloquants pour le MVP.

```text
POST /organizations/current/notifications
Description: Envoyer une notification à un ou plusieurs membres explicitement choisis de son
  organisation (Phase 14).
Permission: notification:send_team (OWNER, ADMIN uniquement - matrice PRD §21.1)
Request: {
  "recipient_ids": ["uuid", ...],   // destinataires choisis nommément par l'expéditeur, jamais un ciblage implicite "tous les membres" - doivent tous appartenir à l'organisation de l'appelant
  "message": "string"
}
Response: 201 Created
{ "data": { "sender_type": "ORGANIZATION_OWNER", "target_type": "ORGANIZATION_MEMBERS", "status": "sent", "recipient_count": 3 } }
Errors: 403 (appelant COLLABORATOR) ; 422 VALIDATION_ERROR (recipient_ids vide, ou contenant un utilisateur hors de l'organisation - jamais 403 pour ce dernier cas, cohérent avec §46).
Idempotency-Key: requise.
Audit: Oui - AuditLogEntry(event_type="TEAM_NOTIFICATION_SENT") - porte uniquement recipient_count, jamais le contenu du message.
```

**Correction (Phase 14, même patron que la correction D1 de la section 26)** : la version
précédente de cet exemple de réponse montrait un unique objet `{ "id", "sender_type",
"target_type": "ORGANIZATION_MEMBERS", "status": "pending" }`, incompatible avec la
résolution immédiate des destinataires retenue à l'implémentation (une ligne `Notification`
par destinataire effectif, chacune `target_type: USER` - voir `07-data-model.md` section 21
et le raisonnement documenté sur `App\Notification\Enum\TargetType`). La réponse ci-dessus
décrit l'action d'envoi dans son ensemble (`target_type: ORGANIZATION_MEMBERS`,
`status: sent` puisque l'envoi est synchrone, `recipient_count` plutôt qu'un `id` unique
puisqu'aucune ligne canonique n'existe) - chaque destinataire retrouve sa propre notification,
individuellement, via `GET /notifications`.

**Notifications à portée plateforme** (ciblage individuel/organisation/segment/diffusion
globale, `sender_type=PLATFORM_ADMIN`) : voir section 38 (Platform Administration API) - jamais
accessible via cet endpoint organisation, réservées au rôle `PlatformAdministrator`.

## 35. AI Assistant API

**Principe non négociable, rappelé explicitement** : l'IA ne peut jamais produire ou modifier un résultat de conformité - elle ne fait que l'expliquer (`04-product-requirements.md` section 17 ; `06-technical-architecture.md` section 14-15).

| Endpoint                                 | Méthode | Description                                                                  | Permission      |
| ---------------------------------------- | ------- | ---------------------------------------------------------------------------- | --------------- |
| `/compliance-findings/{id}/explanations` | POST    | Demander une reformulation pédagogique d'un finding déjà produit (US-AI-001) | `assistant:use` |
| `/assistant/questions`                   | POST    | Poser une question générale de compréhension (US-AI-002)                     | `assistant:use` |

```text
POST /compliance-findings/{id}/explanations
Request: {} (aucune donnée requise au-delà de l'identifiant du finding - le contexte est résolu côté serveur à partir du finding déjà existant, jamais fourni librement par le client, cf. minimisation 06-technical-architecture.md §14)
Response: 200 OK
{
  "data": {
    "finding_id": "uuid",
    "explanation": "string (texte reformulé)",
    "source": "Généré par assistance IA à partir du résultat déterministe existant"
  }
}
Errors: 403 EMAIL_VERIFICATION_REQUIRED (email non vérifié, §7) ; 404 (finding inexistant ou appartenant à une autre organisation, jamais 403 pour ce cas précis, §46) ; 429 (limite de débit dépassée, §22) ; 503 (fournisseur IA indisponible) → dans ce cas, le frontend doit utiliser le message par défaut déjà présent dans le finding (ComplianceFinding.message, 07-data-model.md §18), jamais bloquer l'affichage du résultat lui-même (fallback, 06-technical-architecture.md §14-15).
Async: Non - synchrone, un seul appel HTTP borné au fournisseur IA dans le cycle requête/réponse (timeout court, pas de retry automatique, cohérent avec 06-technical-architecture.md §16). Décision Phase 8, cohérente avec le précédent de la Phase 5 sur POST /invoices/{id}/compliance-analyses (§30) : le chemin 202/status_url documenté comme capacité générale de l'architecture n'est pas retenu ici, la latence d'un appel Mistral pour une reformulation courte restant bornée et ne justifiant pas un worker Messenger.
Idempotency: Non nécessaire (opération de lecture augmentée, sans effet persistant sur ComplianceFinding).
Audit: Oui - trace de la tentative (succès/échec, identifiant du finding), sans jamais inclure le prompt ni le texte généré (10-security-privacy.md §35).
```

**Contrainte structurelle du contrat** : cet endpoint ne prend **jamais** en paramètre l'ensemble d'une facture ou d'une organisation - uniquement l'identifiant d'un `ComplianceFinding` déjà produit. Cette restriction du contrat lui-même est ce qui empêche, au niveau de l'API, que l'IA reçoive plus de contexte que nécessaire (minimisation, cohérent avec `06-technical-architecture.md` section 14).

```text
POST /assistant/questions
Request:
{
  "question": "string (1 à 500 caractères)"
}
Response: 200 OK
{
  "data": {
    "question": "string (reprise telle quelle)",
    "answer": "string (réponse ancrée dans 02-regulatory-study.md)",
    "source": "Généré par assistance IA à partir de l'étude réglementaire du produit (02-regulatory-study.md)"
  }
}
Errors: 403 EMAIL_VERIFICATION_REQUIRED (email non vérifié, §7) ; 422 VALIDATION_ERROR (question vide ou supérieure à 500 caractères, §15) ; 429 (limite de débit dépassée, §22, budget partagé avec /compliance-findings/{id}/explanations) ; 503 (fournisseur IA indisponible) → aucun contenu de repli à afficher ici (contrairement à l'endpoint d'explication, il n'existe pas de message par défaut préexistant pour une question libre), le frontend affiche un message calme invitant à réessayer.
Async: Non - même principe que POST /compliance-findings/{id}/explanations ci-dessus.
Idempotency: Non nécessaire.
Audit: Oui - trace de la tentative (succès/échec, longueur de la question), jamais la question elle-même ni la réponse générée (10-security-privacy.md §35).
```

**Ancrage de la réponse (US-AI-002, décision Phase 8)** : le contexte transmis au fournisseur IA pour cet endpoint inclut le texte intégral de `02-regulatory-study.md` (~20k tokens, fenêtre de contexte du modèle retenu très largement suffisante - vérifié sur la documentation Mistral actuelle au moment de l'implémentation), jamais un mécanisme de recherche/retrieval ni un glossaire séparé qui dupliquerait ce contenu et risquerait de diverger de la source. Le prompt système impose explicitement de ne jamais présenter une information comme provenant de l'étude si elle n'y figure pas réellement, et de préserver les mentions "à confirmer" de l'étude plutôt que de les présenter comme certaines.

## 36. Integrations API

**Non exposée au MVP.** Aucune intégration externe active (`06-technical-architecture.md`, section 16 : IA, email et stockage sont des dépendances internes encapsulées, pas des intégrations pilotées par l'utilisateur). Si une intégration avec une plateforme agréée ou un outil de validation Factur-X tiers devenait active (Future Scope), des endpoints `GET /integrations`, `POST /integrations/{provider}/connect` seraient introduits alors, sans rétroaction sur le contrat actuel.

## 37. Subscription API

**Non exposée au MVP.** Cohérent avec `07-data-model.md` (section 24) : `Subscription`/`Plan`/`SubscriptionStatus` ne sont **pas implémentés dans le cœur du MVP** - orientation Freemium + abonnement Pro retenue mais provisoire, sans intégration à un PSP (type Stripe) à ce stade, validation marché toujours requise (`03-market-analysis.md`). Aucun endpoint `/subscriptions` ou `/plans` n'est créé par anticipation.

## 38. Administration API

Deux sous-espaces distincts, sous un préfixe séparé de l'API utilisateur et un mécanisme
d'authentification propre à chacun (non détaillé ici, renvoyé à `10-security-privacy.md`) :

### 38.1 Regulatory Rules Administration (interne, historique)

```text
/api/v1/admin/rule-versions   POST   Créer une nouvelle version de règle (jamais de PATCH/PUT - immutabilité, 07-data-model.md §16)
/api/v1/admin/rule-versions   GET    Lister les versions existantes d'une règle
```

Cette API n'est **jamais** accessible avec les permissions d'un `OWNER` d'organisation ni d'un
`PlatformAdministrator` (rôles distincts, ADR-009) - réservée à un accès opérationnel interne
(développeur solo au MVP), cohérent avec `05-user-stories.md` (Epic Administration, partie
interne).

### 38.2 Platform Administration API (Phase 15, nouveau, ADR-009)

**Préfixe distinct `/api/v1/platform-admin/`, authentification `PlatformAdministrator`
exclusivement (MFA obligatoire, `10-security-privacy.md`)** - jamais accessible avec un jeton
tenant-scoped (`OWNER`/`ADMIN`/`COLLABORATOR`), et réciproquement un jeton
`PlatformAdministrator` n'est jamais accepté sur l'API utilisateur normale (section 9, étendue).

| Endpoint                                          | Méthode | Description                                              | Permission                  |
| -------------------------------------------------- | ------- | ---------------------------------------------------------- | ---------------------------- |
| `/platform-admin/auth/login`                       | POST    | Étape 1/2 : email + mot de passe, renvoie un ticket `mfa_challenge` (jamais un jeton exploitable) | `PUBLIC_ACCESS` |
| `/platform-admin/auth/mfa/verify`                  | POST    | Étape 2/2 : consomme le ticket + code TOTP, renvoie le JWT complet | `PUBLIC_ACCESS` |
| `/platform-admin/auth/refresh`                     | POST    | Rotation du refresh token (cookie `platform_admin_refresh_token`, distinct du tenant) | `PUBLIC_ACCESS` |
| `/platform-admin/me`                                | GET     | Identité de l'administrateur authentifié - écart de spécification comblé à l'implémentation (Phase 15, `12-roadmap.md` bilan), nécessaire à la restauration de session côté frontend | `ROLE_PLATFORM_ADMIN` |
| `/platform-admin/organizations`                    | GET     | Lister les organisations (paginé, filtrable)                | `platform:organizations:read` |
| `/platform-admin/organizations/{id}`               | GET     | Consulter une organisation (détail, membres)                 | `platform:organizations:read` |
| `/platform-admin/organizations/{id}/suspend`       | POST    | Suspendre une organisation (US-PLATFORMADMIN-002)             | `platform:organizations:suspend` |
| `/platform-admin/organizations/{id}/reactivate`    | POST    | Réactiver une organisation                                    | `platform:organizations:suspend` |
| `/platform-admin/audit-events`                     | GET     | Consulter l'audit trail cross-tenant (US-PLATFORMADMIN-003)   | `platform:audit:read`        |
| `/platform-admin/notifications`                    | POST    | Envoyer une notification ciblée/segmentée/diffusée (US-PLATFORMADMIN-004) | `platform:notifications:send` |
| `/platform-admin/health`                           | GET     | Indicateurs de santé applicative (US-PLATFORMADMIN-005)       | `platform:health:read`       |

```text
POST /platform-admin/organizations/{id}/suspend
Response: 200 OK - Organization.suspended_at renseigné.
Effet: tous les membres de l'organisation perdent l'accès applicatif immédiatement (07-data-model.md, invariant Organization Phase 15).
Audit: Oui, obligatoire - AuditLogEntry(event_type="organization_suspended", actor=PlatformAdministrator.id) - jamais une action silencieuse.
```

```text
POST /platform-admin/notifications
Request: {
  "target_type": "USER" | "ORGANIZATION" | "SEGMENT" | "ALL",
  "target_id": "uuid"        | null,   // requis si USER ou ORGANIZATION
  "target_criteria": { ... } | null,   // requis si SEGMENT (07-data-model.md §21 : critères repris de FiscalContext)
  "message": "string"
}
Response: 201 Created
{ "data": { "id": "uuid", "sender_type": "PLATFORM_ADMIN", "target_type": "string", "estimated_recipient_count": "integer" } }
Errors: 422 VALIDATION_ERROR (target_id/target_criteria incohérent avec target_type).
Audit: Oui, obligatoire - AuditLogEntry(event_type="platform_notification_sent", actor=PlatformAdministrator.id, target_type, target_criteria).
```

```text
GET /platform-admin/health
Response: 200 OK
{
  "data": {
    "compliance_engine_failure_rate_24h": "string (decimal)",
    "async_jobs_dead_letter_count": "integer",
    "ai_calls_volume_24h": "integer",
    "ai_estimated_cost_24h": "string (decimal)",
    "api_health": "ok" | "degraded"
  }
}
```
Explicitement limité au niveau applicatif (`04-product-requirements.md`, FR-PLATFORMADMIN-005) -
aucun indicateur d'infrastructure réelle (uptime, ressources serveur) tant qu'aucun hébergeur
n'est retenu (Phase 17).

**Isolation** : ces endpoints lisent/écrivent à travers toutes les organisations - **jamais**
via le mécanisme `TenantFilter` utilisé par le reste de l'API (section 9), toujours via des
requêtes explicitement cross-tenant réservées à ce module (`06-technical-architecture.md`,
ADR-009). Toute action de cette API est journalisée avec l'identité de l'acteur
`PlatformAdministrator`, sans exception.

### 38.3 Platform Analytics API (Phase 16)

| Endpoint                          | Méthode | Description                                              | Permission              |
| ----------------------------------- | ------- | ---------------------------------------------------------- | ------------------------ |
| `/platform-admin/analytics/summary` | GET     | Statistiques agrégées (US-ANALYTICS-001)                    | `platform:analytics:read` |
| `/platform-admin/analytics/trends`  | GET     | Évolution temporelle des statistiques (US-ANALYTICS-002)    | `platform:analytics:read` |

Même autorisation `PlatformAdministrator` que la section 38.2, jamais un accès distinct moins
strict (`06-technical-architecture.md`, ADR-009, conséquences). Lecture seule, agrégation
construite sur le même patron que `DashboardAggregator` (Phase 9), jamais un nouveau mécanisme
d'agrégation inventé.

**Deux sémantiques volontairement distinctes, jamais interchangeables** : `summary` est un
cumul sur toute l'historique de la plateforme (jamais restreint à une fenêtre temporelle) ;
`trends` est une activité par jour de déclenchement, restreinte à une fenêtre fixe. Un futur
développeur ne doit jamais aligner l'une sur la logique de l'autre, ni sur le patron "dernière
analyse par facture" du Dashboard (Phase 9, hors de propos ici).

```text
GET /platform-admin/analytics/summary
Response: 200 OK
{
  "data": {
    "organizations_count": "integer",
    "users_count": "integer",
    "compliance_analyses_count": "integer",
    "compliance_rate": "string (decimal)"
  }
}
```
`compliance_analyses_count` = nombre de `ComplianceAnalysis` avec `status = COMPLETED`, toute
l'historique, tous tenants confondus. `compliance_rate` = `conforme / completed` où `conforme`
compte uniquement `globalResult = CONFORME` parmi ces mêmes analyses COMPLETED - `FAILED`
n'entre ni dans le numérateur ni dans le dénominateur (jamais un statut technique compté comme
un résultat métier). `"0"` si `compliance_analyses_count` est nul, jamais une division par
zéro. `users_count` exclut les comptes soft-deleted (`07-data-model.md`, section 30).

```text
GET /platform-admin/analytics/trends
Response: 200 OK
{
  "data": { "points": [
    {
      "date": "YYYY-MM-DD",
      "organizations_created": "integer",
      "users_created": "integer",
      "compliance_analyses_count": "integer",
      "compliance_rate": "string (decimal)"
    }
  ] },
  "meta": { "window_days": 90 }
}
```
Fenêtre fixe de 90 jours glissants, un point par jour, pas de `?since`/`?until` au MVP de cette
phase - décision produit assumée pour rester au plus simple (`06-technical-architecture.md`,
section 3). Bornes en UTC (`../CLAUDE.md`, section 11 : "horodatages ISO 8601 UTC", aucune
autre convention de fuseau horaire n'existe dans ce projet) : `from` = aujourd'hui à 00:00:00
UTC moins 89 jours, `until` (exclusif) = demain à 00:00:00 UTC - exactement 90 points, toujours
présents même sans aucune donnée ce jour-là (`compliance_analyses_count: 0`,
`compliance_rate: "0"`), jamais un jour absent. `compliance_analyses_count`/`compliance_rate`
d'un point donné portent uniquement sur les `ComplianceAnalysis` COMPLETED dont `triggeredAt`
tombe ce jour-là (même formule que `summary` ci-dessus, appliquée au sous-ensemble du jour).

## 39. Audit API

**GET /audit-events**

```text
Description: Consulter le journal d'audit de sa propre organisation (US-HISTORY-001, partiellement).
Permission: audit:read
Query parameters: ?entity_type=Invoice&entity_id=uuid&since=...&until=...
Response: 200 OK, paginé
{
  "data": [
    { "event_type": "invoice_created", "occurred_at": "...", "actor": { "type": "user", "id": "uuid" } }
  ],
  "meta": { "pagination": { "..." } }
}
```

**Restriction** : cet endpoint expose uniquement les `AuditLogEntry` dont `organization_id` correspond au tenant de l'utilisateur (filtrage systématique, section 9) ; les événements globaux (`organization_id` nul, par exemple `rule_version_created`) ne sont **jamais** exposés via cet endpoint utilisateur - ils ne sont visibles que via l'API d'administration (section 38).

## 40. Query parameters - synthèse

Convention uniforme (détail sections 16, 43) : pagination `?page=1&per_page=20`, filtrage `?champ=valeur` un paramètre par attribut, tri `?sort=-created_at`, recherche `?search=...` limitée aux ressources qui le justifient.

## 41. Pagination

```json
{
  "data": [],
  "meta": {
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total_count": 134,
      "total_pages": 7
    }
  }
}
```

**Choix retenu : pagination par page/offset**, plutôt que cursor-based, jugée suffisante pour le volume attendu au MVP (`07-data-model.md`, section 38) et plus simple à implémenter et à consommer côté frontend. Une migration vers une pagination par curseur resterait possible sans changement de contrat côté enveloppe `meta.pagination` si le volume le justifiait un jour (`07-data-model.md`, section 41).

Appliquée systématiquement à : `GET /invoices`, `GET /customers`, `GET /invoices/{id}/compliance-analyses` (par facture), `GET /compliance-analyses` (historique organisation-wide, section 29 bis, Phase 9), `GET /compliance-analyses/{id}/findings`, `GET /audit-events`, `GET /notifications`.

## 42. HTTP Status Codes

| Code                                          | Usage                                                                                                                                                                                                      |
| --------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `200 OK`                                      | Lecture réussie, ou opération synchrone complétée avec succès (y compris un résultat de conformité `NON_CONFORME`, section 46)                                                                             |
| `201 Created`                                 | Création réussie d'une ressource (`Invoice`, `Customer`, `User`)                                                                                                                                           |
| `202 Accepted`                                | Opération acceptée mais traitée de façon asynchrone (upload de document, analyse dépendant d'une extraction - section 30)                                                                                  |
| `204 No Content`                              | Suppression réussie sans contenu à retourner                                                                                                                                                               |
| `400 Bad Request`                             | Requête syntaxiquement invalide (JSON malformé)                                                                                                                                                            |
| `401 Unauthorized`                            | Authentification manquante ou invalide                                                                                                                                                                     |
| `403 Forbidden`                               | Authentifié mais non autorisé pour cette ressource                                                                                                                                                         |
| `404 Not Found`                               | Ressource inexistante - **utilisé aussi pour masquer l'existence d'une ressource d'une autre organisation** plutôt que `403`, pour ne pas révéler qu'une ressource existe ailleurs (voir section 60, IDOR) |
| `409 Conflict`                                | Conflit d'idempotence (section 20), de concurrence (section 21), ou de transition d'état invalide (section 28)                                                                                             |
| `422 Unprocessable Entity`                    | Erreur de validation métier (section 15)                                                                                                                                                                   |
| `429 Too Many Requests`                       | Limite de débit dépassée (section 22)                                                                                                                                                                      |
| `500 Internal Server Error`                   | Erreur technique non catégorisée                                                                                                                                                                           |
| `502 Bad Gateway` / `503 Service Unavailable` | Dépendance externe indisponible (IA, stockage) - jamais utilisé pour masquer un résultat de conformité (section 46)                                                                                        |

## 43. Error Contract - rappel

Voir sections 14-15 pour le détail complet ; non dupliqué ici.

## 44. Backward Compatibility

**Changements non cassants (autorisés en évolution mineure de `v1`)** :

- Ajout d'un nouveau champ optionnel dans une réponse.
- Ajout d'une nouvelle valeur d'énumération (à condition que le client tolère les valeurs inconnues, section 19).
- Ajout d'un nouvel endpoint.
- Ajout d'un nouveau paramètre de requête optionnel.

**Changements cassants (nécessitent `v2`)** :

- Suppression ou renommage d'un champ existant.
- Changement de type d'un champ (par exemple, montant de `string` à `number`).
- Changement du code HTTP retourné par un endpoint existant pour un cas déjà documenté.
- Changement de la signification d'une valeur d'énumération existante.

## 45. API Evolution

```text
v1 (MVP)
  ↓ ajouts non cassants (nouveaux champs optionnels, nouveaux endpoints)
v1.x (toujours "v1" côté URL - le versionnement n'affecte que les changements cassants)
  ↓ si un changement cassant devient nécessaire
v2 (nouvelle base URL /api/v2, v1 maintenue en parallèle pendant une période de dépréciation à définir)
```

Aucune stratégie de versionnement plus complexe (négociation de contenu, versionnement par en-tête) n'est retenue, jugée disproportionnée pour un produit à un seul client frontend maîtrisé en interne (cohérent avec `06-technical-architecture.md` section 18).

## 46. Compliance errors vs API errors

Rappel structurant, explicitement isolé conformément à la mission :

```text
API Error (400/401/403/404/409/422/429/5xx)
   → le système n'a pas pu exécuter correctement la requête.

Compliance Finding (toujours via 200/202, jamais un code d'erreur)
   → le système a correctement exécuté l'analyse et a découvert un problème de conformité.
```

Exemple concret :

```json
HTTP 200 OK
{
  "data": {
    "id": "uuid",
    "status": "COMPLETED",
    "global_result": "NON_CONFORME",
    "findings": [
      { "result": "NON_CONFORME", "rule": { "id": "mention-siren-client", "version": 1 }, "message": "..." }
    ]
  }
}
```

Ce comportement découle directement de `06-technical-architecture.md` (section 25, distinction Business Error / Technical Error / Compliance Result) et constitue l'une des contraintes les plus structurantes de tout ce contrat API.

## 47. Idempotency, Concurrency, Rate Limiting - rappel

Voir sections 20, 21, 22 - non dupliqué ici.

## 48. Versionnement des règles dans l'API

Toute réponse contenant un `ComplianceFinding` expose systématiquement :

```json
{
  "rule": {
    "id": "mention-siren-client",
    "version": 1,
    "source_reference": "02-regulatory-study.md, section 10",
    "confidence_level": "ELEVE",
    "effective_from": "2026-01-01",
    "effective_until": null
  }
}
```

Cohérent avec `07-data-model.md` (section 16, 18) : le `finding` référence toujours la **version précise** de la règle utilisée, jamais « la règle en général » - permettant au frontend d'afficher, si nécessaire, que le résultat a été produit avec une version de règle donnée à une date donnée, condition nécessaire à US-HISTORY-001. `effective_from`/`effective_until` (Phase 6, `11-frontend-design-system.md` section 29 : niveau 3 du Compliance Finding UI, "quand la règle s'applique") sont toujours présents, y compris `effective_until` quand il vaut `null` (RuleVersion encore active) - jamais une clé omise.

**Écart connu, assumé** : ce document mentionnait auparavant un `evaluated_at` par finding, jamais implémenté (Phase 5) et volontairement non ajouté en Phase 6 - un finding est toujours évalué au moment exact de son `ComplianceAnalysis` parente, déjà exposée via `ComplianceAnalysis.completed_at` (section 29-30) ; dupliquer cette date sur chaque finding n'apporterait aucune information supplémentaire pour un coût de schéma réel (nouvelle colonne sur une entité jamais modifiée après création). Ne pas réintroduire ce champ sans un besoin concret qui le justifie.

## 49. Audit et Request ID

- Chaque requête entrante peut porter un `X-Request-ID` fourni par le client ; si absent, le serveur en génère un.
- Ce `request_id` est retourné dans chaque réponse (succès ou erreur, section 14) et propagé dans les logs et, le cas échéant, dans l'`AuditLogEntry` associé - permettant de relier une entrée d'audit à la requête HTTP qui l'a produite.

## 50. OpenAPI

Ce document est conçu pour être directement traduisible en `openapi.yaml`. Extrait représentatif des conventions retenues (non exhaustif) :

```yaml
openapi: 3.1.0
info:
  title: Assistant de conformite a la facturation electronique - API
  version: '1.0'
servers:
  - url: https://api.example.fr/api/v1
paths:
  /invoices/{invoiceId}/compliance-analyses:
    post:
      summary: Lancer une analyse de conformite sur une facture
      security:
        - bearerAuth: []
      parameters:
        - name: invoiceId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '200':
          description: Analyse completee de facon synchrone
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ComplianceAnalysis'
        '202':
          description: Analyse acceptee, traitement asynchrone en cours
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/AsyncOperationAccepted'
components:
  schemas:
    ComplianceAnalysis:
      type: object
      properties:
        id: { type: string, format: uuid }
        status: { type: string, enum: [PENDING, RUNNING, COMPLETED, FAILED] }
        global_result:
          type: string
          enum:
            [
              CONFORME,
              NON_CONFORME,
              AVERTISSEMENT,
              NON_APPLICABLE,
              A_VERIFIER,
              INCERTAIN_REGLEMENTAIRE,
            ]
    AsyncOperationAccepted:
      type: object
      properties:
        id: { type: string, format: uuid }
        status: { type: string, enum: [PENDING] }
        status_url: { type: string }
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
```

## 51. Exemples de requêtes/réponses

**Création d'entreprise** - voir section 24 (`PATCH /organizations/current`).

**Création de client**

```json
POST /customers
{ "customer_type": "PARTICULIER", "name": "Jean Dupont", "country": "FR" }

201 Created
{ "data": { "id": "c-uuid", "customer_type": "PARTICULIER", "name": "Jean Dupont", "country": "FR" } }
```

**Création de facture** - voir section 27.

**Lancement d'une analyse**

```json
POST /invoices/inv-uuid/compliance-analyses

202 Accepted
{ "data": { "id": "ca-uuid", "status": "PENDING", "status_url": "/api/v1/compliance-analyses/ca-uuid" } }
```

**Résultat d'analyse**

```json
GET /compliance-analyses/ca-uuid

200 OK
{
  "data": {
    "id": "ca-uuid",
    "status": "COMPLETED",
    "global_result": "NON_CONFORME",
    "triggered_at": "2026-08-17T10:00:00Z",
    "completed_at": "2026-08-17T10:00:03Z"
  }
}
```

**Erreur de validation** - voir section 15.

**Liste paginée**

```json
GET /invoices?status=ANALYZED&page=1&per_page=20

200 OK
{
  "data": [ { "id": "inv-1", "status": "ANALYZED" } ],
  "meta": { "pagination": { "page": 1, "per_page": 20, "total_count": 4, "total_pages": 1 } }
}
```

**Upload de document** - voir section 31.

**Assistant IA** - voir section 35.

## 52. Matrice Endpoint → User Story

| Endpoint                                          | User Story             | Persona       | Permission           | Priorité |
| ------------------------------------------------- | ---------------------- | ------------- | -------------------- | -------- |
| POST /auth/register                               | US-AUTH-001            | Tous          | -                    | P0       |
| POST /auth/login                                  | US-AUTH-002            | Tous          | -                    | P0       |
| POST /auth/password/forgot, /reset                | US-AUTH-003            | Tous          | -                    | P0       |
| PATCH /organizations/current                      | US-COMPANY-001/002/003 | Tous          | organization:update  | P0       |
| POST /customers                                   | US-CUSTOMER-001/002    | Persona 1     | customer:create      | P0       |
| POST /invoices                                    | US-INVOICE-002         | Persona 1     | invoice:create       | P0       |
| POST /documents                                   | US-INVOICE-001         | Persona 1     | document:create      | P0       |
| GET /eligibility-diagnostics/current              | US-COMPLIANCE-001      | Persona 1, 2  | compliance:read      | P0       |
| POST /invoices/{id}/compliance-analyses           | US-COMPLIANCE-002      | Persona 1     | compliance:create    | P0       |
| GET /compliance-analyses/{id}/findings            | US-COMPLIANCE-003/004  | Persona 1     | compliance:read      | P0       |
| (implicite dans le finding) distinction PDF       | US-COMPLIANCE-005      | Persona 1     | -                    | P0       |
| POST /invoices/{id}/compliance-analyses (relance) | US-COMPLIANCE-006      | Persona 1     | compliance:create    | P0       |
| GET /invoices/{id}/compliance-analyses            | US-COMPLIANCE-006/007  | Persona 1, SB | compliance:read      | P1/P2    |
| GET /documents/{id}, DELETE /documents/{id}       | US-DOCUMENT-001/002    | Tous          | document:read/delete | P1       |
| GET /compliance-analyses (historique)             | US-HISTORY-001         | Tous          | compliance:read      | P1       |
| GET /dashboard                                    | US-DASHBOARD-001       | Persona SB    | dashboard:read       | P1       |
| POST /compliance-findings/{id}/explanations       | US-AI-001              | Tous          | assistant:use        | P1       |
| POST /assistant/questions                         | US-AI-002              | Tous          | assistant:use        | P1       |
| GET /notifications, PATCH .../read                | US-NOTIFICATION-001    | Tous          | notification:\*      | P2       |

## 53. Matrice Endpoint → Data Model

| Endpoint                                | Ressources              | Entités (`07-data-model.md`)                                          | Lecture/Écriture   |
| --------------------------------------- | ----------------------- | --------------------------------------------------------------------- | ------------------ |
| POST /auth/register                     | users                   | User, Membership, Organization (initiale)                             | Écriture           |
| PATCH /organizations/current            | organizations           | Organization, FiscalContext                                           | Lecture + Écriture |
| POST /customers                         | customers               | Customer                                                              | Écriture           |
| POST /invoices                          | invoices                | Invoice, InvoiceLine                                                  | Écriture           |
| POST /documents                         | documents               | Document, DocumentProcessingRecord                                    | Écriture           |
| GET /eligibility-diagnostics/current    | eligibility-diagnostics | EligibilityDiagnostic, FiscalContext                                  | Lecture            |
| POST /invoices/{id}/compliance-analyses | compliance-analyses     | ComplianceAnalysis, ContextSnapshot, ComplianceFinding, AuditLogEntry | Écriture           |
| GET /compliance-analyses/{id}/findings  | compliance-findings     | ComplianceFinding, RuleVersion (référencée)                           | Lecture            |
| GET /regulatory-rules/{id}              | regulatory-rules        | RegulatoryRule, RuleVersion                                           | Lecture            |
| GET /dashboard                          | (agrégat)               | ComplianceAnalysis, ComplianceFinding (lecture agrégée)               | Lecture            |
| GET /audit-events                       | audit-events            | AuditLogEntry                                                         | Lecture            |
| POST /admin/rule-versions               | admin/rule-versions     | RegulatoryRule, RuleVersion                                           | Écriture (interne) |

## 54. Matrice API → Réglementation

| Endpoint                                                           | Exigence réglementaire                                                                              | Source                                                     | Impact                                                                            |
| ------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- | --------------------------------------------------------------------------------- |
| GET /eligibility-diagnostics/current                               | Calendrier différencié selon la taille de l'entreprise ; assujettissement même en franchise en base | `02-regulatory-study.md`, sections 5-6                     | Détermine le contenu de `reception_obligation_date`/`emission_obligation_date`    |
| POST /invoices/{id}/compliance-analyses (finding sur SIREN client) | Nouvelle mention obligatoire SIREN client                                                           | `02-regulatory-study.md`, section 10                       | Fonde le `ComplianceFinding` correspondant                                        |
| POST /invoices/{id}/compliance-analyses (finding sur format)       | Définition de la facture électronique conforme                                                      | `02-regulatory-study.md`, section 8                        | Fonde US-COMPLIANCE-005                                                           |
| GET /regulatory-rules/{id}                                         | Traçabilité de la source réglementaire                                                              | `02-regulatory-study.md` (renvoyée via `source_reference`) | Exposition de la source à l'utilisateur (`04-product-requirements.md` section 18) |

## 55. Security API

> Principes uniquement ; le détail complet relève de `10-security-privacy.md`.

- HTTPS obligatoire sur l'ensemble de l'API, sans exception.
- Authentification requise sur toute route hors `/auth/register`, `/auth/login`, `/auth/password/forgot`, `/auth/password/reset`.
- Isolation du tenant appliquée systématiquement (section 9), y compris pour les erreurs (`404` plutôt que `403` pour masquer l'existence d'une ressource d'une autre organisation, section 42).
- Validation stricte de tout payload à la frontière de l'API, avant tout passage aux modules métier (`06-technical-architecture.md` section 18).
- Upload de documents : validation de format et de taille avant tout traitement (section 31 ; `06-technical-architecture.md` section 26).
- Rate limiting a minima sur l'authentification et les opérations coûteuses (section 22).
- CORS restreint au domaine du frontend officiel.
- CSRF : le mécanisme d'authentification retenu (JWT access token en mémoire + Refresh Token en cookie `HttpOnly`/`Secure`/`SameSite`, décision produit 2026, `06-technical-architecture.md` ADR-007) réduit structurellement l'exposition au CSRF par rapport à un cookie de session classique, l'en-tête `Authorization` n'étant jamais transmis automatiquement par le navigateur lors d'une requête cross-site. **Résolu (décision produit, 2026)** : le refresh token étant porté par un cookie `HttpOnly`, une protection CSRF **ciblée sur `/auth/refresh`** est nécessaire et fait partie du contrat de sécurité - ce n'est plus une question conditionnelle. Le détail précis du mécanisme de protection (double-submit token, en-tête personnalisé, etc.) reste renvoyé à `10-security-privacy.md`.
- Aucun secret n'apparaît jamais dans une réponse API (cohérent avec `07-data-model.md` section 22, `secret_reference` opaque).
- Logs de requêtes : ne journalisent jamais le contenu d'un mot de passe, d'un jeton, ou d'un document uploadé - uniquement les métadonnées nécessaires à l'observabilité (section 58).

## 56. Observabilité API

Informations journalisées pour chaque requête : endpoint, méthode, statut HTTP, durée, `request_id` (section 49), identifiant utilisateur et organisation (pour le diagnostic, jamais pour un affichage cross-tenant), catégorie d'erreur le cas échéant (section 46). **Jamais journalisés** : mots de passe, contenu de documents, contenu intégral d'un payload de facture (seules les métadonnées utiles au diagnostic sont conservées) - cohérent avec `06-technical-architecture.md` section 23 et la consigne de ne pas logger de données sensibles inutilement.

## 57. Performance

- Pagination obligatoire sur toute collection potentiellement volumineuse (section 41).
- Aucun endpoint ne doit déclencher de N+1 requêtes visibles côté client - un `GET /invoices/{id}` retourne directement ses `lines` (section 19), évitant un aller-retour supplémentaire.
- Les traitements longs (extraction documentaire, analyse dépendante, IA) suivent le contrat asynchrone (section 30), jamais une attente synchrone non bornée.
- Aucun SLA chiffré n'est fixé arbitrairement ici - à calibrer pendant les tests de charge, cohérent avec la consigne de la mission.

## 58. Risques API

| Risque                                                     | Impact                                                           | Mitigation                                                                                                                                                                 |
| ---------------------------------------------------------- | ---------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Exposition cross-tenant (IDOR)                             | Très élevé - fuite de données financières entre organisations    | Tenant résolu depuis la session, jamais depuis l'URL (section 9) ; `404` plutôt que `403` pour ne pas confirmer l'existence d'une ressource d'un autre tenant (section 42) |
| Endpoints trop puissants (accès direct aux tables)         | Moyen - surface d'attaque et de confusion inutile                | Aucune ressource CRUD générique n'est exposée (`invoice_lines`, `document-processing-records`, etc. non exposés séparément)                                                |
| Payloads trop volumineux (upload)                          | Moyen                                                            | Limite de taille sur l'upload de documents (section 31), à calibrer                                                                                                        |
| Dépendance externe IA (latence, coût, indisponibilité)     | Moyen                                                            | Fallback systématique vers le message figé du finding (section 35) ; rate limiting dédié (section 22)                                                                      |
| Opérations non idempotentes déclenchées en double          | Moyen                                                            | `Idempotency-Key` sur les opérations coûteuses (section 20)                                                                                                                |
| Fuite de données dans les erreurs                          | Moyen                                                            | Messages d'erreur génériques pour l'authentification (section 23) ; pas de trace technique interne exposée dans `error.message`                                            |
| Confusion entre erreur technique et résultat de conformité | Élevé - spécifique à ce produit, atteinte directe à la confiance | Séparation stricte et systématique (section 46), rappelée dans toutes les sections concernées                                                                              |
| Évolution du contrat cassant le frontend                   | Moyen                                                            | Règles de compatibilité explicites (section 44-45)                                                                                                                         |

## 59. Open Questions - état après décisions produit (2026)

| Question initiale                                                                                                | Décision retenue                                                                                                                                                                                                                                                                                                | Statut               |
| ---------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------- |
| Mécanisme JWT (`06-technical-architecture.md`, ADR-007) : stockage du refresh token, protection CSRF associée    | **Résolu** : access token JWT en mémoire côté frontend (jamais `localStorage`), Refresh Token en cookie `HttpOnly`/`Secure`/`SameSite`, protection CSRF ciblée sur `/auth/refresh` (sections 7, 55). Durée de vie exacte des tokens, rotation, révocation : **reste à préciser** dans `10-security-privacy.md`. | Partiellement résolu |
| Vérification d'email obligatoire ou différée                                                                     | **Résolu** : obligatoire avant toute fonctionnalité sensible (upload, analyse persistante, IA, fonctionnalités avancées), non bloquante pour un usage basique du compte (section 7).                                                                                                                            | Résolu               |
| Durée de conservation des clés d'idempotence                                                                     | **Résolu** : 24h par défaut, Redis, clé `idempotency:{tenant}:{key}` (section 20).                                                                                                                                                                                                                              | Résolu               |
| Comportement précis de `PATCH /invoices/{id}` sur une facture déjà `ANALYZED` (nouvelle version vs invalidation) | **Résolu** : invalidation en place, statut `ANALYSIS_STALE`, aucune nouvelle `Invoice` créée, pas de `409 Conflict` bloquant du seul fait de l'analyse préexistante (section 28, harmonisé avec `07-data-model.md` sections 28-29).                                                                             | Résolu               |
| Limite exacte de taille d'upload de documents                                                                    | **Résolu** : 20 Mo par fichier au MVP ; formats PDF, Factur-X, XML CII/UBL si supportés (section 31).                                                                                                                                                                                                           | Résolu               |
| Comportement exact de suppression d'un document déjà analysé                                                     | **Résolu** : fichier supprimé physiquement (et données extraites personnelles/sensibles devenues inutiles anonymisées/supprimées), audit et résultats de conformité conservés (section 31, harmonisé avec `07-data-model.md` section 30).                                                                       | Résolu               |

**Reste explicitement ouvert** (non couvert par les décisions produit 2026, à ne pas considérer comme tranché) : durée de vie exacte des tokens JWT (access/refresh), politique de rotation et de révocation - renvoyées à `10-security-privacy.md` ; calibrage chiffré du rate limiting (section 22) ; détails de mise en œuvre technique de la protection CSRF sur `/auth/refresh` (section 55).

## 60. Impact sur la stratégie de tests et la sécurité

- **`09-test-strategy.md`** doit couvrir chaque endpoint listé dans les sections 23 à 39, en particulier la distinction erreur technique / résultat de conformité (section 46), les transitions d'état de `Invoice` (section 28), l'isolation multi-tenant (section 9, avec des tests explicites de tentative d'accès cross-tenant), et le comportement asynchrone (section 30).
- **`10-security-privacy.md`** doit détailler le mécanisme d'authentification exact (section 7) - en particulier la durée de vie précise des tokens JWT, leur rotation et leur révocation, qui restent ouvertes malgré la décision d'architecture access-en-mémoire/refresh-en-cookie déjà actée (section 59) -, la mise en œuvre technique de la protection CSRF sur `/auth/refresh` (section 55), la gestion des secrets (section 55), ainsi que les mécanismes de rate limiting précis (section 22).

## Informations nécessaires à la stratégie de tests

À l'attention de `09-test-strategy.md` :

- **Tests d'authentification** - inscription, connexion, récupération de compte (section 23), y compris les cas d'erreur (identifiants invalides, email déjà utilisé).
- **Tests d'autorisation** - vérification systématique qu'un `OWNER` ne peut agir que sur les ressources de sa propre organisation.
- **Tests multi-tenant** - tentative explicite d'accès à une ressource d'une autre organisation via son `id` (attendu : `404`, jamais `200` ni `403`, section 42 et 58).
- **Tests de validation** - chaque règle de validation métier (SIREN requis si client professionnel français, cohérence des montants de facture, section 15 et 27).
- **Tests des transitions de facture** - respect strict du cycle `DRAFT → READY_FOR_ANALYSIS → ANALYZED` et rejet des transitions non prévues (section 28).
- **Tests de conformité** - couverture des six états de résultat (`CONFORME`, `NON_CONFORME`, `AVERTISSEMENT`, `NON_APPLICABLE`, `A_VERIFIER`, `INCERTAIN_REGLEMENTAIRE`), y compris le cas d'une donnée manquante produisant `A_VERIFIER` et jamais `NON_CONFORME` par défaut.
- **Tests des erreurs** - respect du contrat d'erreur uniforme (section 14-15) sur l'ensemble des endpoints.
- **Tests d'idempotence** - répétition d'une requête avec la même `Idempotency-Key` (section 20), et détection de conflit en cas de payload différent.
- **Tests asynchrones** - vérification du contrat `202 Accepted` + polling jusqu'à `COMPLETED`/`FAILED` (section 30), y compris le cas d'échec technique explicite.
- **Tests de documents** - upload de formats valides/invalides, fichiers volumineux, documents illisibles (section 31), et vérification que ces erreurs sont catégorisées comme techniques et non comme un résultat de conformité.
- **Tests d'intégrations** - comportement de repli en cas d'indisponibilité du fournisseur IA (section 35, 55).
- **Tests de contrats API** - conformité des réponses au schéma attendu (utile pour une validation automatisée contre le futur `openapi.yaml`, section 50).
- **Tests de sécurité API** - tentatives d'IDOR, absence de secrets dans les réponses (section 55, 58), en-têtes de sécurité.
- **Tests de performance** - comportement de la pagination sous volume croissant (`07-data-model.md` section 38), absence de N+1 sur les endpoints à forte volumétrie.
