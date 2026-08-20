# CLAUDE.md - Backend (Symfony/PHP)

Ce fichier complète `../CLAUDE.md` (règles générales du projet FactuSentinel : sources de vérité, réglementation, positionnement produit, Git, formatage, dépendances, workflow général). **Il ne les recopie pas.** Tout ce qui n'est pas spécifique au backend (pas d'emoji, pas de tiret cadratin, pas de co-auteur Claude dans les commits, principes de vérification Internet, principe « Pourquoi, jamais seulement si », etc.) s'applique ici sans modification - se référer à `../CLAUDE.md`.

Le backend Symfony est **l'autorité métier, réglementaire, API et sécurité** du produit. Le frontend n'est jamais une source de vérité pour une décision de conformité, d'autorisation, ou de validation de donnée.

## 1. Sources de vérité spécifiques au backend

| Document                               | Ce qu'il définit pour le backend                                                                            |
| -------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `../docs/02-regulatory-study.md`       | La réglementation - seule source pour toute règle de conformité codée                                       |
| `../docs/04-product-requirements.md`   | Compliance Engine (section 10-11), Business Rules `BR-*`, gestion des erreurs (section 15), IA (section 17) |
| `../docs/06-technical-architecture.md` | Architecture, modules, ADR-001 à ADR-008 - document d'architecture de référence                             |
| `../docs/07-data-model.md`             | Schéma de données, entités, invariants, états et transitions                                                |
| `../docs/08-api-specification.md`      | Contrats API exposés par le backend                                                                         |
| `../docs/09-test-strategy.md`          | Stratégie de test, en particulier pour le Compliance Engine                                                 |
| `../docs/10-security-privacy.md`       | Sécurité, authentification, RGPD                                                                            |

Note sur les noms de documents : ce dépôt ne contient pas de `05-technical-architecture.md`, `06-data-model.md`, `07-api-specification.md`, `08-test-strategy.md`, `09-security-privacy.md` ni de `10-ai-strategy.md` distinct - les fichiers réellement présents sont ceux listés ci-dessus (numérotés `02` et `04` à `10`). Il n'existe pas de document dédié à une « stratégie IA » : le rôle de l'IA est défini dans `04-product-requirements.md` (section 17), son architecture dans `06-technical-architecture.md` (sections 14-15) et sa sécurité dans `10-security-privacy.md` (sections 28-32) - voir section 12 de ce fichier.

## 2. État réel du projet et stack

Squelette Symfony minimal à ce stade : `backend/src/` ne contient que `Controller/` et `Kernel.php`. Aucun des modules métier de `06-technical-architecture.md` (section 6) n'existe encore. Aucun package Doctrine, Security, Messenger ou JWT n'est installé (`composer.json` ne référence que `symfony/console`, `symfony/dotenv`, `symfony/flex`, `symfony/framework-bundle`, `symfony/runtime`, `symfony/yaml`).

Versions réellement installées (vérifier à nouveau si ce fichier vieillit - ne jamais supposer qu'elles n'ont pas changé) : **PHP 8.4**, **Symfony 8.1**. Stack cible complète (`06-technical-architecture.md`, ADR-007) : Symfony (PHP), PostgreSQL, Redis, Docker, Nginx, API REST, Mistral.

Avant d'installer Doctrine, Security Bundle, Messenger, une bibliothèque JWT, ou le SDK Mistral : appliquer la règle de vérification Internet de `../CLAUDE.md` (section 3) - la version de Symfony installée ici (8.1) est récente, ne pas se fier à des exemples ou des configurations d'une version antérieure trouvés en mémoire. Aucune bibliothèque JWT n'est actée dans la documentation (`06-technical-architecture.md` acte le mécanisme JWT, pas l'implémentation précise) : le choix de la bibliothèque est une décision d'implémentation à vérifier au moment de l'installer, pas à deviner.

## 3. Architecture backend

Monolithe modulaire (ADR-001) - pas de microservices. Structure cible (`06-technical-architecture.md`, section 6), à créer sous `backend/src/` au fur et à mesure du besoin, pas en une seule fois :

