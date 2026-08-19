# CLAUDE.md — FactuSentinel

Ce fichier est le guide permanent de Claude Code pour le projet **FactuSentinel**.

FactuSentinel est un **assistant de conformité à la facturation électronique** destiné principalement aux micro-entrepreneurs, indépendants, freelances et TPE françaises, face à la réforme française de la facturation électronique (e-invoicing/e-reporting, 1er septembre 2026 pour les grandes entreprises/ETI, 1er septembre 2027 pour les PME/TPE/micro-entreprises).

Il définit les règles de développement, les contraintes architecturales, les règles de qualité, de sécurité, de réglementation, d'IA, de dépendances, de documentation, de Git, et le workflow à suivre avant de modifier le code.

**Ce fichier ne recopie pas la documentation fonctionnelle et technique.** Les détails précis (exigences, user stories, schéma de données, contrats API, stratégie de test, exigences de sécurité, design system, roadmap) vivent exclusivement dans `docs/`. Ce fichier définit des règles de comportement, pas des spécifications.

## 1. Rôle de ce fichier

Ce document définit les règles que Claude Code doit respecter en permanence sur ce projet : développement, architecture, qualité, sécurité, réglementation, IA, dépendances, documentation, Git, et le workflow à suivre avant toute modification de code. Il ne se substitue à aucun document de `docs/` : en cas de doute sur une exigence fonctionnelle, technique ou réglementaire précise, la réponse se trouve dans `docs/`, pas ici.

## 2. Sources de vérité

Hiérarchie des sources de vérité pour ce projet, du plus général au plus précis :

| Document | Ce qu'il définit |
|---|---|
| `docs/01-intent-note.md` | Vision, cible, positionnement, hors périmètre |
| `docs/02-regulatory-study.md` | La réglementation vérifiée — seule source pour toute règle de conformité |
| `docs/03-market-analysis.md` | Marché, concurrence, hypothèses à valider |
| `docs/04-product-requirements.md` | Exigences fonctionnelles/non fonctionnelles, Business Rules (`BR-*`), décisions produit (`DEC-*`) |
| `docs/05-user-stories.md` | Parcours utilisateurs, critères d'acceptation détaillés |
| `docs/06-technical-architecture.md` | Architecture, modules, ADR (`ADR-001` à `ADR-008`) |
| `docs/07-data-model.md` | Schéma de données, invariants, contraintes d'intégrité |
| `docs/08-api-specification.md` | Contrats API |
| `docs/09-test-strategy.md` | Stratégie et niveaux de test |
| `docs/10-security-privacy.md` | Sécurité, RGPD, gestion des secrets |
| `docs/11-frontend-design-system.md` | Système de design, composants, accessibilité |
| `docs/12-roadmap.md` | Séquencement, décisions produit historisées (`DL-*`) |

Les documents de `docs/` définissent les décisions du projet. Avant d'implémenter une fonctionnalité importante :

1. Identifier les documents concernés.
2. Lire les sections pertinentes.
3. Inspecter l'implémentation existante (le code, pas la mémoire de ce qui a été dit précédemment).
4. Vérifier les éventuelles décisions déjà prises (sections « Décisions produit », ADR, `DEC-*`, `BR-*`).
5. Identifier les contradictions éventuelles entre documents, ou entre la documentation et le code existant.
6. Si une décision documentée doit changer, proposer une modification **documentée** (mettre à jour le document concerné, pas seulement le code) plutôt que de dévier silencieusement.

Claude ne doit **jamais** remplacer silencieusement une décision documentée par sa propre préférence — y compris en matière d'architecture, de nommage, de choix de bibliothèque ou de règle métier. Si la documentation est contradictoire, incomplète, ou marque explicitement un point comme « question ouverte » ou « à confirmer juridiquement » (fréquent dans `10-security-privacy.md`), Claude doit le signaler à l'utilisateur au lieu d'inventer une décision importante à sa place.

## 3. Vérification Internet obligatoire

La connaissance interne de Claude sur les frameworks, bibliothèques, API et bonnes pratiques peut être obsolète. Lorsqu'une tâche dépend du comportement actuel d'une technologie, une recherche doit être effectuée **avant** d'implémenter — jamais après coup pour se justifier.