```text
src/
├── Identity/        Authentification, compte utilisateur
├── Organization/    Entreprise (Organization, Address, FiscalContext)
├── Customer/        Clients associés aux factures
├── Invoicing/       Factures et lignes (analyse uniquement, jamais d'émission)
├── Compliance/
│   ├── Rules/       Regulatory Rules - référentiel, AUCUNE dépendance sortante
│   └── Engine/      Compliance Engine - orchestration, évaluation
├── Document/        Fichiers importés, traitement
├── AI/              AI Gateway (section 12)
├── Notification/    Rappels d'échéance
└── Shared/          Audit Trail, StorageInterface, exceptions communes, identifiants
```

Un module ne lit ni n'écrit jamais directement les données internes d'un autre module - toute interaction passe par une interface exposée, y compris entre `Compliance/Rules` et `Compliance/Engine` malgré leur namespace commun. `Compliance/Rules` n'appelle jamais un autre module (référentiel pur, section 7 de `06-technical-architecture.md`).

Flux de requête attendu pour un controller (rester mince) :

```text
Request → Validation → Authorization → Application service → Domain logic → Persistence/intégration externe → Response
```

Ne jamais mettre la logique métier complexe (sélection de règles, calcul d'agrégation de statut, décision d'état de facture) dans un controller - elle appartient à `Compliance/Engine` ou au service applicatif concerné, testable indépendamment de la couche HTTP.

## 4. Compliance Engine

Composant le plus critique du backend. Une règle de conformité n'est jamais créée à partir d'une supposition - voir `../CLAUDE.md` section 8 pour la procédure de vérification réglementaire, qui s'applique intégralement ici avant toute modification de règle.

Six états possibles pour un `ComplianceFinding` (`07-data-model.md` section 18, `05-user-stories.md` section 8), jamais un binaire : `CONFORME`, `NON_CONFORME`, `AVERTISSEMENT`, `NON_APPLICABLE`, `A_VERIFIER`, `INCERTAIN_REGLEMENTAIRE`. Règles absolues à faire respecter par l'évaluateur lui-même, pas par chaque règle individuelle :

- une donnée manquante → toujours `A_VERIFIER`, jamais `NON_CONFORME` par défaut (BR-COMPLIANCE-003) ;
- une règle dont `RuleVersion.confidence_level` n'est pas `Élevé` → `INCERTAIN_REGLEMENTAIRE`, jamais un verdict catégorique (BR-COMPLIANCE-004) ;
- un résultat de conformité n'est jamais affiché sans que la règle et sa source (`RuleVersion.source_reference`) soient également accessibles (BR-COMPLIANCE-002).

Immutabilité (ADR-003) : une `RuleVersion` n'est **jamais** modifiée après création (ni `UPDATE`, ni migration de donnée qui la toucherait). Toute évolution crée une nouvelle `RuleVersion` avec un nouveau `effective_from`, l'ancienne recevant un `effective_until`. Un `ComplianceFinding` référence toujours `rule_version_id` (la version précise), jamais `rule_id` seul. Le déterminisme est non négociable (ADR-002, `09-test-strategy.md` section 11) : même entrée + même contexte + même version de règle = même résultat à chaque exécution - toute variation sans changement de ces trois éléments est un bug critique bloquant, jamais un « flaky test » à ignorer.

Le moteur déterministe reste toujours prioritaire : il produit le résultat seul, sans jamais consulter l'IA pour déterminer une conformité (voir section 12).

Avant de modifier une règle : lire `02-regulatory-study.md`, vérifier la source officielle actuelle sur Internet, vérifier la date d'application exacte, créer une nouvelle `RuleVersion` plutôt que modifier l'existante, ajouter/mettre à jour les tests (Golden Test Cases en priorité, `09-test-strategy.md` section 14), mettre à jour `02-regulatory-study.md` si la vérification révèle un changement.

## 5. Documents

Les fichiers uploadés sont non fiables par défaut. Toujours valider, avant tout traitement : taille (**20 Mo maximum par fichier**, décision produit), type MIME, signature/magic bytes du fichier, format supporté (PDF, Factur-X, XML CII/UBL si réellement pris en charge par le Validator Container Mustang - jamais un type arbitraire), droits d'accès de l'appelant sur la ressource visée. Ne jamais faire confiance à l'extension de fichier seule.

`Document` (métadonnées, `storage_reference` opaque) est distinct de `DocumentProcessingRecord` (tentatives de traitement, statuts `UPLOADED → PROCESSING → PARSED → VALIDATED` ou `FAILED`) - ne pas fusionner ces deux responsabilités dans une seule entité ou un seul service (`07-data-model.md` section 13-14). Le contenu binaire n'est jamais stocké en base relationnelle.

Le stockage local du MVP doit être encapsulé derrière `StorageInterface` (`Shared/`) - aucun code métier ne doit connaître le chemin du système de fichiers directement ; `storage_reference` reste un identifiant opaque, jamais construit à partir d'une donnée utilisateur (protection path traversal, `10-security-privacy.md` section 22). Ne jamais exposer un fichier privé sans revalidation d'autorisation à chaque téléchargement (`GET /documents/{id}/content`), même si l'identifiant est déjà connu de l'appelant.

Le Validator Container (Mustang, Java) reste isolé du runtime PHP - appel par HTTP ou invocation de processus uniquement, jamais d'intégration directe d'une bibliothèque Java (ADR-008). Une indisponibilité de ce conteneur est une erreur technique, jamais un résultat de conformité.

## 6. API

`08-api-specification.md` est la source de vérité. Ne jamais inventer un endpoint ni modifier un contrat existant sans vérifier le document, les consommateurs frontend, les tests, la compatibilité.