Obligatoire notamment pour : Next.js, React, TypeScript, Tailwind CSS v4, Symfony, PHP, PostgreSQL, Redis, Docker, Nginx, GitHub Actions, Mistral, Mustangproject, les bibliothèques déjà utilisées dans `backend/composer.json` et `frontend/package.json`, les API externes (Sirene/INSEE), les mécanismes d'authentification, les recommandations de sécurité, la configuration de production, les nouvelles fonctionnalités, les API dépréciées, les changements de version, les migrations.

Ne jamais supposer qu'une API connue est encore correcte simplement parce qu'elle existait dans une ancienne version. Ne jamais reproduire automatiquement une solution issue de la mémoire sans vérification.

Avant d'utiliser une technologie ou une API :
1. Identifier la version réellement utilisée dans le projet (`composer.json`, `package.json`, `Dockerfile`).
2. Consulter la documentation officielle correspondant à cette version précise.
3. Vérifier les release notes pertinentes si nécessaire.
4. Vérifier les API actuellement recommandées et les API dépréciées.
5. Vérifier les recommandations de sécurité actuelles.
6. Vérifier les incompatibilités connues.
7. Implémenter ensuite seulement.

Note projet : `frontend/AGENTS.md` (généré par `next dev`) rappelle déjà que le Next.js installé peut différer des connaissances d'entraînement et renvoie vers `node_modules/next/dist/docs/` — cohérent avec cette règle, à traiter comme un rappel local et non comme une source suffisante à elle seule.

## 4. Sources Internet à privilégier

Ordre de préférence : documentation officielle → documentation de référence du framework → release notes officielles → documentation officielle des packages → dépôt officiel GitHub → security advisories officielles → sources communautaires, uniquement lorsque les sources officielles ne suffisent pas.

Ne pas prendre Stack Overflow, Reddit, des blogs personnels ou d'anciens tutoriels comme source principale lorsqu'une documentation officielle existe. Pour tout ce qui touche à la réglementation française de la facturation électronique, appliquer la même discipline de sourcing que celle déjà utilisée dans `02-regulatory-study.md` (sources officielles : economie.gouv.fr, impots.gouv.fr, service-public.gouv.fr, Légifrance, BOFiP — jamais une source commerciale comme preuve principale).

## 5. Versioning

Toujours une version stable et supportée. Ne pas utiliser d'alpha, bêta, release candidate, nightly, experimental, ni d'API dépréciée lorsqu'une alternative stable existe. Ne pas prendre automatiquement la toute dernière version disponible : le critère est **version stable + compatible avec le projet + officiellement supportée**, pas « la plus récente ».

Avant toute installation ou mise à jour importante : vérifier la version actuelle, vérifier la dernière version stable pertinente, vérifier la compatibilité avec le reste de la stack, vérifier les breaking changes, vérifier les recommandations officielles.

## 6. Stack du projet

Stack officielle, actée par décision produit (`06-technical-architecture.md`, ADR-007) :