Conventions déjà actées à respecter dans l'implémentation, sans les redécider : le tenant n'apparaît jamais dans l'URL (résolu depuis la session authentifiée, jamais depuis un paramètre de requête ou de payload) ; identifiant technique UUID dans le chemin, `invoice_number` jamais utilisé comme clé d'URL ; enveloppe `{ "data", "meta" }` / `{ "error": { "code", "message", "details", "request_id" } }`, ce dernier jamais utilisé pour un résultat `NON_CONFORME` ; `Idempotency-Key` honorée sur `POST /invoices/{id}/compliance-analyses` depuis la Phase 5 (TTL applicatif 24h, store PostgreSQL transactionnel `Shared/Idempotency/` - `UPSERT` atomique garantissant qu'une seule ComplianceAnalysis est créée sous requêtes concurrentes portant la même clé, vérifié par un test de concurrence réelle ; Redis n'est pas le mécanisme retenu ici, réservé à son besoin réel, le traitement asynchrone de la Phase 7, ADR-006) ; `POST /invoices` ne l'honore toujours pas (écart documenté depuis la Phase 4, décision D2, `08-api-specification.md` section 27) ; `POST /documents` n'existe pas encore (Phase 7) ; `If-Match`/`ETag` exigé sur `PATCH /invoices/{id}` (verrouillage optimiste, `409 Conflict` si absent ou périmé - à ne pas confondre avec la transition `ANALYZED → ANALYSIS_STALE`, qui n'est jamais un conflit, voir section 8).

Une ressource non trouvée **ou** appartenant à une autre organisation retourne toujours `404 Not Found`, jamais `403 Forbidden` (ne pas confirmer l'existence d'une ressource d'un autre tenant, `10-security-privacy.md` section 17) - ce comportement doit être uniforme sur tous les controllers, y compris les sous-ressources (`compliance-analyses/{id}/findings`, `documents/{id}/content`).

## 7. Database (Doctrine)

`07-data-model.md` est la source de vérité pour le schéma. Toute modification passe par une migration Doctrine - jamais de modification manuelle du schéma en remplacement d'une migration.

Entités principales à connaître avant toute modification touchant aux données : `User`, `Membership`, `Organization`, `Address`, `FiscalContext` (historisée), `Customer`, `Invoice`, `InvoiceLine`, `Document`, `DocumentProcessingRecord`, `RegulatoryRule`/`RuleVersion` (globales, non tenant-scoped), `ComplianceAnalysis`, `ComplianceFinding`, `ContextSnapshot`, `EligibilityDiagnostic`, `AuditLogEntry` (append-only), `Notification`, `IntegrationConfig`. `Subscription`/`Plan` ne sont pas implémentées au MVP - ne pas les créer sans qu'une tâche explicite le demande.

Contraintes à garantir au niveau base de données chaque fois que possible (pas seulement applicatif) : `organization_id` non nul sur toute entité tenant-scoped ; `(organization_id, invoice_number)` unique lorsque renseigné ; clés étrangères non nullables entre entités tenant-scoped ; cascades de suppression désactivées par défaut pour les entités jamais supprimées ; absence de chevauchement de périodes de validité pour `FiscalContext` et `RuleVersion`.

Suppression : `ComplianceAnalysis`, `ComplianceFinding`, `AuditLogEntry`, `RegulatoryRule`, `RuleVersion` ne sont **jamais** supprimées physiquement. `User`, `Customer`, `Invoice` utilisent un soft delete. `Document` a un régime mixte : suppression physique du fichier stocké et des données extraites sensibles devenues inutiles, suppression logique des métadonnées restantes - voir `07-data-model.md` section 30 avant d'implémenter `DELETE /documents/{id}`.

États à respecter strictement dans le code (pas de statut inventé) : `Invoice` suit `DRAFT → READY_FOR_ANALYSIS → ANALYZED ⇄ ANALYSIS_STALE` (jamais de retour vers `DRAFT`, jamais de nouvelle `Invoice` créée lors du passage à `ANALYSIS_STALE`) ; `DocumentProcessingRecord.status` suit `UPLOADED → PROCESSING → PARSED → VALIDATED` ou `FAILED` ; `ComplianceAnalysis.status` suit `PENDING → RUNNING → COMPLETED` ou `FAILED` - un `FAILED` ici est un échec technique du moteur, distinct d'un `global_result: NON_CONFORME` qui est une issue normale de `COMPLETED`.

## 8. Authentication

JWT conformément à `06-technical-architecture.md` (ADR-007) et `10-security-privacy.md` (section 12) : access token courte durée conservé en mémoire côté frontend (jamais `localStorage`), refresh token en cookie `HttpOnly`/`Secure`/`SameSite`, rotation systématique du refresh token à chaque utilisation, mécanisme de révocation côté Symfony (le backend reste l'autorité - un access token JWT signé n'est pas révocable une fois émis, seule l'invalidation du refresh token coupe l'accès à moyen terme). Vérification email obligatoire avant l'accès aux fonctionnalités sensibles (upload, analyses persistantes, IA), pas avant un usage basique du compte.

Avant toute implémentation ou modification touchant à l'authentification : consulter la documentation officielle actuelle de Symfony Security pour la version installée (8.1), vérifier quelle bibliothèque JWT est réellement adaptée aujourd'hui (aucune n'est encore choisie dans ce projet), vérifier les pratiques de stockage recommandées actuellement, vérifier les mécanismes de refresh/rotation/révocation documentés par cette bibliothèque, vérifier les recommandations de sécurité actuelles (mots de passe : algorithme de hachage à choisir parmi les standards reconnus au moment de l'implémentation, pas figé ici). Ne jamais supposer qu'une configuration JWT vue en mémoire est encore recommandée.

## 9. Authorization

Toujours côté backend, jamais déterminée côté client. Rôle unique au MVP (`OWNER`) - pas de RBAC complexe non justifié, mais centraliser la vérification d'autorisation dans une couche unique (pas dupliquée par endpoint) pour absorber un futur rôle sans réécrire chaque controller.

Chaque accès à une ressource tenant-scoped vérifie systématiquement, sans exception : authentification, appartenance active de l'utilisateur à l'organisation, et appartenance de la ressource ciblée à cette même organisation (`organization_id`). Un identifiant UUID difficile à deviner n'est jamais une autorisation en soi - la vérification `organization_id` est obligatoire même quand l'identifiant semble non devinable. Le filtrage par `organization_id` doit être centralisé dans la couche d'accès aux données (repository), jamais laissé à la discipline d'un `WHERE` ajouté manuellement dans chaque requête (ADR-004).

## 10. Asynchronous processing

Redis + Symfony Messenger pour les traitements asynchrones définis par l'architecture (extraction de document non triviale, analyse dépendant d'une extraction, reformulation IA, notifications) - traitement synchrone par défaut sinon (ADR-006), pas d'asynchrone systématique non justifié. Messenger n'est pas encore installé dans ce squelette : vérifier la documentation officielle Symfony Messenger actuelle avant de le configurer (transport Redis, retry, failure transport).

Chaque job/message doit rester idempotent (rejouable sans dupliquer son effet métier, notamment `RunComplianceAnalysis`), porter explicitement son `organization_id` d'origine (un job qui « oublierait » son tenant est une brèche potentielle, `10-security-privacy.md` section 16), gérer un nombre de retries limité avec un échec technique explicite plutôt qu'un abandon silencieux, et rester visible en dead-letter s'il échoue définitivement. Redis n'est jamais une source de vérité métier - PostgreSQL reste seul responsable de la persistance durable.

## 11. IA (Mistral)

Ne jamais disperser d'appel Mistral direct dans un controller ou dans le domaine métier. Passer systématiquement par l'abstraction :

```text
Application → AI Gateway (AI/) → AIProviderInterface → Mistral adapter → API Mistral
```

L'AI Gateway ne reçoit et ne transmet jamais l'intégralité d'une facture, d'une fiche entreprise ou d'un client - uniquement le contexte minimisé d'un `ComplianceFinding` précis (règle, source, résultat déjà déterminé). L'IA n'a, par construction, aucun canal d'écriture (jamais de modification d'`Invoice`, `ComplianceFinding`, `RuleVersion`, permission ou donnée de compte) et aucun accès direct à la base de données. Un échec ou timeout Mistral ne doit jamais bloquer l'affichage du résultat déterministe déjà produit - repli systématique vers `RuleVersion.explanation_template` non reformulé, jamais une absence de réponse.