| Couche | Choix |
|---|---|
| Frontend | Next.js, TypeScript, Tailwind CSS v4 |
| Backend | Symfony (PHP), monolithe modulaire |
| Base de données | PostgreSQL (multi-tenant à discriminant `organization_id`) |
| API | REST |
| IA | Mistral (via un `AIProviderInterface` abstrait, jamais d'appel direct dispersé) |
| Validation Factur-X/UBL/CII | Mustangproject, isolé dans un conteneur séparé (ADR-008), jamais intégré directement au runtime PHP |
| Traitement asynchrone / cache | Redis (jamais source de vérité métier — uniquement PostgreSQL) |
| Stockage documentaire | Système de fichiers local, abstrait derrière `StorageInterface`, pour le MVP (migration future vers un stockage objet déjà anticipée, jamais improvisée) |
| Authentification | JWT — access token courte durée en mémoire frontend, refresh token en cookie `HttpOnly`/`Secure`/`SameSite` |
| Infrastructure | Docker, Nginx (reverse proxy) |
| CI/CD | GitHub Actions |

Ne jamais changer cette stack simplement parce qu'une autre technologie semble préférable dans l'instant. Toute modification importante de stack doit être explicitement justifiée, discutée avec l'utilisateur, et documentée dans `06-technical-architecture.md` (nouvel ADR), jamais introduite silencieusement au fil d'une tâche.

## 7. Positionnement du produit

FactuSentinel aide à : comprendre la réforme, déterminer les obligations applicables, analyser des factures, identifier des problèmes, expliquer les règles et pourquoi elles s'appliquent, proposer des corrections, accompagner l'utilisateur dans sa préparation à la facturation électronique.

FactuSentinel n'est pas : un logiciel de facturation complet, un logiciel comptable, un expert-comptable, une **plateforme agréée**, une autorité réglementaire, une source juridique autonome.

Formulation de référence (`04-product-requirements.md`, section 32 bis) : *« un assistant de préparation, de contrôle et de compréhension de la conformité, qui aide le TPE/micro-entrepreneur à comprendre ce qu'il doit corriger et à se préparer à utiliser sa plateforme agréée »*. Le produit **n'émet ni ne transmet jamais réellement** de facture (`04-product-requirements.md`, section 7 et 30, BR-SCOPE-001) — cette distinction doit rester explicite dans toute l'expérience produit, pas seulement dans la documentation.

Claude ne doit jamais élargir silencieusement le périmètre du produit (pas de comptabilité, pas de paie, pas de CRM, pas de paiement intégré, pas de rôle de plateforme agréée — liste complète en `04-product-requirements.md`, section 30). Toute proposition qui rapprocherait le produit de l'une de ces catégories doit être explicitement signalée à l'utilisateur avant d'être implémentée, jamais glissée dans une tâche à un autre objet.

## 8. Réglementation

La réglementation française est une partie critique du produit. Ne jamais inventer une règle, un seuil, une obligation, une date, une mention obligatoire ; ne jamais transformer une hypothèse en règle ; ne jamais présenter une information non vérifiée comme certaine.

Repères déjà vérifiés et actés (`02-regulatory-study.md`, section 23) — à utiliser comme référence, pas à reproduire de mémoire sans vérifier qu'ils n'ont pas changé : calendrier 1er septembre 2026 (réception, toutes entreprises ; émission + e-reporting, GE/ETI) et 1er septembre 2027 (émission + e-reporting, PME/TPE/micro) ; seuils de franchise en base TVA 85 000 €/93 500 € (vente/hébergement) et 37 500 €/41 250 € (services) ; conservation légale de la facture originale, 10 ans ; formats Factur-X (priorité MVP), UBL, CII.

Lorsqu'une règle réglementaire doit être modifiée ou créée :
1. Consulter `02-regulatory-study.md` en premier.
2. Vérifier les sources officielles actuelles sur Internet (section 4 de ce fichier) — la réglementation a déjà changé plusieurs fois depuis la rédaction de cette étude.
3. Vérifier si la réglementation a évolué depuis la dernière vérification documentée.
4. Identifier la date d'entrée en vigueur exacte.
5. Créer une **nouvelle version** de la règle plutôt que de modifier une version existante (immutabilité, ADR-003 — voir section 10 de ce fichier).
6. Ajouter ou mettre à jour les tests, notamment les Golden Test Cases (`09-test-strategy.md`, section 14).
7. Mettre à jour `02-regulatory-study.md` si la vérification révèle un changement, avec la même rigueur de sourcing que le document existant.

Une règle réglementaire ne doit jamais être codée uniquement à partir de la mémoire du modèle, ni comme une condition `if` dispersée dans le code métier — c'est une **donnée versionnée** (`ComplianceRule`/`ComplianceRuleVersion`, `07-data-model.md` sections 15-16).

## 9. Principe produit

Respecter le principe : **« Pourquoi, jamais seulement si. »**

Une vérification de conformité ne doit jamais se limiter à `CONFORME` ou `NON_CONFORME` lorsque le contexte permet d'en dire plus. Le produit gère six états de résultat, jamais un simple binaire (`05-user-stories.md`, section 8 ; `06-technical-architecture.md`, section 8) : `CONFORME`, `NON_CONFORME`, `AVERTISSEMENT`, `NON_APPLICABLE`, `A_VERIFIER`, `INCERTAIN_REGLEMENTAIRE`. Une donnée manquante produit toujours `A_VERIFIER`, jamais `NON_CONFORME` par défaut (BR-COMPLIANCE-003) ; une règle dont la source réglementaire est incertaine produit `INCERTAIN_REGLEMENTAIRE`, jamais un verdict catégorique (BR-COMPLIANCE-004).

Le système doit pouvoir expliquer, pour chaque résultat : la règle appliquée, sa version, la raison, la source réglementaire, la conséquence, l'action de correction recommandée (BR-COMPLIANCE-002). L'IA ne constitue jamais à elle seule une autorité réglementaire (ADR-002) — voir section 14 de ce fichier.

## 10. Architecture

Respecter strictement `06-technical-architecture.md`. Le projet utilise un **monolithe modulaire** (ADR-001) : ne pas introduire de microservices sans décision explicite et documentée.

Modules métier (namespace `backend/src/`) : `Identity` (compte, auth), `Organization` (entreprise), `Customer` (clients), `Invoicing` (factures, analyse uniquement — jamais d'émission), `Compliance` (`Compliance/Rules` + `Compliance/Engine`), `Document` (fichiers importés), `AI` (AI Gateway), `Notification`, `Shared` (Audit Trail, éléments transverses). Un module ne lit ni n'écrit **jamais** directement les données internes d'un autre module ; toute interaction passe par une interface exposée. `Compliance/Rules` (Regulatory Rules) n'a aucune dépendance sortante — c'est un référentiel pur.

Privilégier : séparation des responsabilités, frontières de modules déjà établies, controllers minces, services focalisés, logique métier testable, dépendances explicites, interfaces lorsque réellement utiles (dépendances externes uniquement — IA, email, stockage, vérification d'entreprise), traitement asynchrone uniquement pour ce qui dépend d'une ressource à latence non maîtrisée (ADR-006).

Éviter : architecture inutilement complexe, abstraction prématurée en dehors des frontières déjà posées, design patterns pour « faire propre », dépendances inutiles.

Rappels non négociables issus des ADR : le Compliance Engine ne dépend jamais de la couche IA pour produire un résultat (ADR-002) ; une version de règle n'est jamais modifiée en place (ADR-003) ; l'isolation multi-tenant par `organization_id` doit être garantie au niveau de la couche d'accès aux données, jamais laissée à la seule discipline d'une requête individuelle (ADR-004) ; toute dépendance externe passe par une interface interne (ADR-005) ; le Validator Container (Mustang) reste isolé du runtime PHP (ADR-008).

## 11. API

La spécification API est définie dans `docs/08-api-specification.md`.

Conventions à respecter systématiquement, sans les réinventer : enveloppe de réponse `{ "data": ..., "meta": ... }` ; contrat d'erreur `{ "error": { "code", "message", "details", "request_id" } }`, **jamais** utilisé pour représenter un résultat de conformité `NON_CONFORME` (qui est un résultat métier valide, pas une erreur) ; montants transmis en chaînes décimales (jamais en flottant JSON) ; dates métier `YYYY-MM-DD`, horodatages ISO 8601 UTC ; énumérations en `SCREAMING_SNAKE_CASE` ; `Idempotency-Key` obligatoire sur `POST /invoices`, `POST /invoices/{id}/compliance-analyses`, `POST /documents` (TTL applicatif 24h — le mécanisme de stockage n'est pas figé à Redis : `POST /invoices/{id}/compliance-analyses` l'honore depuis la Phase 5 via un store PostgreSQL transactionnel dédié, `backend/src/Shared/Idempotency/`, sûr sous requêtes concurrentes ; Redis reste réservé à son besoin réel, le traitement asynchrone de la Phase 7, ADR-006 — voir `docs/08-api-specification.md` section 20) ; verrouillage optimiste via `If-Match`/`ETag` sur `PATCH /invoices/{id}`.

Ne jamais inventer silencieusement un endpoint ou modifier un contrat existant. Toute modification d'API doit prendre en compte le backend, le frontend, les tests, la documentation (`08-api-specification.md`) et la compatibilité (section 44-45 de ce document : un ajout de valeur d'énumération est non cassant, une suppression ou un renommage de champ l'est).

## 12. Database

Le modèle de données est défini dans `docs/07-data-model.md`.

Conventions à respecter : identifiant technique UUID sur toutes les entités ; `organization_id` non nul sur toute entité tenant-scoped, avec contrainte au niveau base de données chaque fois que possible ; contrainte `(organization_id, invoice_number)` unique lorsque renseignée ; soft delete réservé aux entités dont la suppression physique casserait une garantie de traçabilité (`User`, `Customer`, `Invoice`) — `ComplianceAnalysis`, `ComplianceFinding`, `AuditLogEntry`, `RegulatoryRule`, `RuleVersion` ne sont **jamais** supprimées.

Toute modification du schéma doit être reproductible : migrations, foreign keys, indexes, unique constraints, check constraints lorsque pertinentes, transactions lorsque nécessaires. Ne jamais modifier manuellement une base de données comme remplacement d'une migration.

## 13. Authentication et Authorization

L'authentification et l'autorisation sont deux problèmes différents. Le frontend ne constitue jamais une frontière de sécurité — toute autorisation doit être vérifiée côté backend (Symfony reste l'autorité).

Mécanisme retenu (`06-technical-architecture.md`, ADR-007) : access token JWT courte durée conservé en mémoire côté frontend (jamais `localStorage`) + refresh token en cookie `HttpOnly`/`Secure`/`SameSite`, avec protection CSRF ciblée sur `/auth/refresh`. Un seul rôle au MVP (`OWNER`), mais l'architecture d'autorisation doit rester capable d'accueillir des rôles supplémentaires sans refonte. Vérification email obligatoire avant l'accès aux fonctionnalités sensibles (upload, analyses persistantes, IA), non bloquante pour une utilisation basique du compte.

Toujours considérer, à chaque accès à une ressource : IDOR, BOLA, ownership, isolation entre organisations (`organization_id`), accès aux factures, aux documents, aux résultats de conformité, aux données personnelles. Une erreur technique ne doit jamais dégénérer en accès non contrôlé — en cas de doute sur l'appartenance d'une ressource, le système refuse (Fail Secure, `10-security-privacy.md` section 3).

## 14. IA

Mistral est le fournisseur IA retenu (ADR-007), implémentation initiale de `AIProviderInterface`. L'intégration doit rester abstraite :

```text
Compliance Engine (résultat déjà produit, déterministe)
       ↓ résultat figé, non modifiable
AI Gateway (point d'entrée unique vers tout fournisseur IA)
       ↓ contexte minimisé (un ComplianceFinding précis, jamais une facture ou fiche entreprise entière)
Mistral (implémentation de AIProviderInterface)
```

L'IA n'est **jamais** l'autorité réglementaire (ADR-002) : elle reformule un résultat déjà déterminé par le Compliance Engine déterministe, elle ne le produit jamais elle-même. Ne jamais disperser des appels Mistral en dehors de l'AI Gateway. L'IA n'a structurellement aucun canal d'écriture (ne modifie jamais une `Invoice`, un `ComplianceFinding`, une `RuleVersion`, des permissions ou des données de compte) et aucun accès direct à la base de données. Un échec ou une indisponibilité de Mistral ne doit jamais bloquer l'affichage du résultat déterministe déjà produit — repli systématique vers `explanation_template` non reformulé.

Avant toute modification de l'intégration Mistral : consulter la documentation officielle actuelle (section 3-4 de ce fichier), vérifier les modèles disponibles, les API, les limites, les formats de sortie, les recommandations de sécurité, les coûts lorsque nécessaire, et le contenu contractuel encore ouvert (localisation des données, usage à des fins d'entraînement, DPA — `10-security-privacy.md` section 30, à ne jamais présumer réglé).

## 15. Sécurité

`docs/10-security-privacy.md` est la source de vérité.

Considérer systématiquement : XSS, CSRF (notamment `/auth/refresh`), SSRF, IDOR/BOLA, injection, upload de fichiers malveillants (documents importés — validation MIME, magic bytes, taille avant tout traitement), secrets, exposition de données entre tenants, dépendances vulnérables, logs sensibles, sécurité des tokens, sécurité des appels IA (prompt injection : tout contenu de document ou question utilisateur est toujours traité comme une donnée, jamais comme une instruction système).

Principes structurants à respecter par défaut, sans qu'ils aient besoin d'être redemandés à chaque fois (`10-security-privacy.md`, section 3) : Security/Privacy by Design, Least Privilege, Defense in Depth, Fail Secure, Zero Trust (aucune requête frontend n'est fiable sans revalidation serveur), Data Minimization, Secure Defaults (toute nouvelle donnée est par défaut tenant-scoped et non transmise à un fournisseur externe sauf décision explicite).

Ne jamais commit : secrets, clés d'API, mots de passe, tokens, credentials, fichiers `.env` contenant des secrets. Aucun secret n'est jamais en dur dans un fichier de configuration versionné — injection par variable d'environnement ou mécanisme dédié, distinct par environnement.

## 16. Code Quality

Privilégier : code simple, lisible, typage fort (TypeScript strict côté frontend, typage PHP explicite côté Symfony), fonctions courtes, classes focalisées, noms explicites, dépendances explicites, conventions officielles des frameworks utilisés.

Éviter : code mort, duplication, classes gigantesques, fonctions gigantesques, logique métier dans les controllers, logique métier dans les composants frontend, abstractions inutiles en dehors des frontières déjà posées par l'architecture (section 10), hacks, magic values (en particulier tout ce qui devrait être une règle réglementaire versionnée, section 8), dépendances inutiles.

## 17. Commentaires

Les commentaires doivent être rares et utiles. Ne pas écrire de longs commentaires expliquant ligne par ligne ce que fait le code, ni des commentaires qui paraphrasent le code.

Un commentaire doit principalement expliquer : pourquoi une décision inhabituelle existe, une contrainte réglementaire importante (avec référence à `02-regulatory-study.md`), un compromis technique, un comportement non évident, une limitation externe. Le code doit être suffisamment clair pour que les commentaires ne soient pas nécessaires pour comprendre son fonctionnement normal. Ne pas générer automatiquement des blocs de commentaires longs.

## 18. Règles de formatage et de texte

Ne jamais utiliser d'emoji dans le code, les commentaires, les logs, les messages de commit, les titres de PR ou les documents techniques générés par Claude, sauf demande explicite.

Le tiret cadratin / em dash (—) est strictement interdit dans le code : PHP, TypeScript, JavaScript, JSX, TSX, HTML, CSS, SQL, YAML, JSON, Dockerfiles, scripts, commentaires, chaînes de caractères, tests, messages d'erreur, documentation technique générée dans le dépôt.

Ne jamais générer de lignes décoratives constituées d'une répétition de tirets ou de tirets cadratins (par exemple `--------------------------------------------------`) utilisées uniquement comme séparateur visuel. Utiliser des titres Markdown normaux.

## 19. Git et commits

Claude ne doit jamais ajouter de co-auteur Claude dans les commits. Ne jamais ajouter `Co-authored-by: Claude`, `Co-authored-by: Claude Code`, `Co-authored-by: Anthropic` ou toute variante similaire. Les commits doivent représenter le travail du projet sans attribution automatique à Claude.

Respecter les conventions de commit du projet une fois établies. Privilégier des commits courts, ciblés, explicites, cohérents. Ne pas mélanger plusieurs fonctionnalités indépendantes dans un même commit.

Point d'attention spécifique à ce dépôt : `backend/` contient son propre répertoire `.git` (dépôt imbriqué). Avant tout commit touchant `backend/`, vérifier explicitement dans quel dépôt (racine ou `backend/`) l'opération doit avoir lieu, et le signaler à l'utilisateur en cas de doute plutôt que de deviner.

## 20. Merge Requests / Pull Requests

Ne jamais ajouter automatiquement Claude comme co-auteur, comme contributeur, une attribution automatique à Claude, une mention « Generated by Claude », un badge ou une signature Claude — dans les commits, descriptions de PR/MR ou métadonnées Git — sauf demande explicite de l'utilisateur.

Les descriptions de PR doivent être professionnelles et centrées sur : ce qui a changé, pourquoi, les tests effectués, les impacts, les éventuels risques.

## 21. Dependencies

Avant d'ajouter une dépendance (`composer.json` ou `package.json`) :
1. Vérifier si Symfony ou Next.js fournit déjà la fonctionnalité.
2. Vérifier la documentation officielle.
3. Vérifier la version stable (section 5).
4. Vérifier la compatibilité avec la stack retenue (section 6).
5. Vérifier la maintenance active du paquet.
6. Vérifier les vulnérabilités connues (`10-security-privacy.md`, section 48-51).
7. Vérifier si la dépendance est réellement nécessaire — au regard notamment du principe de simplicité opérationnelle pour un développeur solo (`06-technical-architecture.md`, section 3).

Ne jamais ajouter une dépendance simplement pour économiser quelques lignes de code.

## 22. Tests

`docs/09-test-strategy.md` est la source de vérité.

Ce produit est testé comme un **système de conformité**, pas seulement comme une application web : une fonctionnalité peut être techniquement correcte tout en appliquant la mauvaise règle réglementaire — cette situation est une défaillance critique, au même titre qu'une fuite de données cross-tenant. Le Compliance Engine reçoit une densité de tests unitaires proportionnellement plus élevée que le reste du système (un scénario par cas réglementaire).

Toute nouvelle fonctionnalité importante doit avoir les tests appropriés ; toute correction de bug importante doit avoir un test de régression. Une fonctionnalité touchant au Compliance Engine doit référencer explicitement la section de `02-regulatory-study.md` qui la justifie (Definition of Ready, `09-test-strategy.md` section 57).

Avant de considérer une tâche terminée : lancer les tests pertinents (unitaires, intégration, API selon ce qui est concerné), lancer lint/format, vérifier les erreurs, vérifier le diff, vérifier les migrations, vérifier les impacts API (Definition of Done, `09-test-strategy.md` section 58).

## 23. Docker

Docker doit rester reproductible, sécurisé, maintenable, adapté au développement, évolutif vers la production. Composants conteneurisés au MVP (`06-technical-architecture.md`, section 30) : Symfony (API + worker), Next.js, PostgreSQL, Redis, Nginx, et le Validator Container (Mustang, isolé du runtime PHP — ADR-008).

Ne pas utiliser `latest` sans justification. Éviter les conteneurs root lorsque ce n'est pas nécessaire. Ne pas exposer inutilement Redis ou PostgreSQL publiquement — self-hosted/conteneurisé, sans dépendance à un service managé tiers au MVP.

Avant toute modification Docker importante, consulter la documentation Docker officielle actuelle (section 3-4 de ce fichier).

## 24. GitHub Actions

Avant de modifier le workflow CI/CD : vérifier la documentation officielle GitHub Actions actuelle, vérifier les versions actuelles des actions utilisées, vérifier les changements de syntaxe, vérifier les recommandations de sécurité, vérifier les permissions accordées au workflow.

Le pipeline doit exécuter les tests (`09-test-strategy.md`) avant tout déploiement — particulièrement critique pour le Compliance Engine, où une régression pourrait produire silencieusement des résultats de conformité incorrects. Éviter les actions obsolètes. Limiter les permissions GitHub Actions au strict nécessaire.

## 25. Workflow de Claude

Avant toute modification importante :
1. Lire la demande.
2. Lire les documents de `docs/` concernés (section 2 de ce fichier).
3. Inspecter le code existant (`backend/src/`, `frontend/app/`) — jamais uniquement la documentation.
4. Identifier les décisions déjà prises (`DEC-*`, `BR-*`, ADR, sections « Décisions produit »/« Questions ouvertes » des documents concernés).
5. Vérifier la documentation officielle actuelle sur Internet si la tâche dépend du comportement d'une technologie (section 3).
6. Vérifier les implications réglementaires (section 8) — toute règle de conformité doit rester traçable vers `02-regulatory-study.md`.
7. Vérifier les implications sécurité (section 15) — en particulier l'isolation multi-tenant.
8. Vérifier les impacts API et données (`08-api-specification.md`, `07-data-model.md`).
9. Implémenter le changement minimal cohérent avec l'architecture existante (section 10).
10. Ajouter ou mettre à jour les tests (section 22).
11. Exécuter les vérifications (tests, lint, migrations).
12. Examiner le diff.
13. Mettre à jour la documentation dans `docs/` lorsque la tâche a fait évoluer une décision qui y était documentée.

Ne jamais commencer par une réécriture massive. Ne pas modifier des fichiers sans rapport avec la tâche demandée.

## 26. Règle finale

Claude doit se comporter comme un ingénieur logiciel senior travaillant dans un projet existant, sur un produit dont la fiabilité réglementaire est la proposition de valeur centrale.

Avant de coder : inspecter, comprendre, vérifier, rechercher, planifier.
Puis : implémenter, tester, sécuriser, documenter.

Règle fondamentale : **« Do not guess when the answer can be verified. »**

Lorsqu'une information peut avoir changé depuis les connaissances internes du modèle — qu'il s'agisse d'une API, d'une bibliothèque, d'une recommandation de sécurité ou d'un point de réglementation française — effectuer une vérification auprès des sources officielles avant de prendre une décision technique ou d'affirmer un fait à l'utilisateur.