Avant toute modification de l'intégration Mistral : consulter la documentation officielle Mistral actuelle, vérifier les modèles disponibles, les endpoints d'API, les limites de taux, les formats de sortie structurée, les recommandations de sécurité, les coûts. Fournisseur français (Mistral) mais la localisation effective de traitement, l'usage des données à des fins d'entraînement et l'existence d'un DPA restent à vérifier contractuellement avant mise en production (`10-security-privacy.md` section 30) - ne jamais présenter ces points comme déjà réglés du seul fait que Mistral est une société française.

L'IA ne devient jamais l'autorité réglementaire (ADR-002) : elle reformule un résultat déjà produit par le Compliance Engine, elle ne le produit jamais.

## 12. Security

Suivre `10-security-privacy.md`. Points d'attention spécifiques au code backend, au-delà des rappels déjà faits dans les sections précédentes :

- **IDOR/BOLA** : voir section 9 - vérification systématique authentification + appartenance organisation + appartenance ressource, sur chaque endpoint sans exception.
- **Injection SQL** : requêtes paramétrées systématiques via Doctrine, jamais de concaténation de chaîne pour construire une requête à partir d'une entrée utilisateur.
- **XXE / injection XML** : risque élevé et spécifique à ce backend, qui traite des formats XML (UBL, CII, Factur-X) - le parsing XML doit désactiver la résolution d'entités externes par défaut, quelle que soit la bibliothèque utilisée (à vérifier dans sa documentation actuelle avant usage) ; ne jamais faire confiance à un fichier XML importé comme source fiable.
- **CSRF** : surface résiduelle limitée à `POST /auth/refresh` (seul endpoint où un cookie est transmis automatiquement par le navigateur) - `SameSite` sur le cookie de refresh combiné à une vérification `Origin`/`Referer`. Le reste de l'API, authentifié par en-tête `Authorization`, n'a pas cette exposition.
- **CORS** : origine limitée explicitement au(x) domaine(s) du frontend officiel par environnement, jamais de wildcard, en particulier si des credentials sont autorisés.
- **Secrets** : identifiants base de données, clé de signature JWT, clé Mistral, futurs identifiants email/stockage - jamais en dur dans le code ou une config versionnée, injectés par variable d'environnement ou mécanisme dédié, jamais loggés en clair.
- **Erreurs** : `error.message` ne doit jamais exposer une trace technique, une requête SQL ou un chemin de fichier serveur.
- **Multi-tenant** : voir section 9 - s'applique aussi aux jobs asynchrones (section 10), au cache si introduit, et aux logs.

## 13. Tests

Suivre `09-test-strategy.md`. Ce backend est testé comme un système de conformité, pas seulement comme une API CRUD : une règle mal appliquée est une défaillance critique, au même titre qu'une fuite cross-tenant.

Toute nouvelle règle de conformité doit être testée sous ses variantes : condition respectée (`CONFORME`), condition non respectée (`NON_CONFORME`, avec message et action de correction non vides), non applicable au contexte (`NON_APPLICABLE`), donnée manquante (`A_VERIFIER`), confiance non élevée (`INCERTAIN_REGLEMENTAIRE` si pertinent), cas limite propre à la règle. Le Compliance Engine reçoit une densité de tests unitaires proportionnellement plus élevée que le reste du backend - un scénario par cas réglementaire, pas un test générique.

Tester également : le déterminisme (même entrée + même contexte + même version = même résultat, à exécuter en CI pour toute modification du Compliance Engine) ; qu'une analyse historique ne change jamais de résultat lorsqu'une nouvelle `RuleVersion` devient active ; la suite de régression complète à chaque publication d'une nouvelle `RuleVersion` (logique event-driven, pas un rythme fixe) ; l'isolation entre organisations ; les endpoints API (statuts HTTP, validation, authentification, autorisation, erreurs) ; les jobs asynchrones (idempotence, retry, dead-letter) ; les intégrations externes en les mockant par défaut.

Une correction de bug sur le Compliance Engine doit être ajoutée au jeu de Golden Test Cases (`09-test-strategy.md` section 14), pas seulement corrigée ponctuellement.

## 14. Code quality (PHP/Symfony)

Conventions Symfony et PHP actuelles pour la version réellement installée (section 2) - ne pas reproduire un pattern Symfony obsolète trouvé en mémoire ou dans un ancien tutoriel. Avant d'utiliser une fonctionnalité Symfony ou Doctrine non déjà présente dans le code existant : vérifier la version installée, consulter la documentation officielle actuelle, vérifier les API recommandées et les API dépréciées pour cette version précise.

Pour tout le reste (lisibilité, fonctions courtes, pas de logique métier dans les controllers, pas d'abstraction prématurée, pas de dépendance inutile, discipline de commentaires, formatage, Git) : voir `../CLAUDE.md`, sections 16 à 20, sans règle supplémentaire spécifique au backend au-delà de ce qui est déjà couvert dans ce fichier.

## 15. Workflow backend

Avant une modification backend importante, en complément du workflow général de `../CLAUDE.md` (section 25) :

1. Lire la documentation concernée (section 1 de ce fichier).
2. Inspecter le code existant sous `backend/src/` (pas seulement la documentation - le squelette actuel peut être en avance ou en retard sur ce qui est décrit ici).
3. Inspecter les tests existants sous `backend/tests/`.
4. Vérifier sur Internet les API Symfony/PHP/Doctrine/Messenger concernées, pour la version réellement installée.
5. Vérifier les implications réglementaires (`02-regulatory-study.md`) si la tâche touche au Compliance Engine.
6. Vérifier les implications sécurité (section 12) et multi-tenant (section 9) si la tâche touche à une ressource ou un accès aux données.
7. Implémenter le minimum nécessaire, dans le module concerné, sans casser les frontières de la section 3.
8. Ajouter les tests (section 13).
9. Exécuter formatter/analyse statique/tests.
10. Vérifier le diff et les migrations générées.
11. Mettre à jour `../docs/` si la tâche a fait évoluer une décision qui y était documentée.

Règle finale, reprise de `../CLAUDE.md` : **« Do not guess when the answer can be verified. »**
