# Security & Privacy Specification - Assistant de conformité à la facturation électronique

> Ce document définit les exigences de sécurité et de confidentialité du produit, à partir de `01-intent-note.md` à `09-test-strategy.md`. Il ne fabrique aucune obligation légale, aucune durée de conservation, aucune base légale RGPD au-delà de ce qui est documenté dans `02-regulatory-study.md` ou vérifiable par une source officielle. Toute question juridique non tranchée par les documents précédents est explicitement signalée par la mention **« À confirmer juridiquement »**, plutôt que résolue par une affirmation technique.

## 1. Introduction

Ce produit manipule des données financières (factures, montants, TVA), des données d'identification d'entreprise (SIREN, raison sociale) et des données personnelles (comptes utilisateurs, informations de clients particuliers). Il s'agit d'un logiciel de conformité réglementaire : une faille de sécurité ou une atteinte à la confidentialité y aurait un impact aggravé, s'ajoutant directement à la défiance déjà identifiée comme risque produit (`03-market-analysis.md`, section 15 ; `04-product-requirements.md`, section 29). Ce document construit les contrôles de sécurité et de confidentialité **sur** l'architecture déjà actée (`06-technical-architecture.md`), le modèle de données (`07-data-model.md`) et le contrat API (`08-api-specification.md`), sans les redéfinir.

## 2. Security & Privacy Objectives

- **Confidentialité** : aucune donnée d'une organisation n'est jamais accessible à une autre (isolation multi-tenant, section 16), et aucune donnée sensible n'est exposée au-delà de ce qui est strictement nécessaire (minimisation, section 10, 29).
- **Intégrité** : les résultats de conformité, une fois produits, restent inaltérables (`07-data-model.md`, ADR-003 ; section 34 de ce document).
- **Disponibilité** : le système reste accessible dans des conditions raisonnables pour un produit non critique en temps réel (`06-technical-architecture.md`, section 2).
- **Traçabilité** : toute action significative peut être reconstruite a posteriori (section 33-34).
- **Conformité RGPD** : les traitements de données personnelles sont identifiés, documentés et leur base légale clarifiée dans la mesure du possible, avec toute incertitude explicitement signalée (sections 9, 41).
- **Résilience** : le système peut se remettre d'un incident de sécurité ou d'une perte de données sans compromettre l'auditabilité déjà garantie par construction (sections 54, 55, 59).

## 3. Security Principles

| Principe                  | Application dans ce document                                                                                                                                                                                                                                        |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Security by Design        | Les contrôles de ce document s'appuient sur des frontières déjà posées architecturalement (`06-technical-architecture.md`), pas ajoutées après coup                                                                                                                 |
| Privacy by Design         | Minimisation déjà actée au niveau du modèle de données (`07-data-model.md`, section 27, AI Gateway) et étendue ici à l'ensemble des flux (section 11)                                                                                                               |
| Least Privilege           | Chaque module backend n'accède qu'aux données de sa responsabilité (`06-technical-architecture.md`, section 7) ; chaque fournisseur externe ne reçoit que le contexte minimal nécessaire (section 29)                                                               |
| Defense in Depth          | L'isolation tenant est vérifiée à la fois au niveau de la session (`08-api-specification.md`, section 9), de la couche d'accès aux données (`07-data-model.md`, section 4) et des tests bloquants (`09-test-strategy.md`, section 22) - trois couches indépendantes |
| Fail Secure               | Une erreur technique ne doit jamais dégénérer en accès non contrôlé ; en cas de doute sur l'appartenance d'une ressource, le système refuse plutôt que d'autoriser (section 17)                                                                                     |
| Zero Trust                | Aucune requête frontend n'est considérée comme fiable sans revalidation serveur (section 15) ; aucun document importé n'est considéré comme fiable sans validation (section 22) ; aucune sortie IA n'est considérée comme fiable sans contrôle (section 32)         |
| Data Minimization         | Principe déjà posé dans `06-technical-architecture.md` (section 14) pour l'IA, étendu ici à l'ensemble des flux de données personnelles (section 9-10)                                                                                                              |
| Explicit Trust Boundaries | Cartographiées en section 7                                                                                                                                                                                                                                         |
| Auditability              | `AuditLogEntry` déjà modélisée (`07-data-model.md`, section 20), complétée ici par les événements de sécurité (section 33)                                                                                                                                          |
| Secure Defaults           | Toute nouvelle donnée ou fonctionnalité est, par défaut, tenant-scoped, non exposée publiquement et non transmise à un fournisseur externe sauf décision explicite                                                                                                  |

## 4. Threat Model - Assets

| Actif                                                                        | Confidentialité                              | Intégrité       | Disponibilité | Conséquences d'une compromission                                                                                                                     |
| ---------------------------------------------------------------------------- | -------------------------------------------- | --------------- | ------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Comptes utilisateurs (`User`)                                                | Élevée                                       | Élevée          | Moyenne       | Usurpation d'identité, accès à toutes les données de l'organisation associée                                                                         |
| Données d'entreprise (`Organization`, `FiscalContext`)                       | Moyenne à élevée                             | Élevée          | Moyenne       | Diagnostic de conformité erroné si altéré ; exposition de l'identité légale si divulgué                                                              |
| Clients (`Customer`)                                                         | Élevée (peut inclure des particuliers)       | Moyenne         | Faible        | Exposition de données personnelles de tiers non utilisateurs du produit                                                                              |
| Factures et lignes (`Invoice`, `InvoiceLine`)                                | Élevée                                       | Élevée          | Moyenne       | Exposition de données financières ; une altération pourrait fausser un résultat de conformité                                                        |
| Documents importés (`Document`)                                              | Élevée                                       | Élevée          | Moyenne       | Exposition de factures complètes, potentiellement plus riches en données que le modèle structuré                                                     |
| Résultats de conformité (`ComplianceAnalysis`, `ComplianceFinding`)          | Moyenne                                      | **Très élevée** | Moyenne       | Une altération est la pire compromission possible pour ce produit spécifique - elle romprait la proposition de valeur centrale (`01-intent-note.md`) |
| Règles réglementaires (`RegulatoryRule`, `RuleVersion`)                      | Faible (référentiel non confidentiel en soi) | **Très élevée** | Moyenne       | Une altération non autorisée fausserait tous les résultats de conformité produits ensuite                                                            |
| Journal d'audit (`AuditLogEntry`)                                            | Moyenne                                      | **Très élevée** | Moyenne       | Une falsification masquerait une compromission antérieure                                                                                            |
| Secrets et identifiants d'intégration (`IntegrationConfig.secret_reference`) | **Très élevée**                              | Élevée          | Moyenne       | Compromission en cascade de tous les fournisseurs externes                                                                                           |
| Jetons de session                                                            | **Très élevée**                              | Élevée          | Faible        | Usurpation de session active                                                                                                                         |

## 5. Threat Model

**Menaces externes** : attaquant internet automatisé (scan, brute force) ; compte utilisateur compromis (identifiants réutilisés/fuités ailleurs) ; fraudeur cherchant à faire produire un faux résultat de conformité à des fins de couverture ; upload de fichier malveillant se faisant passer pour une facture.

**Menaces internes** : risque limité au MVP (Phases 0-12) compte tenu du rôle unique (`OWNER`) et de l'absence d'équipe (développeur solo) ; pertinent pour un accès administratif interne mal contrôlé (`08-api-specification.md`, section 38.1, API Regulatory Rules). **Élevé depuis la Phase 15 (DEC-010)** : l'introduction du rôle `PlatformAdministrator` (cross-tenant, `06-technical-architecture.md` ADR-009) change fondamentalement l'ampleur de ce risque - la compromission d'un compte tenant reste limitée à une organisation, alors que la compromission d'un `PlatformAdministrator` expose potentiellement **toutes** les organisations. Voir section 17 bis (nouvelle) pour les contrôles dédiés.

**Menaces applicatives** : IDOR (section 17), injection (section 19), XSS (section 19), CSRF (section 20), SSRF (pertinence limitée, section 19), upload malveillant (section 22), mauvaise gestion de session (section 14).

**Menaces liées aux documents** : fichier malveillant déguisé en facture, contenu actif dans un PDF, parsing vulnérable des formats structurés (XML notamment, section 23), zip bomb si un format archive était introduit, dépassement de taille (section 22).

**Menaces liées à l'IA** : prompt injection via le contenu d'un document ou d'une question utilisateur, fuite de données via le contexte transmis, hallucination présentée comme un fait réglementaire, exfiltration de données via un contexte trop large, exposition de données sensibles dans les logs d'échange avec le fournisseur.

**Menaces liées aux fournisseurs externes** : compromission d'un fournisseur (IA, email, stockage), indisponibilité affectant la disponibilité du produit, mauvaise configuration de ces services, fuite via des logs détenus par un tiers.

## 6. STRIDE Analysis

Appliqué aux composants principaux (`06-technical-architecture.md`, section 6), en ne retenant que les menaces réellement pertinentes pour ce produit :

| Composant                   | Spoofing                                                 | Tampering                                                                                                                 | Repudiation                                                                                                                        | Information Disclosure                                                          | DoS                                                                                                  | Elevation of Privilege                                                                      |
| --------------------------- | -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Frontend                    | Usurpation de session (cookie/jeton volé)                | Manipulation de requêtes côté client (non fiable par nature, section 15)                                                  | Faible pertinence côté client                                                                                                      | Fuite via logs navigateur, XSS                                                  | Faible pertinence                                                                                    | Non pertinent (aucune logique d'autorisation côté client)                                   |
| API                         | Jeton falsifié ou rejoué                                 | Payloads manipulés (section 19)                                                                                           | Absence d'audit sur une action → répudiation possible (mitigée section 33)                                                         | Erreurs trop verbeuses (`08-api-specification.md`, section 60)                  | Absence de rate limiting (`08-api-specification.md`, section 22)                                     | Élévation via une faille d'autorisation (section 15)                                        |
| Backend / Compliance Engine | Non pertinent directement (pas d'accès externe direct)   | Altération d'une `RuleVersion` existante - **empêchée structurellement par l'immutabilité** (`07-data-model.md`, ADR-003) | Absence de traçabilité de la sélection de règle → mitigée par `ComplianceFinding.rule_version_id` (`07-data-model.md`, section 18) | Fuite d'un résultat vers un autre tenant (section 16)                           | Analyse coûteuse déclenchée en boucle (rate limiting, `08-api-specification.md` section 22)          | Non pertinent (pas de notion de privilège interne au moteur)                                |
| Document Processor          | Fichier se faisant passer pour un format qu'il n'est pas | Contenu falsifié pour tromper l'extraction                                                                                | Absence de trace de traitement → mitigée par `DocumentProcessingRecord`                                                            | Extraction excessive de métadonnées non nécessaires                             | Fichier volumineux ou complexe consommant des ressources excessives (zip bomb, XML bomb, section 23) | Non pertinent                                                                               |
| Queue                       | Job falsifié injecté directement                         | Job modifié en transit                                                                                                    | Job traité sans trace de son origine                                                                                               | Contenu de job exposant des données sensibles en clair                          | Inondation de jobs                                                                                   | Non pertinent                                                                               |
| Stockage objet              | Accès à une URL de téléchargement sans autorisation      | Remplacement d'un fichier existant                                                                                        | Absence de log d'accès                                                                                                             | URL prévisible ou trop longue durée de vie (section 24)                         | Non pertinent au MVP                                                                                 | Non pertinent                                                                               |
| AI Gateway / Provider       | Non pertinent directement                                | Réponse manipulée en transit (TLS, section 25)                                                                            | Absence de trace de l'échange → mitigée section 33                                                                                 | **Risque majeur** - fuite de contexte au-delà du strict nécessaire (section 29) | Coût incontrôlé (`06-technical-architecture.md`, section 15)                                         | Non pertinent (l'IA n'a structurellement aucun privilège d'écriture, section 32)            |
| Intégrations externes       | Compromission des identifiants d'un fournisseur          | Falsification d'une réponse externe                                                                                       | Absence de log de synchronisation                                                                                                  | Fuite via un fournisseur tiers                                                  | Indisponibilité du fournisseur                                                                       | Non pertinent au MVP (aucune intégration active)                                            |
| Administration (interne, Regulatory Rules) | Usurpation de l'accès admin                | Modification non autorisée d'une règle                                                                                    | Absence de traçabilité d'une création de règle → mitigée section 33                                                                | Exposition de l'API admin publiquement                                          | Non pertinent                                                                                        | **Risque élevé si mal isolée** de l'API utilisateur (`08-api-specification.md`, section 38.1) |
| **Platform Admin (Phase 15, nouveau)** | Usurpation d'un compte `PlatformAdministrator` (**MFA obligatoire en contrôle direct**) | Suspension abusive d'une organisation, notification frauduleuse diffusée à tous les utilisateurs | Absence de traçabilité d'une action cross-tenant → **mitigée obligatoirement**, jamais optionnellement (section 17 bis) | **Risque le plus élevé du produit** - un accès cross-tenant mal isolé exposerait toutes les organisations simultanément (section 16) | Diffusion globale mal maîtrisée impactant tous les utilisateurs (`08-api-specification.md` section 22) | **Risque structurel principal de cette phase** - c'est précisément pour le contenir qu'ADR-009 exige une identité séparée, jamais un simple rôle sur `User` |

## 7. Trust Boundaries

```text
Internet
   │  (non fiable par défaut - Zero Trust)
   ▼
Frontend
   │  requêtes non fiables, revalidées côté serveur (section 15)
   ▼
API (authentification + tenant résolu depuis la session, 08-api-specification.md §9)
   │
   ▼
Backend (modules métier, 06-technical-architecture.md §6)
   │
   ├── Database (accès filtré par tenant_id, 07-data-model.md §4)
   ├── Object Storage (accès par référence opaque, section 24)
   ├── Queue (jobs porteurs du contexte tenant, section 16)
   ├── AI Provider (contexte minimisé uniquement, section 29)
   └── External Providers (email, vérification d'entreprise - section 44)
```

| Frontière                    | Données traversantes                                       | Menaces principales                | Contrôles                                                                              |
| ---------------------------- | ---------------------------------------------------------- | ---------------------------------- | -------------------------------------------------------------------------------------- |
| Internet → Frontend          | Aucune donnée sensible stockée côté client au repos        | XSS, session hijacking             | CSP (section 47), cookies sécurisés (section 14)                                       |
| Frontend → API               | Identifiants, payloads métier                              | CSRF, altération de requête        | TLS, authentification, CSRF si pertinent (section 20)                                  |
| API → Backend                | Requête authentifiée et résolue au tenant                  | IDOR, autorisation contournée      | Vérification systématique tenant + permission (sections 15-17)                         |
| Backend → Database           | Requêtes filtrées par tenant                               | Injection, fuite cross-tenant      | Requêtes paramétrées, filtrage centralisé (section 19)                                 |
| Backend → Object Storage     | Fichiers, référence opaque                                 | Accès direct non autorisé          | URLs temporaires, contrôle d'accès (section 24)                                        |
| Backend → AI Provider        | Contexte minimisé d'un `ComplianceFinding`                 | Fuite de données, prompt injection | Minimisation stricte (section 29), traitement du contenu comme non fiable (section 31) |
| Backend → External Providers | Selon fournisseur (email : adresse ; vérification : SIREN) | Fuite via un tiers compromis       | DPA, vérification de la localisation (section 44)                                      |
| Internet → Platform Admin (Phase 15) | Identifiants + MFA, jamais un jeton tenant-scoped   | Usurpation, contournement du MFA, confusion avec le chemin tenant | Authentification et autorisation structurellement séparées du reste de l'API (ADR-009), MFA obligatoire, surface isolée (application séparée ou route strictement isolée, section 17 bis) |
| Platform Admin → Backend (cross-tenant) | Requêtes explicitement cross-tenant, jamais via `TenantFilter` | Accès non audité, élévation de privilège | Audit systématique et obligatoire de chaque accès (section 17 bis), jamais un accès silencieux |

## 8. Data Classification

| Catégorie     | Exemple                                                                                                                                  | Sensibilité | Protection                                                               |
| ------------- | ---------------------------------------------------------------------------------------------------------------------------------------- | ----------- | ------------------------------------------------------------------------ |
| Public        | Contenu de `RegulatoryRule` (référentiel non confidentiel), documentation produit                                                        | Faible      | Aucune protection spécifique au-delà de l'intégrité (section 6)          |
| Interne       | Métadonnées techniques, logs applicatifs non sensibles                                                                                   | Moyenne     | Accès restreint à l'exploitation, jamais exposé publiquement             |
| Confidentiel  | `Organization` (identité légale, SIREN), `Customer`, `Invoice`/`InvoiceLine`                                                             | Élevée      | Tenant-scoped strict (section 16), chiffrement au repos (section 25)     |
| Très sensible | Documents importés (`Document`), secrets d'intégration (`IntegrationConfig.secret_reference`), jetons de session, mots de passe (hashés) | Très élevée | Chiffrement renforcé, accès minimal, jamais loggés en clair (section 35) |

## 9. Personal Data Mapping

| Donnée                                                                                     | Personne concernée                                                       | Finalité                              | Base légale (indicative)                                                                  | Stockage                                                                | Accès                                | Durée                                                                                                           | Destinataires                                                    | Transfert                                                                                                    |
| ------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------ | ------------------------------------- | ----------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- | ------------------------------------ | --------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Email, identifiants de compte                                                              | Utilisateur (dirigeant d'entreprise)                                     | Authentification, accès au service    | **À confirmer juridiquement** - probablement exécution du contrat (fourniture du service) | Base de données, tenant-scoped                                          | Système, utilisateur lui-même        | Durée de vie du compte (`07-data-model.md`, section 36)                                                         | Aucun destinataire tiers par défaut                              | Aucun a priori                                                                                               |
| Nom, informations d'un client particulier (`Customer.customer_type = PARTICULIER`)         | Client de l'utilisateur (tiers, pas utilisateur du produit)              | Vérification de conformité de facture | **À confirmer juridiquement** - intérêt légitime probable, à valider                      | Base de données, tenant-scoped                                          | Organisation propriétaire uniquement | Alignée sur la conservation de la facture (`07-data-model.md`, section 36 - elle-même signalée « à confirmer ») | Aucun destinataire tiers par défaut                              | Aucun a priori, sauf si le fournisseur IA reçoit un contexte incluant cette donnée (à minimiser, section 29) |
| Contenu d'un document de facture importé (peut contenir des données personnelles de tiers) | Client, éventuellement autres personnes mentionnées sur la facture       | Analyse de conformité                 | **À confirmer juridiquement**                                                             | Stockage objet + métadonnées en base                                    | Organisation propriétaire uniquement | À confirmer (`07-data-model.md`, section 36)                                                                    | Aucun destinataire tiers, sauf traitement d'extraction (interne) | Aucun a priori                                                                                               |
| Adresse IP, métadonnées de connexion (si journalisées)                                     | Utilisateur                                                              | Sécurité, audit                       | **À confirmer juridiquement** - intérêt légitime de sécurité probable                     | `AuditLogEntry` ou logs techniques                                      | Système, accès restreint             | À confirmer                                                                                                     | Aucun destinataire tiers                                         | Aucun a priori                                                                                               |
| Contexte transmis au fournisseur IA (extrait minimisé d'un `ComplianceFinding`)            | Potentiellement l'utilisateur ou son client, selon le contenu du finding | Reformulation pédagogique             | **À confirmer juridiquement**                                                             | Non stocké côté fournisseur au-delà de sa propre politique (section 30) | Fournisseur IA                       | Selon la politique du fournisseur (section 30, à vérifier)                                                      | Le fournisseur IA lui-même (sous-traitant potentiel, section 43) | Possible transfert hors UE selon le fournisseur retenu (section 45)                                          |

> Aucune base légale n'est ici affirmée comme définitive : chaque ligne reste marquée « à confirmer juridiquement », conformément à la consigne de ne pas déterminer une base légale sans validation professionnelle.

## 10. Privacy by Design

- **Minimisation** : chaque module ne collecte que les données nécessaires à sa fonction (`06-technical-architecture.md`, section 27, principe déjà posé) ; en particulier, aucune donnée n'est demandée à l'utilisateur au-delà de ce que justifie une exigence fonctionnelle tracée (`04-product-requirements.md` à `08-api-specification.md`).
- **Limitation des finalités** : les données d'un `Customer` ne sont utilisées que pour la vérification de conformité, jamais à des fins commerciales ou de profilage non prévues par le PRD.
- **Séparation** : documents bruts (stockage objet) séparés des métadonnées structurées (base relationnelle), déjà posé en `06-technical-architecture.md` (section 21, 27) - limite l'exposition en cas de compromission partielle.
- **Pseudonymisation** : non appliquée au MVP pour les données métier elles-mêmes (une facture nécessite une identification claire de son client pour être vérifiée) ; **appliquée systématiquement pour les données de test** (section 60, cohérent avec `09-test-strategy.md` section 10).
- **Suppression** : mécanismes de soft/hard delete déjà différenciés (`07-data-model.md`, section 30), détaillés section 39.
- **Rétention** : voir section 38.
- **Contrôle d'accès** : voir sections 15-17.
- **Traçabilité** : voir sections 33-34.

## 11. Data Flow Mapping

```text
Utilisateur
   ↓ (HTTPS, authentifié)
Frontend
   ↓ (HTTPS, authentifié)
API
   ↓
Backend
   ├── PostgreSQL (données structurées, tenant-scoped)
   ├── Object Storage (documents, référence opaque)
   ├── Compliance Engine (traitement interne, aucune sortie externe directe)
   ├── Queue (jobs porteurs du contexte tenant)
   └── AI Gateway → AI Provider (contexte minimisé uniquement)
```

| Flux                         | Données envoyées                                                         | Données reçues       | Chiffrement                                               | Authentification                                          | Risques                                                      | Conservation                                  |
| ---------------------------- | ------------------------------------------------------------------------ | -------------------- | --------------------------------------------------------- | --------------------------------------------------------- | ------------------------------------------------------------ | --------------------------------------------- |
| Utilisateur → Frontend → API | Identifiants, payloads métier                                            | Résultats, erreurs   | TLS (section 25)                                          | Session/jeton (section 12)                                | Interception si TLS absent (exclu par principe)              | N/A (transit)                                 |
| API → Database               | Requêtes filtrées par tenant                                             | Enregistrements      | TLS interne recommandé, chiffrement au repos (section 25) | Identifiants de service, jamais partagés avec le frontend | Fuite cross-tenant si filtrage défaillant (section 16)       | Selon section 38                              |
| API → Object Storage         | Fichier binaire (upload), référence (lecture)                            | Fichier, métadonnées | TLS, chiffrement au repos (section 25)                    | Identifiants de service                                   | Accès direct non autorisé si URL mal protégée (section 24)   | Selon section 38                              |
| Backend → AI Provider        | Contexte minimisé d'un `ComplianceFinding` (section 29)                  | Texte reformulé      | TLS                                                       | Clé d'API du fournisseur (secret, section 27)             | Fuite de contexte, rétention par le fournisseur (section 30) | Selon la politique du fournisseur, à vérifier |
| Backend → Email Provider     | Adresse email, contenu du message (notification, récupération de compte) | Statut d'envoi       | TLS                                                       | Clé d'API du fournisseur                                  | Fuite via le fournisseur email                               | Selon la politique du fournisseur             |

## 12. Authentication

Reprend et précise `06-technical-architecture.md` (section 19) et `08-api-specification.md` (section 7), sans remplacer les choix déjà actés :

- **Inscription/connexion** : par email et mot de passe (section 13). **Mécanisme désormais tranché : JWT access token courte durée + Refresh Token en cookie `HttpOnly`, `Secure`, `SameSite`** (décision produit, `06-technical-architecture.md` ADR-007), cohérent avec la séparation physique du frontend Next.js et du backend Symfony - un jeton porteur est plus naturel qu'une session serveur classique dans cette configuration à deux applications, tout en conservant l'essentiel des garanties de sécurité qu'apportait un cookie de session classique grâce au traitement du refresh token comme un cookie sécurisé. Le backend Symfony reste l'autorité d'authentification. Le schéma retenu, désormais **acté au niveau de son principe** :
  - **Access token** : JWT à **durée de vie courte** (de l'ordre de quelques minutes à une quinzaine de minutes - valeur précise à calibrer en implémentation), transmis en en-tête `Authorization: Bearer <token>`, conservé **en mémoire côté frontend** (variable d'application, jamais `localStorage` ni `sessionStorage`) pour limiter son exposition à un vol via XSS (section 19) - un access token volé a une fenêtre d'exploitation réduite du fait de sa courte durée de vie.
  - **Refresh token** : durée de vie plus longue, utilisé uniquement pour obtenir un nouvel access token via `POST /auth/refresh` (`08-api-specification.md`, section 23). **Stocké dans un cookie `HttpOnly`, `Secure`, `SameSite=Lax` ou `Strict`**, plutôt que côté JavaScript - ce choix hybride (access token en mémoire, refresh token en cookie sécurisé) combine la simplicité d'un jeton porteur pour l'API avec la protection XSS d'un cookie `HttpOnly` pour le secret le plus sensible et le plus longuement valide. Conséquence directe : une protection CSRF ciblée reste nécessaire sur `POST /auth/refresh`, seul endpoint où le cookie est transmis automatiquement par le navigateur (section 20).
  - **Rotation** : à chaque utilisation du refresh token, émission d'un nouveau refresh token et invalidation de l'ancien (rotation systématique), pour détecter une réutilisation frauduleuse (signal de compromission si un refresh token déjà consommé est présenté à nouveau).
  - **Révocation** : liste de révocation ou mécanisme équivalent côté Symfony pour invalider un refresh token avant son expiration naturelle (déconnexion explicite, changement de mot de passe, compromission suspectée) - un JWT signé n'étant pas révocable par nature une fois émis, seule l'invalidation du refresh token associé permet de couper l'accès à moyen terme ; l'access token en circulation reste valide jusqu'à sa courte expiration, ce qui est le compromis assumé de ce mécanisme.
  - **Ce qui reste à préciser en implémentation** : les paramètres précis (durées exactes access/refresh, bibliothèque Symfony retenue) - le schéma lui-même (JWT + refresh en cookie `HttpOnly`) est acté et ne constitue plus une simple recommandation.
- **Logout** : invalidation effective de la session côté serveur, pas seulement suppression côté client.
- **Expiration** : une session doit expirer après une durée d'inactivité raisonnable - durée précise **à définir selon les besoins business**, non fixée arbitrairement ici.
- **Récupération de compte** (US-AUTH-003) : jeton à usage unique, à durée de vie courte, invalidé après utilisation ou expiration.
- **Vérification email** : désormais **tranchée et obligatoire avant toute fonctionnalité sensible** (upload, analyses persistantes, usage de l'IA, fonctionnalités avancées), mais pas nécessairement bloquante avant toute utilisation basique du compte - ce qui couvre de fait, et au-delà, le cas de la récupération de compte évoqué dans une version antérieure de ce document.
- **MFA** : non nécessaire au MVP compte tenu du volume et de la nature du produit ; à réévaluer si le produit venait à manipuler des opérations plus sensibles (transmission réelle de factures, Future Scope explicitement exclu du MVP).

## 13. Password Security

- **Hashing** : les mots de passe ne sont **jamais** stockés en clair - algorithme de hachage adapté aux mots de passe (à choisir en implémentation parmi les standards reconnus, non fixé ici pour ne pas figer un choix technique prématuré), avec salage individuel systématique.
- **Politique de mot de passe** : longueur minimale raisonnable plutôt qu'une exigence de complexité artificielle (cohérent avec les recommandations actuelles de sécurité, qui privilégient la longueur) - seuil précis à définir en implémentation.
- **Protection contre le brute force** : limitation de débit sur `/auth/login` (`08-api-specification.md`, section 22), avec verrouillage temporaire ou ralentissement progressif après un nombre d'échecs successifs.
- **Récupération/changement** : via le mécanisme de la section 12, jamais par envoi du mot de passe existant.
- **Compromission** : en cas de suspicion de fuite d'une base d'identifiants (interne ou via un tiers), invalidation forcée de toutes les sessions actives et notification à l'utilisateur (section 56).

## 14. Session Security

- **Cookie du refresh token** (section 12) : `Secure` (jamais transmis en clair), `HttpOnly` (inaccessible en JavaScript, protection XSS), `SameSite=Lax` ou `Strict` (protection CSRF de base sur `/auth/refresh`).
- **Durée** : access token à durée de vie courte, refresh token à durée de vie plus longue mais bornée - valeurs précises à définir en implémentation, non fixées arbitrairement ici.
- **Rotation** : régénération du refresh token à chaque rafraîchissement (section 12), pour empêcher sa réutilisation prolongée en cas de vol.
- **Révocation** : possibilité de révoquer un refresh token à la demande (déconnexion) ou automatiquement (changement de mot de passe, compromission suspectée) ; l'access token correspondant reste valide jusqu'à sa courte expiration (compromis assumé du mécanisme JWT, section 12).
- **Gestion des appareils** : **non nécessaire au MVP** - aucune exigence identifiée dans les documents précédents ne justifie une gestion multi-appareils complexe (liste des sessions actives, révocation sélective) ; cohérent avec le principe de ne pas complexifier sans justification.

## 15. Authorization

Rappel du principe fondamental : **l'autorisation n'est jamais déterminée côté client** - chaque requête est revalidée côté serveur, quelle que soit l'interface qui l'a émise (`08-api-specification.md`, section 8-9).

**Historique (MVP, Phases 0-12)** : un seul rôle (`OWNER`) existait (`04-product-requirements.md`, section 21 historique) : toute action authentifiée était vérifiée sur deux axes systématiquement, jamais un seul :

1. **L'utilisateur est bien membre actif de l'organisation** dont il tente d'agir sur les données.
2. **La ressource ciblée appartient bien à cette organisation** (section 17).

Cette double vérification doit être appliquée à **chaque** endpoint manipulant une ressource tenant-scoped, sans exception et sans mutualisation implicite - un oubli sur un seul endpoint suffirait à créer une brèche (cohérent avec `09-test-strategy.md`, section 22, tests bloquants).

**Depuis la Phase 14 (DEC-009)** : un troisième axe s'ajoute aux deux ci-dessus, désormais
vérifié systématiquement pour toute action - **le rôle de l'appelant au sein de cette
organisation autorise-t-il cette action précise ?** (matrice `OWNER`/`ADMIN`/`COLLABORATOR`,
`04-product-requirements.md` section 21.1). Cette vérification reste **centralisée dans une
couche unique**, jamais dupliquée par endpoint - c'est précisément la préparation anticipée
ci-dessous qui a permis cette extension sans réécrire chaque handler.

**Matrice reprise à l'identique de `04-product-requirements.md` section 21.1 (implémentation :
`App\Shared\Security\OrganizationPermissionVoter`, Voter unique)** :

| Permission              | OWNER | ADMIN              | COLLABORATOR |
| ------------------------ | :---: | :-----------------: | :----------: |
| `team:read`              | Oui   | Oui                 | Oui          |
| `team:invite`             | Oui   | Oui                 | Non          |
| `team:manage_roles`       | Oui   | Non                 | Non          |
| `team:remove`             | Oui   | Oui (jamais OWNER)  | Non          |
| `notification:send_team`  | Oui   | Oui                 | Non          |
| `organization:update`     | Oui   | Oui                 | Non          |

`team:read` accordé aux trois rôles (précision Phase 14) : la restriction porte sur la
**gestion** d'équipe, jamais sur sa simple consultation - cohérent avec le fait qu'un
`COLLABORATOR` consulte déjà librement factures/clients/diagnostics (section 21.1 du PRD).
`notification:read`/`notification:update` (`GET /notifications`, `PATCH /notifications/{id}/read`)
ne figurent volontairement **pas** dans cette matrice : ce ne sont jamais des permissions par
rôle, mais un contrôle de propriété de ressource (`recipient_user_id = utilisateur courant`,
appliqué par `App\Notification\Repository\NotificationRepository`, jamais par ce Voter - voir
`08-api-specification.md` section 34).

**Depuis la Phase 15 (DEC-010)** : le rôle `PlatformAdministrator` suit un chemin
d'autorisation **entièrement distinct**, jamais une extension de la matrice ci-dessus - aucune
des trois vérifications tenant-scoped (membre actif, appartenance de ressource, rôle
d'organisation) ne s'applique à ce rôle, qui n'a par construction ni organisation ni
`Membership`. Sa propre autorisation est détaillée en section 17 bis.

(Historique, réalisé) L'architecture d'autorisation avait été conçue pour pouvoir accueillir
un rôle supplémentaire sans réécriture complète (cohérent avec `06-technical-architecture.md`,
section 19/39) - c'est ce qui a rendu possible l'extension des Phases 14-15 ci-dessus.

## 16. Multi-Tenant Security

**Exigence critique**, cohérente avec `06-technical-architecture.md` (ADR-004) et `07-data-model.md` (section 4) : aucun accès croisé entre `Tenant A` et `Tenant B`, sous aucune circonstance.

| Composant                                 | Contrôle                                                                                                                                                                                                                                                                                                   |
| ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Repositories / couche d'accès aux données | Filtrage systématique par `organization_id`, centralisé (ne dépend jamais d'un `WHERE` ajouté manuellement à chaque requête)                                                                                                                                                                               |
| Services métier                           | Reçoivent le `organization_id` du contexte authentifié, jamais un `organization_id` fourni librement par le payload d'une requête                                                                                                                                                                          |
| API                                       | Tenant résolu depuis la session, jamais depuis l'URL (`08-api-specification.md`, section 9)                                                                                                                                                                                                                |
| Documents / stockage objet                | Référence opaque associée au tenant propriétaire ; toute tentative d'accès à une référence d'un autre tenant refusée (section 24)                                                                                                                                                                          |
| Cache (si introduit ultérieurement)       | Toute clé de cache doit inclure le `organization_id` - **aucun cache partagé entre tenants** ne doit être introduit sans cette garantie                                                                                                                                                                    |
| Logs                                      | Les entrées de logs incluent le `organization_id` pour le diagnostic, mais ne sont jamais exposées à un autre tenant (accès restreint à l'exploitation interne, section 35)                                                                                                                                |
| Jobs asynchrones                          | **Point d'attention particulier** : chaque job doit porter explicitement le `organization_id` du tenant d'origine dans son payload, et le worker qui le traite doit appliquer ce contexte à chaque opération qu'il effectue - un job qui « oublierait » son tenant d'origine serait une brèche potentielle |
| Notifications                             | Envoyées uniquement au(x) membre(s) de l'organisation concernée, **sauf** une notification `sender_type=PLATFORM_ADMIN` (Phase 15) qui traverse délibérément les tenants par construction (même exception que la ligne Platform Admin ci-dessous, jamais un cas distinct) |
| Exports (si introduits ultérieurement)    | Aucun export ne doit pouvoir agréger des données de plusieurs tenants                                                                                                                                                                                                                                      |
| IA                                        | Le contexte transmis à l'AI Gateway (section 29) ne concerne jamais qu'un seul tenant à la fois, jamais un contexte agrégé                                                                                                                                                                                 |
| Platform Admin (Phase 15)                 | **Seule exception structurelle et volontaire** à ce tableau : le module `PlatformAdmin` (`06-technical-architecture.md`, ADR-009) accède délibérément à travers plusieurs tenants, jamais via le filtrage automatique ci-dessus mais via des requêtes explicitement cross-tenant, réservées à ce module, systématiquement auditées (section 17 bis) - toute autre partie du code reste soumise sans exception au filtrage par `organization_id` |

## 17. IDOR / Broken Access Control

Pour toute route de la forme `GET /invoices/{id}` (ou équivalent pour `customers`, `documents`, `compliance-analyses`, etc.), la vérification systématique suit :

```text
Authentication (l'appelant est-il authentifié ?)
   +
Authorization (l'appelant a-t-il la permission logique pour cette action ?)
   +
Tenant ownership (la ressource {id} appartient-elle à l'organisation de l'appelant ?)
```

Si l'une de ces trois conditions échoue, la réponse est **`404 Not Found`**, jamais `403 Forbidden`, pour ne pas confirmer l'existence d'une ressource appartenant à une autre organisation (cohérent avec `08-api-specification.md`, section 42 et 60) - ce comportement doit être strictement uniforme sur l'ensemble de l'API, y compris pour les sous-ressources (`compliance-analyses/{id}/findings`, `documents/{id}/content`).

**IDOR intra-organisation (Phase 14, nouveau)** : la même discipline s'applique désormais **à
l'intérieur** d'une organisation, entre rôles. Un `COLLABORATOR` tentant d'accéder à une action
réservée à `OWNER`/`ADMIN` (`04-product-requirements.md` section 21.1) doit recevoir `403`,
pas `404` (l'existence de la ressource elle-même - un autre membre, une invitation - n'est pas
une information à cacher à un collègue de la même organisation, contrairement à une ressource
d'un autre tenant). Un `ADMIN` tentant une action réservée à `OWNER` (modifier/retirer
l'`OWNER` lui-même) suit la même règle.

**Accès cross-tenant du `PlatformAdministrator` (Phase 15)** : cette route de vérification à
trois conditions ne s'applique **jamais** à ce rôle - il n'a ni organisation ni `Membership`
sur lequel appliquer une condition de tenant ownership. Son propre modèle d'autorisation
(liste explicite d'actions permises, jamais un accès implicite) est détaillé en section 17 bis.
Un jeton `PlatformAdministrator` présenté sur un endpoint tenant normal doit être rejeté
(`401`, jeton non reconnu pour ce contexte d'authentification - jamais traité comme un
utilisateur tenant-scoped ordinaire).

## 17 bis. Sécurité de l'administration plateforme (Phase 15, ADR-009)

Section dédiée, à la mesure du risque introduit par cette phase (section 5, section 6 STRIDE -
« risque le plus élevé du produit »). S'applique exclusivement au rôle `PlatformAdministrator`,
jamais aux rôles d'organisation `OWNER`/`ADMIN`/`COLLABORATOR`.

**Authentification** :

- **MFA obligatoire, sans exception** - exigence de sécurité actée (DEC-010,
  `04-product-requirements.md` section 21.2), jamais une option activable. **Mécanisme retenu
  à l'implémentation (Phase 15, `../CLAUDE.md` section 21)** : TOTP (RFC 6238) via
  `spomky-labs/otphp` (version 11.5.0 au moment de l'implémentation, vérifiée activement
  maintenue et compatible PHP 8.4) - bibliothèque de primitives pures, jamais un bundle
  Symfony Security complet, cohérent avec le style déjà en place du projet (listeners JWT
  écrits à la main plutôt que des bundles à fort couplage). Secret chiffré au repos
  (`sodium_crypto_secretbox`, libsodium natif PHP 8.4, aucune dépendance ajoutée) - jamais en
  clair en base. Une clé de sécurité physique (WebAuthn/FIDO2) n'a pas été retenue pour cette
  phase : aucun besoin exprimé au-delà d'un unique administrateur au MVP de cette phase (section
  17 bis ci-dessous, "pas de RBAC interne à ce rôle"), pourrait être réévalué si le nombre
  d'administrateurs plateforme augmentait.
- Authentification **structurellement séparée** du mécanisme JWT tenant-scoped (ADR-007) -
  jamais le même émetteur de jeton, jamais une simple revendication (`claim`) supplémentaire
  sur un jeton par ailleurs identique à celui d'un `User` tenant-scoped. **Vérifié à
  l'implémentation** : `lexik/jwt-authentication-bundle` ne permettant pas une seconde paire de
  clés via sa configuration standard, un second trousseau JWT complet est câblé manuellement
  (`backend/config/services.yaml`, `platform_admin_jwt.*`) en réutilisant les classes du bundle,
  jamais en les réimplémentant - voir `12-roadmap.md`, bilan Phase 15.
- Surface d'accès : application front séparée si le coût reste raisonnable pour un développeur
  solo, sinon route strictement isolée (`06-technical-architecture.md`, ADR-009) - décision à
  documenter concrètement au moment de l'implémentation, jamais présumée d'office.

**Autorisation** :

- **Liste explicite des actions permises** (`08-api-specification.md`, section 38.2), jamais un
  accès complet implicite du seul fait d'être `PlatformAdministrator` - cohérent avec le
  principe de moindre privilège (section 3).
- Aucune notion de rôle supplémentaire au sein de `PlatformAdministrator` au MVP de cette phase
  (pas de RBAC interne à ce rôle) - à réévaluer seulement si plusieurs administrateurs
  plateforme aux périmètres distincts devenaient un jour nécessaires.

**Audit** :

- **Chaque lecture ou écriture cross-tenant est journalisée, sans exception** - identité de
  l'acteur (`PlatformAdministrator.id`), action, ressource(s) concernée(s), horodatage. Une
  action cross-tenant non auditée est traitée comme un défaut bloquant, jamais comme un détail
  d'implémentation à corriger plus tard.
- Le journal d'audit cross-tenant reste consultable via `GET /platform-admin/audit-events`
  (`08-api-specification.md` section 38.2) - jamais mélangé avec le journal d'audit
  tenant-scoped exposé par `GET /audit-events` (section 39 de `08-api-specification.md`).

**Test d'intrusion ciblé avant activation** (résout DL-011, `12-roadmap.md` section 50, dont le
raisonnement pour la Private Beta ne s'étend pas à cette phase - voir section 61 ci-dessous) :
couvre a minima authentification plateforme, MFA, autorisation, isolation tenant, IDOR/BOLA,
accès cross-tenant, suspension de comptes, audit trail, notifications globales/segmentées,
endpoints de santé, segmentation, élévation de privilèges - pas nécessairement un pentest
complet de toute l'application si le budget ne le justifie pas.

## 18. API Security

Rappel synthétique des contrôles déjà actés dans `08-api-specification.md`, complétés ici du point de vue sécurité :

- **Validation** stricte de tout payload à la frontière de l'API (`08-api-specification.md`, section 15), avant tout passage aux modules métier.
- **Authentication/Authorization** : sections 12, 15, 17 de ce document.
- **Rate limiting** : sur l'authentification, le déclenchement d'analyses, l'upload et l'assistant IA (`08-api-specification.md`, section 22), calibré pour limiter à la fois les abus et les coûts (section 39 de `08-api-specification.md`, IA).
- **CORS** : section 21.
- **Headers de sécurité** : section 47.
- **Sanitization des entrées** : toute donnée utilisateur potentiellement réaffichée (nom de client, description de ligne de facture) est traitée comme non fiable (section 19).
- **Taille des payloads** : limite explicite, en particulier pour l'upload de documents (section 22).
- **Erreurs** : le contrat d'erreur uniforme (`08-api-specification.md`, section 14) ne doit **jamais** exposer de détail d'implémentation interne (trace technique, requête SQL, chemin de fichier serveur) dans `error.message`.
- **Logs** : section 35.
- **Versionnement** : section 44-45 de `08-api-specification.md`, sans impact sécurité direct au MVP.

## 19. Injection Protection

| Type d'injection                           | Pertinence                                                                                           | Contrôle                                                                                                                                                                         |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| SQL / injection de requête base de données | Élevée si des requêtes sont construites dynamiquement                                                | Requêtes paramétrées systématiques, jamais de concaténation de chaînes pour construire une requête à partir d'une entrée utilisateur                                             |
| Command injection                          | Faible - aucun besoin identifié d'exécuter des commandes système à partir d'une entrée utilisateur   | Éviter tout appel système construit à partir d'une donnée utilisateur ; si nécessaire (traitement de document), utiliser des bibliothèques dédiées plutôt qu'un appel shell      |
| Template injection                         | Faible à moyenne - pertinente si des templates sont utilisés pour générer des emails ou des messages | Séparer strictement les données utilisateur du moteur de template, échapper systématiquement                                                                                     |
| XXE / injection XML                        | **Élevée** - le produit traite des formats XML (UBL, CII, Factur-X)                                  | Voir section 23, contrôle dédié                                                                                                                                                  |
| NoSQL injection                            | Non pertinent - le modèle de données est relationnel (`07-data-model.md`)                            | -                                                                                                                                                                                |
| XSS                                        | Élevée - toute donnée utilisateur réaffichée (nom, description) est un vecteur potentiel             | Échappement systématique côté frontend au rendu, Content-Security-Policy (section 47), validation côté API n'étant pas une protection suffisante à elle seule (defense in depth) |

## 20. CSRF

**Surface largement réduite par le mécanisme JWT retenu** (section 12) : la quasi-totalité des requêtes authentifiées portent l'access token en en-tête `Authorization`, jamais transmis automatiquement par le navigateur lors d'une requête cross-site - cela élimine le vecteur CSRF classique pour l'ensemble de l'API métier.

**Point résiduel : `POST /auth/refresh`**, si le refresh token est porté par un cookie (recommandation de la section 12). Ce seul endpoint reste exposé, car un cookie **est** transmis automatiquement par le navigateur lors d'une requête cross-site. Protection recommandée sur cet endpoint uniquement : `SameSite=Lax` ou `Strict` sur le cookie de refresh token (première ligne de défense, section 14) **combiné à** une vérification `Origin`/`Referer`. Un jeton anti-CSRF synchronisé supplémentaire n'est pas jugé nécessaire compte tenu de la protection déjà apportée par `SameSite` et du fait qu'un rafraîchissement réussi ne produit qu'un nouvel access token, sans effet métier direct - à réévaluer si ce raisonnement s'avérait insuffisant en pratique.

## 21. CORS

Politique stricte : **`Access-Control-Allow-Origin` limité explicitement au(x) domaine(s) du frontend officiel**, jamais `*`, en particulier parce que l'API manipule des données authentifiées avec des identifiants potentiellement portés par cookie (`Access-Control-Allow-Credentials: true` incompatible avec un wildcard d'origine par construction des standards CORS).

- **Origines autorisées** : le ou les domaines du frontend officiel (production, staging), configurés explicitement par environnement (section 53).
- **Méthodes** : limitées à celles réellement utilisées par l'API (`GET`, `POST`, `PATCH`, `DELETE`).
- **Headers** : limités à ceux nécessaires (`Authorization`, `Content-Type`, `Idempotency-Key`, `If-Match`, `X-Request-ID`).
- **Credentials** : autorisés uniquement si le mécanisme de session retenu (section 12) en a besoin (cookie).

**IP/schéma client réels à travers nginx (Phase 12, `docs/14-private-beta-plan.md`)** :
`backend/config/packages/framework.yaml` déclare `trusted_proxies: PRIVATE_SUBNETS` et
`trusted_headers: ['x-forwarded-for', 'x-forwarded-proto']`. Restrictif dans la topologie
actuelle car `backend:9000` n'est jamais publié hors du réseau Docker interne (`expose`,
jamais `ports:` dans `docker-compose.yml`) - `nginx` est structurellement le seul pair TCP
possible de PHP-FPM, donc faire confiance à `PRIVATE_SUBNETS` équivaut en pratique à ne faire
confiance qu'à nginx. `docker/nginx/default.conf` résout l'IP/le schéma réels via l'en-tête
`CF-Connecting-IP` (posé par l'edge Cloudflare lui-même lors d'un accès par tunnel bêta, jamais
falsifiable par le client - vérifié sur la documentation officielle Cloudflare, qui déconseille
explicitement `X-Forwarded-For` pour cet usage), avec repli sur `$remote_addr`/`$scheme` en
accès direct local (sans tunnel) - et **écrase** systématiquement la valeur transmise à
Symfony plutôt que de l'accumuler, empêchant un client de faire remonter sa propre valeur.
Sans ce correctif, le rate limiting de connexion et les logs de sécurité (section 36) ne
verraient que l'adresse de nginx, jamais celle de l'appelant réel - constaté empiriquement lors
de l'implémentation de la Phase 12. **Limite assumée** : `nginx` est publié sur `0.0.0.0:8080`
(pas seulement `127.0.0.1`) pour rester utilisable en développement local ; si la machine hôte
était elle-même directement joignable depuis Internet sur ce port, `CF-Connecting-IP` pourrait
être forgé en contournant le tunnel - hypothèse retenue : le réseau du développeur n'est pas
exposé publiquement en dehors du tunnel pendant les sessions de Private Beta.

## 22. File Upload Security

Contrôle critique, cohérent avec `06-technical-architecture.md` (section 26) et `09-test-strategy.md` (section 19) :

- **Taille maximale** : **20 Mo par fichier** au MVP (décision produit, `08-api-specification.md`, `09-test-strategy.md` section 19) - rejet avec `413` avant tout traitement. Formats initiaux supportés explicitement : PDF, Factur-X (PDF avec XML embarqué), XML CII/UBL si réellement supportés ; aucun type de fichier arbitraire n'est accepté.
- **Extension déclarée** : non fiable en elle-même, ne sert qu'd'indication initiale.
- **MIME type déclaré** : non fiable en lui-même (peut être falsifié par le client).
- **Magic bytes / inspection du contenu réel** : vérification que le contenu binaire correspond effectivement au format annoncé, **avant** toute tentative de parsing structuré (Factur-X, UBL, CII).
- **Nom de fichier** : neutralisé avant tout usage dans un chemin de stockage (suppression des séquences de traversée de répertoire, des caractères de contrôle) - jamais utilisé tel quel comme identifiant de stockage (cohérent avec `07-data-model.md`, section 13 : `storage_reference` est un identifiant opaque généré par le système, pas dérivé du nom de fichier).
- **Stockage** : dans le stockage objet dédié (`06-technical-architecture.md`, section 21), jamais dans un répertoire directement servi par le serveur web.
- **Antivirus** : décision désormais tranchée, en deux stades distincts. **MVP en environnement local/dev** : non indispensable - la validation MIME, l'inspection des magic bytes, la limite de taille et un parseur sécurisé (section 23) suffisent, compte tenu du volume attendu et du fait que les fichiers ne sont pas exécutés côté serveur. **Avant une mise en production réelle** : l'ajout d'un antivirus/sandboxing pour les fichiers uploadés devient **requis**, ce second seuil ne devant jamais être confondu avec le seuil, plus permissif, du développement MVP.
- **Parsing isolé** : le traitement d'extraction (section 11 de `06-technical-architecture.md`) doit être conçu pour échouer proprement sur un fichier malformé, sans faire planter le processus principal ni exposer d'erreur technique détaillée à l'utilisateur.
- **Suppression** : comportement désormais tranché (cohérent avec `07-data-model.md` section 30) - le fichier original est supprimé du stockage, mais le `ComplianceEvaluation`/résultat de conformité est **conservé** lorsqu'il est nécessaire à la traçabilité, avec une indication explicite que le document source a été supprimé ; les données dérivées contenant des données personnelles et non nécessaires à cette traçabilité sont supprimées ou anonymisées. Voir section 39 pour la tension résiduelle avec le droit à l'effacement RGPD, qui reste à valider juridiquement sur ses modalités fines.
- **Téléchargement sécurisé** : URL temporaire à durée de vie limitée (section 24), jamais un lien permanent et prévisible.

## 23. XML Security

Pertinent pour Factur-X (conteneur incluant un XML), UBL et CII (`02-regulatory-study.md`, section 9-10 ; `06-technical-architecture.md`, section 11) :

- **XXE (XML External Entity)** : le parseur XML utilisé doit être configuré pour **désactiver le traitement des entités externes** et des DTD, sans exception - un parseur XML par défaut, non configuré, est vulnérable par construction.
- **Entity expansion / XML bomb** : limitation du nombre d'entités et de la profondeur d'imbrication autorisée avant tout parsing complet.
- **Parsing dangereux** : privilégier des bibliothèques de parsing éprouvées et à jour (section 48) plutôt qu'un parseur écrit sur mesure.

Ces contrôles doivent être appliqués **avant** toute extraction de données métier depuis le fichier, dans l'étape de validation technique décrite en `06-technical-architecture.md` (section 11).

## 24. Document Storage

- **Séparation logique** : chaque document est associé explicitement à un `organization_id` (`07-data-model.md`, section 13), et cette association est vérifiée à chaque accès (section 16-17).
- **Permissions** : le stockage retenu pour le MVP - **système de fichiers local du projet** (`06-technical-architecture.md`, ADR-007), abstrait derrière `StorageInterface` - n'est jamais servi directement par le reverse proxy Nginx ni exposé dans un répertoire public ; tout accès passe obligatoirement par l'application Symfony, qui vérifie l'appartenance tenant (section 16-17) avant de retourner le fichier.
- **URLs temporaires** : tout téléchargement (`GET /documents/{id}/content`, `08-api-specification.md` section 31) passe par une route authentifiée générant l'accès à la demande. Avec un stockage local, cette URL reste interne à l'application (pas de redirection vers un stockage objet distant au MVP) ; le principe d'URL à durée de vie courte et non devinable, posé initialement pour anticiper une migration vers un stockage objet (S3, MinIO, Scaleway), reste la cible à appliquer dès que cette migration aura lieu (`06-technical-architecture.md`, section 17).
- **Chiffrement** : au repos, voir section 25.
- **Suppression** : cohérente avec `07-data-model.md` (section 30) - comportement désormais tranché : suppression physique du fichier original, sans perdre la traçabilité déjà garantie par `ComplianceFinding`/`ContextSnapshot`, qui reste accessible avec l'indication explicite que le document source a été supprimé (section 22). Voir section 39 pour la nuance RGPD résiduelle sur les modalités fines de purge.
- **Expiration des URLs** : courte (quelques minutes), régénérée à chaque demande de téléchargement plutôt que réutilisée.

## 25. Encryption

**In transit** : TLS obligatoire sur l'ensemble des flux - Frontend↔API, API↔fournisseurs externes, et idéalement Backend↔Database/Stockage même en interne (défense en profondeur), cohérent avec `08-api-specification.md` (section 55) et `06-technical-architecture.md` (section 26).

**At rest** :

- Base de données : PostgreSQL (`06-technical-architecture.md`, ADR-007) - chiffrement au repos activé au niveau du système de stockage (mécanisme dépendant de l'hébergeur retenu, non tranché ici).
- Stockage documentaire : le stockage local retenu pour le MVP (ADR-007) doit reposer sur un disque chiffré au niveau du système d'exploitation ou de l'hébergeur (chiffrement de volume), particulièrement important compte tenu de la sensibilité des documents importés (section 8) - **point de vigilance spécifique à ce choix** : un stockage local mal isolé expose davantage en cas de compromission du serveur applicatif lui-même (contrairement à un stockage objet distant, physiquement séparé) ; à documenter explicitement dans la configuration d'infrastructure avant mise en production.
- Sauvegardes : chiffrées, cohérent avec section 54 - la sauvegarde du dossier de stockage local doit être traitée avec la même rigueur que celle de la base de données (section 37 de `09-test-strategy.md`).

**Application-level encryption** : **non appliquée systématiquement** à toutes les données, pour ne pas détruire les capacités de recherche/filtrage (`08-api-specification.md`, section 16) ni complexifier excessivement le système (cohérent avec la consigne de ne pas sur-chiffrer). Un chiffrement applicatif supplémentaire, au-delà du chiffrement au repos de l'infrastructure, est recommandé spécifiquement pour : `IntegrationConfig.secret_reference` (déjà prévu comme référence opaque vers un mécanisme dédié, `07-data-model.md` section 22) et tout futur champ de secret équivalent - pas pour les données métier courantes (factures, clients).

## 26. Key Management

- **Clés de chiffrement** (infrastructure et applicative) : générées et gérées via un mécanisme dédié externe au code source, jamais codées en dur.
- **Rotation** : politique de rotation périodique à définir en implémentation, particulièrement pour les clés protégeant les secrets d'intégration (section 27).
- **Stockage** : séparé des données qu'elles protègent (principe de séparation, section 10).
- **Accès** : restreint au strict nécessaire (least privilege, section 3) - un développeur solo doit néanmoins veiller à ne pas concentrer un accès total et permanent à toutes les clés dans un seul poste de travail sans protection (mot de passe, chiffrement disque).
- **Séparation des environnements** : clés distinctes entre local/CI/test/staging/production (section 53) - une clé de staging ne doit jamais pouvoir déchiffrer une donnée de production, et réciproquement.
- **Révocation** : possibilité de révoquer et régénérer une clé compromise sans interruption prolongée du service.

## 27. Secrets Management

Secrets identifiés : identifiants de base de données, secret de signature de session/jeton, clés du fournisseur IA, clés du fournisseur email, identifiants du stockage objet, et (Future Scope) identifiants de paiement ou d'intégrations réglementaires.

**Règle absolue** : aucun secret n'est **jamais** commité dans le dépôt de code source (`.env` versionné, valeur en dur dans un fichier de configuration versionné). Tous les secrets sont injectés par variable d'environnement ou un mécanisme de gestion de secrets dédié, distincts par environnement (section 53), avec un accès restreint et audité si le mécanisme le permet.

**Mécanisme réel (Phase 19 Workstream B, exécuté le 31/08/2026)** : Infisical (self-hosted,
`github.com/Joello61/infrastructure`), un projet par produit applicatif (`FactuSentinel`,
environnements `staging`/`production` distincts) plus un projet `Infrastructure` pour les
secrets du socle partagé lui-même. Aucun fichier `.env.staging`/`.env.production` contenant
une valeur réelle sur le serveur - les secrets sont injectés directement dans
l'environnement du processus `docker compose` au déploiement (`infisical run`,
`docker/deploy/ssh-deploy.sh`), jamais écrits sur disque. Authentification non interactive
via Universal Auth (identité machine Infisical), **une identité par environnement**, jamais
partagée, avec un accès en lecture seule strictement scopé à son environnement (rôle projet
`No Access` + Additional Privilege `Describe Secret` + `Read Value` - les deux actions sont
nécessaires ensemble pour lire une valeur de secret, vérifié sur la documentation officielle
Infisical).

**Procédure de rotation réelle** :
1. Modifier/régénérer la valeur dans Infisical (projet et environnement concernés).
2. Selon le secret, une action complémentaire peut être nécessaire avant de redéployer :
   - `POSTGRES_PASSWORD` : `ALTER ROLE` sur le rôle PostgreSQL réel avant de redémarrer les
     services qui en dépendent (jamais l'inverse, sous peine de verrouiller l'accès
     applicatif) - sauf si l'environnement est réinitialisé à neuf, auquel cas la nouvelle
     valeur peut être fournie directement à l'initialisation.
   - `JWT_PASSPHRASE`/`PLATFORM_ADMIN_JWT_PASSPHRASE` : supprimer le volume nommé `jwt_keys`
     avant redémarrage - la passphrase chiffre la paire de clés déjà présente sur ce volume
     (`--skip-if-exists`), la changer seule sans régénérer les clés casse le déchiffrement
     (`bad decrypt`).
3. Redéployer (le nouveau conteneur reçoit la nouvelle valeur au démarrage, l'ancien est
   détruit avec l'ancienne).
4. Vérifier le parcours applicatif complet (inscription, connexion, email, IA) sur
   l'environnement concerné.

Une seule exception documentée à la règle « jamais de secret en clair sur disque » :
l'amorçage d'Infisical lui-même (identifiants de sa propre base Postgres/Redis) ne peut
structurellement pas être injecté depuis Infisical (rien n'existe encore pour les servir
avant qu'il soit démarré) - traité avec la même rigueur que tout autre secret par ailleurs
(généré via `openssl rand`, jamais committé), documenté comme cas particulier assumé.
`METRICS_SCRAPE_TOKEN` (lu par Prometheus via `credentials_file`, jamais une variable
d'environnement - limitation de Prometheus, pas d'Infisical) reste également un fichier sur
disque, mais géré et régénéré depuis Infisical plutôt que créé une fois à la main et jamais
retouché.

## 28. AI Security

Rappel structurant, cohérent avec `06-technical-architecture.md` (section 14-15) et `09-test-strategy.md` (section 29) : **l'IA est considérée comme une dépendance externe non fiable**, jamais comme un composant de confiance implicite.

- **Prompt injection** : un document importé ou une question utilisateur peut contenir du texte tentant de manipuler le comportement du modèle (« ignore les instructions précédentes », par exemple). Contrôle : le contenu d'un document ou d'une question utilisateur est **toujours traité comme une donnée**, jamais comme une instruction système (section 31).
- **Data leakage** : le contexte transmis au fournisseur est strictement minimisé (section 29) ; aucune fuite au-delà de ce périmètre ne doit être possible même si le modèle « demande » plus de contexte (l'AI Gateway ne peut structurellement pas lui en fournir davantage, `06-technical-architecture.md` section 15).
- **Context manipulation** : le contenu d'une facture ou d'un document ne doit jamais pouvoir modifier le comportement du Compliance Engine lui-même - l'IA n'a par construction aucun canal d'écriture vers le Compliance Engine (section 32).
- **Hallucination** : traitée comme un risque produit non éliminable techniquement à 100 %, mitigée par la fidélité imposée au contexte fourni et testée (`09-test-strategy.md`, sections 29-31), jamais résolue en accordant plus d'autorité à l'IA.
- **Cross-tenant leakage** : le contexte transmis à l'IA pour un tenant ne doit jamais inclure, même par erreur d'implémentation, une donnée d'un autre tenant - cohérent avec la section 16 de ce document.

## 29. AI Data Boundary

```text
Compliance Engine (résultat déjà produit, déterministe)
       ↓
Contexte validé (règle, source, résultat du ComplianceFinding ciblé - uniquement)
       ↓
Data Minimization (filtrage explicite avant tout envoi externe)
       ↓
AI Gateway
       ↓
Provider
```

**Principe non négociable** : l'IA **ne reçoit jamais** l'intégralité d'une facture, d'une fiche entreprise ou d'un client - uniquement le contexte d'explication d'un `ComplianceFinding` précis, déjà borné structurellement par le contrat API (`08-api-specification.md`, section 35 : l'endpoint ne prend en paramètre que l'identifiant du finding, pas un contexte libre). Ce principe est une contrainte du contrat lui-même, pas seulement une bonne pratique d'implémentation - elle limite mécaniquement l'ampleur d'une fuite même en cas d'erreur d'implémentation ailleurs dans le système.

## 30. AI Provider Privacy

**Fournisseur retenu : Mistral** (décision produit, `06-technical-architecture.md` ADR-007) - société française, ce qui change favorablement le contexte de cette évaluation par rapport à la version initiale de ce document (qui envisageait un fournisseur non déterminé, potentiellement hors UE). État après vérification de la documentation officielle Mistral au moment de l'implémentation de la Phase 8 (`docs.mistral.ai`) :

- **Utilisation à des fins d'entraînement - vérifié** : la documentation officielle indique que les données transmises via l'API ne sont **pas utilisées par défaut** pour l'entraînement des modèles ; un panneau d'administration (Admin Panel → API → Privacy) expose un bascule d'opt-in explicite si une organisation souhaitait l'autoriser - jamais activé par défaut pour ce produit.
- **Rétention minimisée - option vérifiée existante** : une option "Zero Data Retention" est documentée, désactivant la conservation des données de troubleshooting/analytics côté fournisseur. Reste à confirmer avant mise en production si cette option est activée pour le compte du produit et sa portée exacte (quelles données précisément elle couvre).
- **DPA - existence vérifiée, statut contractuel non tranché ici** : un modèle de Data Processing Addendum est publié par Mistral (`legal.mistral.ai/terms/data-processing-addendum`). Son existence en tant que document type ne vaut pas signature effective pour ce produit - **reste à confirmer avant mise en production** que ce DPA est bien exécuté pour le compte utilisé.
- **Localisation effective du traitement - toujours ouvert** : le siège social en France ne garantit pas à lui seul que chaque requête API est traitée sur un serveur situé en France ou dans l'UE ; la documentation consultée ne fournit pas de garantie explicite de traitement exclusivement UE pour l'API - **à confirmer contractuellement avant mise en production**, non présumé résolu par cette vérification documentaire.
- **Sous-traitants ultérieurs - partiellement documenté** : Mistral publie une liste de sous-traitants (Trust Center) et indique qu'un transfert temporaire hors UE vers l'un de ces sous-traitants est possible selon la fonctionnalité utilisée - à réévaluer précisément pour le périmètre effectivement utilisé par l'AI Gateway (reformulation de texte uniquement, aucune fonctionnalité annexe) avant mise en production.
- **Durée de rétention exacte des requêtes API - toujours ouvert**, non trouvée chiffrée dans la documentation consultée.

**Ce que ce choix simplifie** : un fournisseur français réduit significativement le risque de transfert international hors UE/EEE (section 45) par rapport à l'hypothèse initiale de ce document, sans pour autant le rendre nul par construction - la vérification contractuelle reste nécessaire, notamment sur la localisation effective des serveurs de traitement.

## 31. Prompt Security

- **Séparation instructions système / données utilisateur** : le contenu d'un document, d'un `ComplianceFinding` ou d'une question utilisateur est toujours inséré comme **donnée** dans le prompt, jamais concaténé de façon à pouvoir être interprété comme une instruction système par le modèle.
- **Contenu des documents traité comme non fiable** : cohérent avec la section 28 - aucune confiance implicite accordée au texte extrait d'un document importé.
- **Limitation des outils accessibles au modèle** : l'IA, dans son rôle défini (`06-technical-architecture.md` section 14), n'a accès à **aucun outil d'action** (pas d'appel de fonction lui permettant de modifier une donnée, section 32) - elle produit uniquement du texte de reformulation.
- **Validation des sorties** : voir section 32.
- **Absence d'accès direct à la base de données** : l'IA (le fournisseur externe) n'a, par construction architecturale, aucune capacité de requêter directement la base de données ou tout autre système interne - elle ne reçoit que ce que l'AI Gateway lui transmet explicitement (section 29).
- **Absence d'autorité réglementaire** : rappel du principe déjà posé dans `04-product-requirements.md` (section 17) et `06-technical-architecture.md` (section 14) - jamais contourné, y compris techniquement.

## 32. AI Output Validation

```text
AI Output (texte de reformulation)
   ↓
Validation (format, absence de contenu structuré inattendu)
   ↓
Policy Check (l'IA n'a pas produit un nouveau verdict de conformité, section 28)
   ↓
Frontend (affiché comme reformulation, jamais comme un résultat officiel distinct)
```

**L'IA ne doit jamais pouvoir** : modifier directement une `Invoice` ou un `ComplianceFinding` (aucun canal d'écriture, section 31) ; modifier une `RuleVersion` ; modifier des permissions ou des données de compte ; exécuter une opération sensible sans validation explicite d'un humain. La sortie de l'IA reste, dans l'architecture, un **texte d'affichage**, jamais une commande exécutée automatiquement.

## 33. Audit Trail

Événements devant être audités, cohérent avec `07-data-model.md` (section 20) et complété du point de vue sécurité :

| Événement                                                                               | Catégorie                                           |
| --------------------------------------------------------------------------------------- | --------------------------------------------------- |
| Connexion réussie / échouée                                                             | Sécurité                                            |
| Réinitialisation de mot de passe demandée / effectuée                                   | Sécurité                                            |
| Modification des informations d'entreprise (`FiscalContext` notamment)                  | Métier                                              |
| Création/modification d'une facture ou d'un client                                      | Métier                                              |
| Suppression d'un document                                                               | Métier                                              |
| Déclenchement et résultat d'une analyse de conformité, avec règle et version appliquées | Métier - critique pour la traçabilité réglementaire |
| Création d'une nouvelle `RuleVersion` (API d'administration)                            | Sécurité et métier - action sensible                |
| Téléchargement d'un document                                                            | Sécurité (accès à une donnée sensible)              |
| Action sur l'API d'administration                                                       | Sécurité - accès privilégié                         |
| Appel à l'assistant IA (sans contenu sensible du prompt lui-même, section 35)           | Métier                                              |

## 34. Audit Integrity

Le journal d'audit doit être difficile à falsifier, cohérent avec `07-data-model.md` (section 20, append-only) :

- **Accès restreint** : aucune API utilisateur ne permet de modifier ou supprimer une `AuditLogEntry` - seule la lecture est exposée, filtrée par tenant (`08-api-specification.md`, section 39).
- **Append-only** : appliqué structurellement (aucune opération de mise à jour ou suppression prévue sur cette entité, `07-data-model.md` section 30).
- **Horodatage** : systématique, en UTC (`08-api-specification.md`, section 17).
- **Identification de l'acteur** : `actor_type`/`actor_id` systématiques (`07-data-model.md`, section 20), y compris pour les actions système.
- **Corrélation avec le Request ID** : chaque entrée d'audit peut être reliée à la requête HTTP qui l'a produite via `X-Request-ID` (`08-api-specification.md`, section 49), facilitant l'investigation d'un incident.

## 35. Logging

**Jamais loggés, sous aucune circonstance** : mots de passe (même hashés dans un log applicatif générique), jetons de session en clair, secrets de toute nature, contenu intégral d'un document importé, contenu intégral d'une facture (au-delà des métadonnées nécessaires au diagnostic technique), prompts ou réponses IA contenant des données personnelles non anonymisées au-delà de ce qui est strictement nécessaire au débogage.

**Stratégie de redaction/masking** : tout champ potentiellement sensible (email, SIREN, montants) apparaissant dans un log technique (erreur, trace de débogage) doit être tronqué ou masqué avant écriture, plutôt que loggé intégralement par défaut - un log ne doit jamais devenir, de fait, une seconde base de données non protégée des mêmes contrôles d'accès que la base principale.

## 36. Security Monitoring

Événements à surveiller : taux d'échec de connexion anormalement élevé (indice de brute force) ; volume d'accès refusés (`403`/`404` sur ressources tenant-scoped) anormalement élevé pour un même compte (indice de tentative d'IDOR) ; upload de fichiers en volume ou en fréquence anormale ; erreurs `5xx` en rafale (indice d'incident technique ou d'attaque) ; appels IA en volume anormal (coût et abus, `06-technical-architecture.md` section 15) ; échecs répétés des dépendances externes.

**Bug corrigé (constaté puis résolu en Phase 12, `docs/14-private-beta-plan.md` section 4)** :
`login_throttling` (`security.yaml`, `max_attempts: 5` par tranche de 15 minutes) ne bloquait
aucune tentative de connexion répétée lors d'une vérification manuelle (7 tentatives
consécutives avec des identifiants invalides, sans aucune usurpation d'en-tête), y compris
après correction de la visibilité IP à travers nginx (section 21). Cause racine identifiée :
`Symfony\Component\Security\Http\EventListener\LoginThrottlingListener` était bien enregistré
et levait correctement une `TooManyLoginAttemptsAuthenticationException` (elle-même une
`AuthenticationException`) à la 6e tentative, mais
`App\Shared\Security\AuthFailureEnvelopeListener` écrasait systématiquement toute
`AuthenticationException` reçue via l'événement Lexik `AUTHENTICATION_FAILURE` par un `401`
générique - le rate limiting de connexion était donc actif côté Symfony sans jamais être
opposable en pratique. Corrigé : ce listener distingue désormais explicitement ce cas et
répond `429` avec un en-tête `Retry-After`, sans révéler si l'identifiant ou le mot de passe
est en cause (US-AUTH-002 toujours respecté - un `429` ne signale qu'un volume de tentatives,
jamais une information sur le compte). Test de régression :
`backend/tests/Functional/Auth/LoginControllerTest::testRepeatedFailedLoginsAreThrottledWith429`.

## 37. Alerting

| Niveau   | Exemple                                                                                                    | Réaction                                                      |
| -------- | ---------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| Critique | Violation d'isolation multi-tenant détectée, faille d'authentification exploitée, fuite de secret détectée | Alerte immédiate, traitement en priorité absolue (section 55) |
| Élevé    | Taux d'échec de connexion anormal, volume d'accès refusés anormal                                          | Investigation rapide                                          |
| Moyen    | Erreurs `5xx` en hausse, dépendance externe dégradée                                                       | Surveillance rapprochée                                       |
| Faible   | Erreur isolée, non répétée                                                                                 | Journalisée, pas d'alerte active                              |

**Principe** : ne pas créer d'alerte pour chaque erreur individuelle - seuls les signaux agrégés ou les événements intrinsèquement critiques (section 62) déclenchent une alerte active, pour éviter la fatigue d'alerte particulièrement problématique pour un développeur solo qui doit rester réactif sur les signaux réellement importants.

## 38. Data Retention

| Catégorie                                                                            | Durée                                                                                                                                                                                                                                                 | Raison                                                                                          | Mécanisme                                                                 | Suppression                                                                                 |
| ------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Compte utilisateur et organisation                                                   | Durée de vie du compte                                                                                                                                                                                                                                | Nécessaire au fonctionnement du service                                                         | Conservation active                                                       | Soft delete puis purge différée (délai **à confirmer juridiquement**)                       |
| Factures et lignes analysées (facture originale)                                     | **10 ans**, désormais retenu comme décision produit (`02-regulatory-study.md` section 23, mis à jour ; `07-data-model.md` section 36) - obligation comptable, à distinguer explicitement des données techniques dérivées ci-dessous                   | Obligation légale de conservation de la pièce comptable                                         | Conservation active pendant 10 ans                                        | Non tranchée dans son délai exact de purge après ces 10 ans - **à confirmer juridiquement** |
| Documents importés (fichier original)                                                | Suit la même référence de **10 ans** que la facture qu'il matérialise, sous réserve de la suppression physique du fichier décrite en section 22/24 (le résultat de conformité restant conservé pour la traçabilité même après suppression du fichier) | Idem                                                                                            | Conservation active du fichier tant qu'il n'est pas supprimé à la demande | Suppression possible à la demande (US-DOCUMENT-002), cohérente avec la section 22           |
| Données techniques dérivées (métadonnées de traitement, extraction, logs de parsing) | Durée **propre à leur finalité**, nettement plus courte que celle de la facture originale - durée précise **à confirmer juridiquement/en implémentation**, non alignée par défaut sur les 10 ans                                                      | Finalité technique ponctuelle (diagnostic, débogage), sans justification de conservation longue | Conservation limitée à la finalité                                        | Purge après la finalité atteinte, délai exact à définir                                     |
| Résultats de conformité et snapshots                                                 | Alignés sur la conservation de la facture (10 ans, section ci-dessus), sans excéder cette durée sans nouvelle justification                                                                                                                           | Auditabilité (`04-product-requirements.md`, section 24)                                         | Jamais supprimés activement (`07-data-model.md`, section 30)              | Non applicable                                                                              |
| Journal d'audit                                                                      | Longue durée, non plafonnée a priori                                                                                                                                                                                                                  | Traçabilité technique et de sécurité                                                            | Append-only                                                               | Jamais supprimé activement                                                                  |
| Notifications                                                                        | Courte à moyenne                                                                                                                                                                                                                                      | Utilité limitée dans le temps                                                                   | Conservation active limitée                                               | Purge périodique envisageable                                                               |
| Logs techniques (hors audit)                                                         | Courte à moyenne, à définir                                                                                                                                                                                                                           | Diagnostic opérationnel                                                                         | Rotation                                                                  | Purge automatique après la durée définie                                                    |

**Aucune durée précise n'est inventée ici** : la durée de conservation de 10 ans pour la facture originale reprend la décision produit déjà actée dans `02-regulatory-study.md` (section 23, mis à jour) et `07-data-model.md` (section 36) ; toute autre incertitude non tranchée par ces sources reste marquée « à confirmer juridiquement », en particulier les délais de purge fins des données techniques dérivées et le délai exact de purge après les 10 ans de conservation de la facture elle-même.

## 39. Right to Erasure

Tension à documenter explicitement : le droit à l'effacement (RGPD) peut entrer en tension avec l'obligation de conservation des factures (**10 ans**, désormais retenue comme référence de décision produit, section 38) et avec l'exigence d'auditabilité du produit (`04-product-requirements.md`, section 24).

**Approche retenue, proportionnée, et désormais tranchée sur son principe** : une demande de suppression d'un compte utilisateur entraîne un soft delete immédiat (perte d'accès, section 30 de `07-data-model.md`) ; le fichier original d'un document est supprimé du stockage, mais le `ComplianceEvaluation`/résultat de conformité est conservé lorsqu'il est nécessaire à la traçabilité, avec une indication explicite que le document source a été supprimé (section 22, 24) ; les données dérivées contenant des données personnelles et non nécessaires à cette traçabilité sont supprimées ou anonymisées. La suppression physique des données personnelles d'identification directe (email, informations de contact) peut être effectuée après un délai de purge, **sous réserve** que cela ne supprime pas des données dont la conservation serait légalement requise (facture conservée 10 ans, section 38). **Ce qui reste à valider juridiquement** : le délai de purge exact, et la conformité fine de ce schéma (conservation minimisée du résultat de conformité après suppression du document source) au regard du droit à l'effacement - le principe général est acté, ses modalités précises ne le sont pas.

**Ne jamais promettre à l'utilisateur** que « tout est supprimé immédiatement » tant que cette tension n'est pas juridiquement clarifiée.

## 40. Data Subject Rights

| Droit         | Données concernées                                                    | Processus (esquissé)                                                                                                           | Authentification de la demande                    | Délai                                                                                                 | Exceptions potentielles                                                  |
| ------------- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------- | ----------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| Accès         | Toutes les données personnelles détenues sur la personne              | Export des données du compte, sur demande authentifiée                                                                         | Authentification forte de l'identité du demandeur | **À confirmer juridiquement** (le RGPD fixe un délai de principe - à vérifier plutôt que supposé ici) | Aucune identifiée à ce stade                                             |
| Rectification | Données de compte, d'entreprise, de client                            | Modification directe via l'interface pour les données du compte propre ; processus manuel pour les données d'un tiers (client) | Idem                                              | Idem                                                                                                  | Aucune identifiée                                                        |
| Effacement    | Voir section 39                                                       | Voir section 39                                                                                                                | Idem                                              | Idem                                                                                                  | Conservation légale potentielle (factures - à confirmer)                 |
| Limitation    | Toutes                                                                | **À confirmer juridiquement** - processus non encore défini                                                                    | Idem                                              | Idem                                                                                                  | -                                                                        |
| Opposition    | Traitements fondés sur l'intérêt légitime (le cas échéant, section 9) | **À confirmer juridiquement**                                                                                                  | Idem                                              | Idem                                                                                                  | -                                                                        |
| Portabilité   | Données fournies par la personne elle-même                            | Export dans un format structuré, pertinent notamment pour les données de compte                                                | Idem                                              | Idem                                                                                                  | Peut ne pas s'appliquer à toutes les catégories de données (à confirmer) |

Pour un client particulier (tiers, pas utilisateur du produit) figurant sur une facture, l'exercice de ces droits soulève une question spécifique : **comment authentifier une demande émanant d'une personne qui n'a pas de compte sur le produit**. Décision désormais tranchée sur le processus : **au MVP, ce type de demande est traité manuellement par l'équipe**, avec une procédure de vérification d'identité manuelle sécurisée (pièce justificative ou échange direct permettant de rattacher la demande à la personne concernée, avant toute communication ou action sur ses données) ; une automatisation de ce processus pourra être envisagée plus tard, une fois le volume de telles demandes mieux connu. Cette procédure reste néanmoins **à formaliser précisément et à valider juridiquement** avant une mise en production impliquant des demandes réelles.

## 41. Legal Basis

| Traitement                               | Finalité                                                            | Données                                     | Base légale (indicative)                              | À confirmer |
| ---------------------------------------- | ------------------------------------------------------------------- | ------------------------------------------- | ----------------------------------------------------- | ----------- |
| Création et gestion de compte            | Fourniture du service                                               | Email, mot de passe (hashé)                 | Exécution du contrat (probable)                       | Oui         |
| Vérification de conformité d'une facture | Cœur de la proposition de valeur du produit                         | Données d'entreprise, de client, de facture | Exécution du contrat (probable)                       | Oui         |
| Sécurité et prévention de la fraude      | Protection du service et des utilisateurs                           | Logs de connexion, audit                    | Intérêt légitime (probable)                           | Oui         |
| Reformulation IA d'un résultat           | Amélioration de la compréhension utilisateur                        | Contexte minimisé d'un finding              | Exécution du contrat ou intérêt légitime (à trancher) | Oui         |
| Notifications d'échéance                 | Rappel d'une obligation réglementaire pertinente pour l'utilisateur | Email, diagnostic d'éligibilité             | Exécution du contrat (probable)                       | Oui         |

**Aucune base légale n'est ici déterminée de façon définitive** - chaque ligne appelle une validation juridique avant mise en production, conformément à la consigne de ne jamais fabriquer une base légale RGPD.

**Orientation générale retenue** (pas une décision juridique définitive) : les bases légales probables, tous traitements confondus, sont l'exécution du contrat, les obligations légales applicables (notamment comptables), et - selon le traitement concerné - l'intérêt légitime ou le consentement. Cette orientation **doit être formellement validée juridiquement avant toute formalisation contractuelle** ou politique de confidentialité destinée aux utilisateurs ; elle n'a pas vocation à remplacer cette validation.

## 42. Records of Processing

Éléments à faire figurer dans un futur registre des traitements (non rédigé ici, ce document en prépare la matière) : pour chaque traitement de la section 41 - finalité, catégories de données, catégories de personnes concernées (utilisateurs, clients tiers), destinataires (aucun tiers par défaut, sauf fournisseurs listés section 44), localisation du traitement (à préciser selon les fournisseurs retenus), durée de conservation (section 38, largement à confirmer), mesures de sécurité appliquées (sections 12-32 de ce document).

## 43. Data Processor / Controller

**Qualification indicative, à valider juridiquement** : l'éditeur du produit agit vraisemblablement comme **responsable de traitement** pour les données de ses utilisateurs directs (comptes, données d'entreprise) et potentiellement comme **sous-traitant** pour les données des clients tiers de ses utilisateurs (une facture contenant des informations sur un client relève des propres obligations RGPD de l'utilisateur envers son client). Cette distinction a des implications contractuelles (nécessité potentielle d'un accord de sous-traitance avec les utilisateurs professionnels) qui **doivent être validées juridiquement** avant toute formalisation contractuelle. Les fournisseurs externes retenus (IA, email, stockage) seraient à leur tour des **sous-traitants ultérieurs**, nécessitant leurs propres garanties (section 44).

## 44. External Providers

| Catégorie                                    | Données transmises                                      | Finalité                              | Niveau de confiance requis                                             | DPA                                              | Localisation                                                                                                                                                               | Transfert international                                                                                                    |
| -------------------------------------------- | ------------------------------------------------------- | ------------------------------------- | ---------------------------------------------------------------------- | ------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| Fournisseur IA                               | Contexte minimisé d'un `ComplianceFinding` (section 29) | Reformulation pédagogique             | Élevé - accès à des données potentiellement sensibles, même minimisées | Requis                                           | **Mistral (retenu)** - société française ; localisation exacte des serveurs de traitement à confirmer contractuellement (section 30)                                       | Risque réduit (fournisseur français) mais **à confirmer contractuellement**, pas présumé nul (section 45)                  |
| Fournisseur email                            | Adresse email, contenu du message                       | Notifications, récupération de compte | Moyen                                                                  | Requis                                           | **Brevo (retenu, Phase 18, 27/08/2026)** - société française ; localisation exacte des serveurs de traitement et exécution contractuelle précise du DPA à confirmer avant mise en production, même réserve que pour Mistral (section 30)                                                                                                                                                                | Risque réduit (fournisseur français) mais **à confirmer contractuellement**, pas présumé nul (section 45)                                                                              |
| Stockage documentaire                        | Documents, métadonnées                                  | Conservation des factures importées   | Élevé - données financières et documentaires                           | Sans objet au MVP (stockage local, pas de tiers) | **Stockage local du serveur applicatif** (MVP, `06-technical-architecture.md` ADR-007) - la localisation dépend de l'hébergeur de l'infrastructure elle-même, à documenter | Aucun au MVP tant que le stockage reste local ; à réévaluer lors d'une éventuelle migration vers un stockage objet distant |
| Vérification d'entreprise (V1, non bloquant) | SIREN, informations d'identification                    | Fiabiliser les données saisies        | Moyen                                                                  | Requis si activé                                 | Non tranché                                                                                                                                                                | À évaluer le cas échéant                                                                                                   |
| Intégrations réglementaires (Future Scope)   | Non applicable au MVP                                   | -                                     | -                                                                      | -                                                | -                                                                                                                                                                          | -                                                                                                                          |

**Fournisseur restant à choisir** : vérification d'entreprise. Le fournisseur email est désormais tranché (Brevo, ci-dessus) ; ce tableau pose toujours, pour la vérification d'entreprise, le cadre d'évaluation à appliquer avant intégration, pas une évaluation d'un fournisseur précis.

## 45. International Data Transfers

**Aucun transfert hors UE/EEE n'est engagé au MVP** : le stockage documentaire est local (pas de tiers), et le fournisseur IA retenu (Mistral) est une société française - ce qui réduit significativement, sans l'éliminer par construction, le risque de transfert hors UE/EEE identifié dans la version initiale de ce document. **Pour Mistral comme pour tout futur fournisseur email ou de vérification d'entreprise localisé hors UE/EEE**, les conditions suivantes doivent être vérifiées avant activation :

- Existence d'un mécanisme juridique valide (clauses contractuelles types, décision d'adéquation, ou équivalent) si un transfert hors UE s'avérait effectif malgré la nationalité française de Mistral (par exemple si un sous-traitant technique de Mistral était localisé hors UE) - **à vérifier contractuellement**.
- Mesures supplémentaires éventuellement nécessaires (chiffrement renforcé, minimisation encore accrue) selon le niveau de protection du pays destinataire, si un transfert hors UE devait exister.
- Documentation de cette analyse avant toute mise en production impliquant un transfert avéré.

Ce document ne prétend pas qu'un transfert particulier est légal ou, à l'inverse, qu'il n'existe aucun transfert simplement parce que le fournisseur IA est français : cette évaluation reste **à confirmer contractuellement et juridiquement** avant mise en production.

## 46. DPIA / AIPD

Facteurs à prendre en compte pour évaluer la nécessité d'une analyse d'impact relative à la protection des données (AIPD) : le produit traite des données financières (facteur de sensibilité) ; il n'exerce pas de surveillance systématique à grande échelle des personnes ; il ne réalise pas de scoring ou de décision automatisée ayant un effet juridique sur une personne physique (le résultat de conformité porte sur une facture/entreprise, pas sur une notation individuelle d'une personne physique) ; il utilise l'IA, mais dans un rôle strictement borné à la reformulation, jamais à la décision (sections 28-32) ; le volume de données traitées reste, au MVP, celui d'un produit naissant plutôt qu'à grande échelle.

**Décision désormais tranchée sur le processus, pas sur la conclusion** : un **screening de nécessité d'AIPD est obligatoire avant toute mise en production**. L'AIPD complète n'est réalisée que **si ce screening conclut qu'elle est requise** - ce document ne préjuge pas de ce résultat, mais note que la combinaison de facteurs présents ici (données personnelles, données financières, traitement automatisé, usage de l'IA) rend cette conclusion probable sans pour autant la garantir automatiquement. Ce screening devra être reconduit si le périmètre du produit évolue significativement (par exemple, une future intégration de vérification d'entreprise à grande échelle). **Reste à valider juridiquement** : le résultat effectif du screening et, le cas échéant, le contenu de l'AIPD elle-même.

## 47. Security Headers

| Header                                                | Objectif                                                                                                                                                                                      |
| ----------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Content-Security-Policy`                             | Limite les sources de scripts/styles/ressources exécutables, réduisant l'impact d'une éventuelle injection XSS (section 19)                                                                   |
| `Strict-Transport-Security`                           | Force l'usage de HTTPS pour toute connexion future au domaine, empêchant un downgrade vers HTTP                                                                                               |
| `X-Content-Type-Options: nosniff`                     | Empêche le navigateur de deviner un type de contenu différent de celui déclaré, limitant certains vecteurs d'exploitation de fichiers uploadés servis au navigateur                           |
| `Referrer-Policy`                                     | Limite les informations transmises dans l'en-tête `Referer` lors de la navigation vers un domaine externe, réduisant les fuites d'URL potentiellement sensibles                               |
| `Permissions-Policy`                                  | Désactive les fonctionnalités navigateur non utilisées par le produit (géolocalisation, caméra, etc.), réduisant la surface d'attaque                                                         |
| `X-Frame-Options` ou équivalent CSP `frame-ancestors` | Empêche l'intégration du produit dans un iframe tiers (protection contre le clickjacking), pertinent pour une interface manipulant des actions sensibles (suppression, correction de facture) |

## 48. Dependency Security

- **Dépendances backend et frontend** : scan automatisé des vulnérabilités connues (CVE) intégré au pipeline CI/CD (`09-test-strategy.md`, section 46), avec mise à jour régulière plutôt que ponctuelle.
- **Images Docker** (si conteneurisation retenue, `06-technical-architecture.md` section 32) : scan des images de base et des couches ajoutées.
- **Bibliothèques de parsing (PDF, XML)** : particulièrement surveillées compte tenu de leur exposition directe à des fichiers non fiables (section 22-23) - préférer des bibliothèques activement maintenues plutôt que des projets abandonnés.
- **Dépendances abandonnées** : identifiées périodiquement et remplacées ou isolées si un remplacement n'est pas immédiatement possible.
- **SBOM** (inventaire logiciel) : non jugé indispensable au MVP pour un développeur solo, mais une pratique à considérer si le produit évolue vers un contexte nécessitant une conformité fournisseur plus formelle (Future Scope).

## 49. Secure Development Lifecycle

- **Code review** : même pour un développeur solo, une relecture différée à froid du code touchant au Compliance Engine, à l'autorisation ou aux secrets est recommandée avant fusion (cohérent avec `09-test-strategy.md`, section 42, tests manuels sur les zones sensibles).
- **Secret scanning** : détection automatisée d'un secret accidentellement commité (section 27), intégrée au pipeline CI/CD - `gitleaks/gitleaks-action` (`.github/workflows/lint.yml`, job `secret-scan`), Phase 10.
- **Dependency scanning** : section 48.
- **SAST** (analyse statique) : intégré au pipeline pour détecter les schémas de code à risque (injection, gestion d'erreurs dangereuse) avant l'exécution - `github/codeql-action` (job `codeql`), Phase 10. **Limité au frontend (JavaScript/TypeScript)** : PHP n'est pas un langage supporté par CodeQL (vérifié à l'implémentation, écosystème actuel de l'outil, pas une limitation propre à ce projet). `PHPStan` (`backend/phpstan.neon`, job `backend-lint`) est également intégré au pipeline mais reste une analyse de **qualité et de sûreté de typage**, jamais un SAST sécurité à lui seul - ne jamais présenter "SAST réalisé par PHPStan" dans une communication future sur ce projet. **Aucun SAST sécurité dédié n'existe donc pour le backend PHP à ce stade** - dette technique connue, structurelle à l'écosystème actuel, à réévaluer si un outil mature (ex. analyse par teinture/*taint analysis* dédiée à PHP) apparaît ou si le produit justifie l'investissement d'une solution commerciale.
- **DAST** (analyse dynamique) : pertinent en environnement de staging (`09-test-strategy.md`, section 7), non nécessaire à chaque commit.
- **Branches protégées** : aucune fusion directe vers la branche de production sans passage par le pipeline de tests (`09-test-strategy.md`, section 46) et les release gates (section 62 de ce document).

## 50. Vulnerability Management

| Sévérité | Définition                                                                                                    | Action                                                      |
| -------- | ------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| Critique | Exploitation directe possible d'une faille menant à une compromission de données ou une violation d'isolation | Correction immédiate, **bloque toute release** (section 62) |
| Élevée   | Faille exploitable sous certaines conditions, impact significatif                                             | Correction priorisée, avant la prochaine release            |
| Moyenne  | Impact limité ou conditions d'exploitation complexes                                                          | Planifiée dans un délai raisonnable                         |
| Faible   | Impact mineur                                                                                                 | Traitée au fil de l'eau                                     |

**Processus** : détection (scans automatisés, section 48-49, ou signalement) → classification selon le tableau ci-dessus → correction → vérification (test de non-régression dédié) → suivi jusqu'à clôture.

## 51. Dependency & Supply Chain Security

Pour un développeur solo, une approche proportionnée est retenue plutôt qu'une chaîne d'approvisionnement logicielle exhaustivement contrôlée : utilisation de registries officiels pour les images Docker et packages ; fichiers de verrouillage (`lockfile`) commités pour garantir la reproductibilité des builds ; vérification de la provenance des dépendances directes les plus critiques (bibliothèques de parsing, section 48) ; **pas de vérification de signature cryptographique systématique de chaque dépendance transitive au MVP**, jugée disproportionnée à ce stade - à réévaluer si le produit atteint une échelle justifiant cet investissement.

## 52. Infrastructure Security

Reprise et complément sécurité de `06-technical-architecture.md` (section 30-32) :

- **Réseau** : le reverse proxy est le seul point d'entrée exposé publiquement ; base de données, stockage objet et file de tâches ne sont **jamais** directement accessibles depuis Internet.
- **Firewall / groupes de sécurité** : accès restreint entre composants internes, seuls les flux réellement nécessaires (application → base de données, application → stockage) sont autorisés.
- **Ports** : seuls les ports strictement nécessaires (HTTPS) sont exposés publiquement.
- **Base de données** : accès par identifiants dédiés à l'application, jamais par un compte administrateur générique utilisé au quotidien.
- **Secrets** : section 27.
- **Backups** : section 54.
- **Accès administrateur** : limité au strict nécessaire, avec authentification forte pour tout accès à l'infrastructure elle-même (distinct de l'authentification applicative, section 12).

## 53. Environment Isolation

| Environnement | Isolation                                                                                                                               |
| ------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Local         | Aucune donnée réelle, services externes simulés (`06-technical-architecture.md` section 31)                                             |
| CI            | Données synthétiques jetables, aucun secret de production accessible                                                                    |
| Test          | Données synthétiques, secrets dédiés à l'environnement de test                                                                          |
| Staging       | Données synthétiques réalistes, comptes de test des fournisseurs (jamais les comptes de production), secrets distincts de la production |
| Production    | Données réelles, secrets réels, accès le plus restreint                                                                                 |

**Principe absolu** : un secret ou une credential valide pour un environnement ne doit **jamais** être réutilisé dans un autre - en particulier, aucune credential de production ne doit exister dans un environnement de développement ou de test.

## 54. Backup Security

- **Chiffrement** : sauvegardes chiffrées, cohérent avec la sensibilité des données sauvegardées (section 25).
- **Accès** : restreint, distinct de l'accès aux données actives (une compromission de l'application ne doit pas automatiquement donner accès aux sauvegardes).
- **Rétention** : alignée sur la politique de rétention des données elles-mêmes (section 38), sans excéder cette durée sans justification.
- **Isolation** : sauvegardes stockées séparément de l'infrastructure de production principale, pour survivre à un incident affectant cette dernière.
- **Tests de restauration** : effectués périodiquement (`09-test-strategy.md`, section 37) - une sauvegarde non testée n'est pas une garantie fiable.
- **Protection contre suppression accidentelle** : mécanisme empêchant la suppression immédiate et irréversible d'une sauvegarde récente (délai de grâce ou équivalent, selon les capacités du fournisseur d'infrastructure retenu).

## 55. Incident Response

```text
Détection (monitoring, section 36 ; signalement utilisateur)
   ↓
Triage (gravité, actifs concernés, section 4)
   ↓
Confinement (limiter la propagation - révocation de session, désactivation d'un compte, isolation d'un composant)
   ↓
Éradication (corriger la cause racine)
   ↓
Récupération (restauration si nécessaire, section 54)
   ↓
Post-mortem (documentation, actions correctives, mise à jour de ce document si pertinent)
```

**Adapté à un développeur solo** : pas de structure d'astreinte formelle au MVP, mais un processus documenté permettant de traiter méthodiquement un incident plutôt que dans l'urgence désorganisée - en particulier, un incident critique (violation multi-tenant, fuite de secret) doit interrompre toute autre activité de développement jusqu'à confinement.

## 56. Data Breach

En cas de fuite de données personnelles suspectée :

```text
Détection → Qualification (s'agit-il réellement d'une violation de données personnelles ?) → Évaluation d'impact (quelles données, combien de personnes) → Confinement → Documentation → Notification (si légalement requise) → Communication → Post-mortem
```

**Notification** : le RGPD prévoit des obligations de notification à l'autorité de contrôle et, dans certains cas, aux personnes concernées, dans des délais et selon des critères précis - **ce document ne fixe aucun délai** faute de source officielle vérifiée dans le cadre de cette étude ; **à confirmer juridiquement** le moment venu, avec une consultation de la CNIL ou d'un professionnel du droit si un incident réel survient.

## 57. Security Incident Logging

Pour chaque incident : date et heure, système/composant concerné, utilisateur(s) et tenant(s) potentiellement affectés, nature de l'événement, impact estimé, actions de confinement entreprises, résolution finale, et enseignements pour la prévention future - conservé dans un registre dédié, distinct du journal d'audit métier courant (section 33-34) mais pouvant s'appuyer sur celui-ci comme source d'investigation.

## 58. Business Continuity

Le produit n'assurant pas de mission critique en temps réel (il n'émet ni ne transmet de factures, `06-technical-architecture.md` section 2), une indisponibilité temporaire n'empêche pas l'utilisateur de facturer par ailleurs. Néanmoins : une panne prolongée de la base de données ou du stockage objet interromprait l'accès aux diagnostics et historiques ; une panne du fournisseur IA doit être absorbée sans interruption de service grâce au comportement de repli déjà posé (`06-technical-architecture.md`, section 14-15) ; une panne d'un fournisseur externe non critique (vérification d'entreprise) ne doit jamais bloquer le parcours principal (dégradation gracieuse déjà actée en `06-technical-architecture.md` section 16).

## 59. Disaster Recovery

- **RPO (perte de données maximale acceptable)** et **RTO (temps de reprise maximal acceptable)** : désormais tranchés pour le MVP - **RPO 24h maximum, RTO 24h maximum**. Valeurs raisonnables pour un premier SaaS solo, cohérentes avec la criticité modérée du produit (section 58, aucune mission temps réel critique), à réévaluer selon les attentes commerciales futures si le produit gagne en criticité perçue par les utilisateurs.
- **Restauration** : procédure documentée s'appuyant sur les sauvegardes de la section 54, testée périodiquement (`09-test-strategy.md`, section 37).
- **Dépendances** : la reprise doit couvrir la base de données, le stockage objet, et la cohérence entre les deux (un document sans sa métadonnée, ou l'inverse, serait problématique - repris de `06-technical-architecture.md` section 32).

## 60. Security Testing

S'appuie sur `09-test-strategy.md` (sections 32-33), avec les précisions suivantes propres à ce document :

- **SAST/DAST/Dependency scanning/Container scanning** : intégrés au pipeline (sections 48-49).
- **API security testing** : `09-test-strategy.md` section 20, complété par les contrôles spécifiques de ce document (validation des en-têtes de sécurité, section 47 ; absence de secrets dans les réponses, section 27).
- **Authorization testing / tenant isolation** : `09-test-strategy.md` sections 22-23, **catégorie bloquante** (section 62 de ce document).
- **Upload security testing** : `09-test-strategy.md` section 19, complété par les contrôles XML (section 23) et magic bytes (section 22) de ce document.
- **Données de test** : systématiquement synthétiques (`09-test-strategy.md`, section 10), jamais de données personnelles ou financières réelles en environnement de test, sauf justification exceptionnelle documentée et approuvée.

## 61. Penetration Testing

Un test d'intrusion devient pertinent : **avant la première mise en production commerciale** impliquant des utilisateurs réels et des données réelles ; après tout **changement architectural important** (par exemple, l'introduction d'une gestion de rôles multiples ou d'une intégration avec une plateforme agréée) ; après l'ajout d'une **fonctionnalité critique** touchant à l'authentification, à l'autorisation ou au stockage de documents ; puis **périodiquement** selon les besoins et l'évolution du produit, sans fréquence fixée arbitrairement ici.

**Précision (Phase 15, DEC-010)** : le rôle `PlatformAdministrator` (ADR-009) est exactement le
« changement architectural important » anticipé par la clause ci-dessus, contrairement à la
Phase 11 dont DL-011 (`12-roadmap.md` section 50) avait constaté l'absence pour justifier de ne
pas exiger de pentest avant la Private Beta. **DL-011 ne s'applique donc pas telle quelle à
cette phase** : un test d'intrusion **ciblé sur la surface Platform Administration** (périmètre
détaillé en section 17 bis) est requis **avant l'activation de cette surface**, indépendamment
du pentest complet déjà prévu avant la Phase 17 (mise en production commerciale). Un pentest
ciblé n'a pas vocation à remplacer le pentest complet pré-Phase 17 - il couvre la surface
nouvellement exposée avant sa mise en service, le pentest complet reste requis ensuite sur
l'ensemble du produit.

**Un pentest ne rend jamais l'application « sécurisée » de façon définitive** - il valide un état à un instant donné, sur un périmètre donné, et doit être renouvelé à mesure que le produit évolue.

**Dossier de scope (Phase 17)** : `docs/17-pentest-scope.md` détaille les deux périmètres
ci-dessus (Scope A ciblé Platform Admin, Scope B produit complet) pour un prestataire
externe, et `docs/15-internal-security-review.md` documente la revue de sécurité interne
menée en préparation - explicitement non substituable à ce pentest.

**Décision finale (27/08/2026)** : aucun des deux pentests ne sera réalisé, faute de
budget et de disponibilité - voir `docs/17-pentest-scope.md` pour la décision complète et
sa conséquence directe sur l'activation de la surface Platform Administration (risque
accepté explicitement, jamais un défaut silencieux).

## 62. Security Release Gates

```text
Vulnérabilité critique (section 50)                    → BLOQUANT
Échec d'un test d'isolation multi-tenant                → BLOQUANT
Contournement d'authentification démontré                → BLOQUANT
Exposition d'un secret (dans le code, les logs, l'API)   → BLOQUANT
Fuite de donnée de conformité entre tenants               → BLOQUANT
Non-déterminisme détecté dans le Compliance Engine        → BLOQUANT (repris de 09-test-strategy.md §45)
Vulnérabilité élevée non critique                          → non bloquant, correction priorisée avant la release suivante
```

Cette liste complète et resserre les release gates déjà posés dans `09-test-strategy.md` (section 45) sur leur volet strictement sécurité.

## 63. Security Requirements

**SEC-AUTH-001** - Les mots de passe sont hashés avec un algorithme adapté et salés individuellement. Priorité P0. Contrôle : section 13. Test associé : `09-test-strategy.md` section 24.

**SEC-AUTH-002** - Toute tentative de connexion échouée est limitée en débit pour prévenir le brute force. Priorité P0. Contrôle : section 13. Test : `09-test-strategy.md` section 24, 32.

**SEC-AUTHZ-001** - Toute action sur une ressource tenant-scoped vérifie systématiquement l'appartenance de la ressource au tenant de l'appelant. Priorité P0. Contrôle : sections 15-17. Test : `09-test-strategy.md` section 22 (bloquant).

**SEC-TENANT-001** - Le tenant courant est résolu depuis la session authentifiée, jamais depuis un paramètre de requête fourni par le client. Priorité P0. Contrôle : section 16 ; hérité de `08-api-specification.md` section 9. Test : `09-test-strategy.md` section 22.

**SEC-DOC-001** - Tout fichier importé est validé par inspection de son contenu réel (magic bytes) avant tout traitement, indépendamment de l'extension ou du MIME type déclaré. Priorité P0. Contrôle : section 22. Test : `09-test-strategy.md` section 18-19.

**SEC-DOC-002** - Tout parseur XML désactive le traitement des entités externes et des DTD. Priorité P0. Contrôle : section 23. Test : `09-test-strategy.md` section 18.

**SEC-AI-001** - L'assistant IA ne reçoit jamais plus que le contexte minimisé d'un `ComplianceFinding` précis, jamais une facture ou une organisation entière. Priorité P0. Contrôle : section 29. Test : `09-test-strategy.md` section 29.

**SEC-AI-002** - Aucune sortie de l'assistant IA ne peut modifier directement une donnée métier (facture, règle, permission). Priorité P0. Contrôle : section 32. Test : `09-test-strategy.md` section 29.

**SEC-API-001** - Le contrat d'erreur API ne révèle jamais de détail d'implémentation interne. Priorité P1. Contrôle : section 18. Test : `09-test-strategy.md` section 20.

**SEC-AUDIT-001** - Toute analyse de conformité produite est enregistrée de façon immuable avec la règle et la version exactes appliquées. Priorité P0. Contrôle : section 33-34 ; hérité de `07-data-model.md` ADR-003. Test : `09-test-strategy.md` sections 11-12 (bloquant).

**SEC-DATA-001** - Aucun secret n'est stocké en clair, ni dans le code source, ni dans une réponse API, ni dans un log. Priorité P0. Contrôle : sections 27, 35. Test : `09-test-strategy.md` section 32.

## 64. Privacy Requirements

**PRIVACY-001** - Aucune donnée n'est collectée au-delà de ce qui est strictement nécessaire à une fonctionnalité tracée dans `04-product-requirements.md` ou `05-user-stories.md`. Contrôle : section 10.

**PRIVACY-002** - Le contexte transmis à tout fournisseur externe (en particulier l'IA) est minimisé avant l'envoi. Contrôle : sections 10, 29.

**PRIVACY-003** - Toute demande d'accès, de rectification ou de suppression émanant d'un utilisateur authentifié est traitée selon le processus de la section 40, avec authentification de la demande.

**PRIVACY-004** - Une demande de suppression ne doit jamais être présentée comme une suppression totale et immédiate tant que la tension avec une éventuelle obligation de conservation légale n'est pas clarifiée. Contrôle : section 39.

**PRIVACY-005** - Tout transfert de données hors UE/EEE fait l'objet d'une vérification explicite du mécanisme juridique applicable avant activation. Contrôle : section 45.

## 65. Security & Privacy Traceability

| Requirement    | User Story                                         | Contrôle        | Test                                              | Release Gate                                  |
| -------------- | -------------------------------------------------- | --------------- | ------------------------------------------------- | --------------------------------------------- |
| SEC-AUTHZ-001  | US multi-tenant transverses (`05-user-stories.md`) | Sections 15-17  | `09-test-strategy.md` TC-TENANT-001               | Bloquant                                      |
| SEC-TENANT-001 | Idem                                               | Section 16      | TC-TENANT-001                                     | Bloquant                                      |
| SEC-DOC-001    | US-INVOICE-001                                     | Section 22      | TC-DOCUMENT-002                                   | Bloquant si vulnérabilité critique            |
| SEC-AI-001     | US-AI-001/002                                      | Section 29      | TC-AI-001                                         | Bloquant si dépassement de périmètre confirmé |
| SEC-AUDIT-001  | US-HISTORY-001                                     | Sections 33-34  | TC-COMPLIANCE-004/005                             | Bloquant                                      |
| SEC-DATA-001   | Transverse                                         | Sections 27, 35 | Section 32 de `09-test-strategy.md`               | Bloquant                                      |
| PRIVACY-001    | Transverse                                         | Section 10      | Revue manuelle à chaque nouvelle donnée collectée | Non bloquant automatiquement, revue requise   |

Cette matrice, complète pour les exigences critiques (P0), illustre la structure attendue ; elle doit être tenue à jour au fil de l'implémentation, à l'image de la matrice équivalente de `09-test-strategy.md` (section 51).

## 66. Security Risk Register

| ID    | Risque                                                         | Actif                               | Probabilité                                       | Impact                                       | Niveau                                   | Mitigation                      |
| ----- | -------------------------------------------------------------- | ----------------------------------- | ------------------------------------------------- | -------------------------------------------- | ---------------------------------------- | ------------------------------- |
| SR-01 | Violation d'isolation multi-tenant                             | Toutes les données tenant-scoped    | Low si les contrôles sont respectés, High sinon   | Critical                                     | **Critical**                             | Sections 16-17, tests bloquants |
| SR-02 | Fuite de secret (code, log, API)                               | Secrets d'intégration, jetons       | Low                                               | Critical                                     | **High**                                 | Sections 27, 35                 |
| SR-03 | Contournement d'authentification                               | Comptes utilisateurs                | Low                                               | Critical                                     | **High**                                 | Sections 12-14                  |
| SR-04 | Injection XXE via un document Factur-X/UBL/CII malveillant     | Serveur de traitement documentaire  | Medium sans contrôle dédié, Low avec              | High                                         | **High**                                 | Section 23                      |
| SR-05 | Prompt injection via un document ou une question utilisateur   | Intégrité de la reformulation IA    | Medium                                            | Medium (l'IA n'a pas d'autorité, section 28) | **Medium**                               | Sections 28-32                  |
| SR-06 | Fuite de contexte excessif vers le fournisseur IA              | Données personnelles/financières    | Low si le contrat API est respecté                | High                                         | **Medium**                               | Section 29                      |
| SR-07 | Compromission d'un fournisseur externe (email, stockage)       | Données transmises à ce fournisseur | Low à Medium                                      | Medium à High selon le fournisseur           | **Medium**                               | Section 44                      |
| SR-08 | Altération non détectée d'une `RuleVersion`                    | Résultats de conformité             | Low (structurellement empêché par l'immutabilité) | Critical                                     | **Medium** (résiduel malgré le contrôle) | Section 33-34, ADR-003          |
| SR-09 | Upload de fichier malveillant exploitant une faille de parsing | Serveur de traitement documentaire  | Medium                                            | Medium à High                                | **Medium**                               | Section 22-23                   |

## 67. Privacy Risk Register

| ID    | Risque                                                                                                                                                                                                               | Probabilité                                                                                                    | Impact        | Niveau            | Mitigation                                                                               |
| ----- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- | ------------- | ----------------- | ---------------------------------------------------------------------------------------- |
| PR-01 | Fuite de données personnelles de clients tiers via une compromission applicative                                                                                                                                     | Low si SR-01/02/03 mitigés                                                                                     | High          | **Medium à High** | Sections 16-17, 25, 27                                                                   |
| PR-02 | Collecte excessive de données non justifiée par une exigence produit                                                                                                                                                 | Medium sans discipline                                                                                         | Medium        | **Medium**        | Section 10, revue à chaque nouvelle collecte                                             |
| PR-03 | Conservation excessive au-delà du nécessaire, notamment sur les données techniques dérivées dont la durée propre reste à préciser (la durée de la facture originale, elle, est désormais fixée à 10 ans, section 38) | Medium, réduite par la fixation de la durée de la facture elle-même, mais persistante sur les données dérivées | Medium        | **Medium**        | Section 38 ; délais fins des données dérivées à trancher juridiquement/en implémentation |
| PR-04 | Transfert international non conforme (fournisseur hors UE)                                                                                                                                                           | Medium selon le fournisseur retenu                                                                             | Medium à High | **Medium**        | Section 45                                                                               |
| PR-05 | Accès non autorisé aux données d'un client tiers via une mauvaise gestion des droits de la personne concernée                                                                                                        | Low                                                                                                            | Medium        | **Low à Medium**  | Section 40                                                                               |
| PR-06 | Sous-traitant (fournisseur IA notamment) sans garanties contractuelles suffisantes                                                                                                                                   | Medium tant qu'aucun fournisseur n'est validé                                                                  | Medium à High | **Medium**        | Sections 30, 44                                                                          |
| PR-07 | Mauvaise suppression : donnée supposée effacée mais restée accessible via un snapshot ou une sauvegarde                                                                                                              | Low à Medium                                                                                                   | Medium        | **Medium**        | Sections 39, 54                                                                          |

## 68. Production Security Checklist

Revue en Phase 10 (docs/12-roadmap.md) : chaque case cochée porte une preuve (fichier/test),
jamais cochée par anticipation. Les points qui dépendent d'un hébergement réel - non choisi
à ce stade, l'environnement actuel restant Docker Compose local + CI - sont marqués
`DIFFÉRÉ - Phase 17 - nécessite une infrastructure hébergée` plutôt que cochés ou
silencieusement retirés (une checklist entièrement cochée ici serait un signal d'alarme, pas
une garantie).

**Application**

- [x] Authentification et hashing des mots de passe conformes (section 13) - Argon2id (`auto`, `backend/config/packages/security.yaml`), `login_throttling` (5/15min) ; Phase 2, `backend/tests/Functional/Auth/`
- [x] Autorisation vérifiée côté serveur sur chaque endpoint tenant-scoped (sections 15-17) - `App\Shared\Security\CurrentOrganizationResolver` + `App\Shared\Doctrine\TenantFilter`, centralisés ; `backend/tests/Integration/MultiTenant/TenantIsolationTest.php` (TC-TENANT-001 à 008, étendu Phase 10) + scénarios cross-tenant par ressource (Document, Dashboard, Historique, IA, Audit)
- [x] Validation stricte de toute entrée utilisateur (section 19) - Symfony Validator sur chaque DTO d'entrée, `422` testé par endpoint
- [x] Tests d'isolation multi-tenant passés et bloquants (section 62) - `TenantIsolationTest` (8 scénarios), job CI `backend-lint` bloquant sur `php bin/phpunit`

**API**

- [x] HTTPS forcé sur l'ensemble des endpoints (section 25) - **exécuté réellement (27/08/2026)** : Traefik en production avec certificats TLS réels (Let's Encrypt, renouvellement automatique) pour `factusentinel.joeltech.fr` et `factusentinel-staging.joeltech.fr` (`12-roadmap.md` §43), `docker/nginx/prod.conf.template` ne sert plus qu'en HTTP interne (pont FastCGI vers `backend`). Point restant, non vérifié à ce jour : `HSTS_ENABLED` est toujours `false` dans `backend/.env`/`.env.prod.example` - Traefik termine le HTTPS et redirige déjà HTTP→HTTPS au niveau du reverse proxy, mais l'en-tête applicatif `Strict-Transport-Security` (`App\Shared\Http\HstsHeaderListener`) n'a pas été confirmé actif sur le serveur réel ; à vérifier avant de considérer ce point clos
- [x] Politique CORS restrictive appliquée (section 21) - `backend/config/packages/nelmio_cors.yaml` (`CORS_ALLOW_ORIGIN` par environnement, jamais de wildcard), en-têtes métier complétés Phase 10 (`Idempotency-Key`, `If-Match`, `X-Request-ID`)
- [x] Rate limiting actif sur l'authentification et les opérations coûteuses (section 18) - `login_throttling`, `password_reset_request`, `ai_assistant` (Phases 2/8) + `compliance_analysis_trigger`, `document_upload` (Phase 10) - `backend/config/packages/rate_limiter.yaml`, tests `testRateLimitReturns429AfterExhaustingLimiter` par endpoint
- [x] Contrat d'erreur ne révélant aucun détail technique interne (section 18) - `App\Shared\Http\ApiExceptionListener`, revu Phase 10, aucune régression trouvée
- [x] En-têtes de sécurité configurés (section 47) - `App\Shared\Http\SecurityHeadersListener` (backend) + `frontend/next.config.ts` (frontend) - Phase 10, `backend/tests/Functional/Shared/SecurityHeadersTest.php`

**Documents**

- [x] Validation par contenu réel (magic bytes), pas seulement extension/MIME (section 22) - `App\Document\Service\UploadedDocumentValidator` ; Phase 7
- [x] Parseurs XML configurés contre XXE (section 23) - Validator Container Mustang isolé (ADR-008) ; Phase 7
- [x] URLs de téléchargement temporaires et non prévisibles (section 24) - `GET /documents/{id}/content` authentifié à chaque appel, jamais d'URL publique (stockage local du MVP) ; Phase 7
- [x] Antivirus activé sur les fichiers uploadés (Phase 17) - `App\Document\Service\ClamAvScanner`, scan avant toute écriture sur `StorageInterface`, conteneur ClamAV isolé (`06-technical-architecture.md` section 30) ; contrairement à l'attente initiale (`DIFFÉRÉ - nécessite une infrastructure hébergée`), ne dépendait pas réellement d'un hébergeur - fonctionne en local/CI comme en production. Détection réelle vérifiée contre le vrai service (`backend/tests/Integration/Document/ClamAvScannerTest.php`, signature EICAR) et non-persistance d'un contenu signalé vérifiée au niveau du pipeline (`backend/tests/Functional/Document/CreateDocumentControllerTest.php::testInfectedUploadIsRejectedAndNeverPersisted`)

**Données**

- [x] Chiffrement en transit systématique (section 25) - **exécuté réellement (27/08/2026)** : TLS réel via Traefik/Let's Encrypt sur les deux environnements (voir HTTPS forcé ci-dessus, `12-roadmap.md` §43)
- [ ] Chiffrement au repos activé (base de données, stockage) (section 25) - `DIFFÉRÉ - Bloc B - dépend de l'option de chiffrement disque de l'offre OVHcloud retenue, à vérifier au moment du provisionnement`
- [x] Politique de rétention documentée, même si certaines durées restent « à confirmer juridiquement » (section 38) - déjà documentée section 38, incertitudes juridiques explicitement signalées, pas un point bloquant pour cette case
- [x] Sauvegardes chiffrées et testées (sections 37, 54) - `docker/backup/backup.sh`/`restore.sh` (gpg AES256, clé jamais stockée avec l'archive) - Phase 10, restauration testée manuellement avec vérification de cohérence croisée `Document` ↔ fichier ↔ `DocumentProcessingRecord` (voir `docker/backup/README.md`). **Phase 17** : automatisation périodique ajoutée (`docker/backup/automated-backup.sh` - cron/systemd, envoi vers un stockage objet distant compatible S3, rétention), et un défaut corrigé au passage - les deux scripts lisaient/écrivaient directement `backend/var/storage/documents` sur l'hôte, ce qui ne fonctionne plus tel quel en production (`docker-compose.prod.yml` porte ce chemin via un volume Docker nommé) ; passent désormais par `docker compose exec backend`, valable dans les deux environnements. **Exécuté réellement en production (27/08/2026)** : sauvegarde quotidienne active vers Cloudflare R2 (juridiction UE), restauration testée de bout en bout avec vérification croisée réelle, pas seulement une absence d'erreur (`12-roadmap.md` §43).

**IA**

- [x] Contexte transmis strictement minimisé (section 29) - Phase 8
- [x] Fournisseur IA évalué selon la grille de la section 30 avant activation - Mistral, Phase 8 (points contractuels résiduels notés section 30, non bloquants au MVP)
- [x] Comportement de repli fonctionnel en cas d'indisponibilité du fournisseur (section 28) - Phase 8
- [x] Protections contre le prompt injection en place (section 31) - Phase 8

**Infrastructure**

- [x] Aucun secret dans le code source ou les images de conteneur (sections 26-27) - `backend/.env` committé sans valeur réelle, secrets via `.env.local`/variables CI ; scan automatisé ajouté Phase 10 (`gitleaks/gitleaks-action`, `.github/workflows/lint.yml`)
- [x] Réseau interne non exposé directement à Internet (section 52) - `docker-compose.yml` : PostgreSQL/Redis liés à `127.0.0.1`, Mustang sans port publié, Nginx seul point d'entrée ; déjà vrai depuis la Phase 0-1
- [x] Environnements strictement isolés (section 53) - **exécuté réellement (27/08/2026)** : VPS OVHcloud provisionné, staging (`factusentinel-staging.joeltech.fr`) et production (`factusentinel.joeltech.fr`) avec bases de données, volumes et réseaux Docker distincts (`12-roadmap.md` §43), secrets GitHub Environments distincts par environnement (`docker/deploy/README.md`)
- [x] Monitoring et alerting actifs sur les événements critiques (sections 36-37) - **exécuté réellement (27/08/2026)** : 10 sondes Uptime Kuma + 1 sonde push de sauvegarde, actives et configurées sur les deux environnements, avec notifications Telegram (`12-roadmap.md` §43, `docker/monitoring/README.md`) ; connectivité Redis/Mustang ajoutée à `GET /platform-admin/health`

**RGPD**

Hors périmètre de la Phase 10 (points juridiques, non techniques - voir section 69) :

- [x] Cartographie des données personnelles tenue à jour (section 9) - à jour, complétée par le registre détaillé de `docs/16-rgpd-compliance-dossier.md` (Phase 17)
- [ ] Bases légales validées juridiquement (section 41) - orientations indicatives préparées (`docs/16-rgpd-compliance-dossier.md` §1), **validation juridique restant entièrement à faire**
- [x] Registre des traitements initié (section 42) - `docs/16-rgpd-compliance-dossier.md` §1 (Phase 17), sept traitements détaillés (finalité, données, sous-traitants, transferts, durée, mesures, base légale à valider) - "initié" au sens littéral de cette case, jamais présenté comme validé juridiquement
- [ ] Qualification responsable/sous-traitant validée juridiquement (section 43) - orientation préparée (`docs/16-rgpd-compliance-dossier.md` §3), **validation juridique restant entièrement à faire**
- [ ] DPA en place avec chaque fournisseur externe traitant des données personnelles (section 44) - dossier Mistral préparé avec les points précis à vérifier contractuellement (`docs/16-rgpd-compliance-dossier.md` §2, DPA public déjà lu, écarts identifiés) ; fournisseurs email/vérification d'entreprise toujours non choisis
- [ ] Analyse des transferts internationaux effectuée si applicable (section 45) - le DPA public de Mistral autorise des transferts sous conditions (`docs/16-rgpd-compliance-dossier.md` §2.3) - **contrairement à l'hypothèse précédente de cette section, ce n'est pas une absence de transfert acquise** ; vérification contractuelle réelle restant à faire
- [ ] Screening de nécessité d'AIPD réalisé ; AIPD complète menée si le screening la requiert (section 46) - grille des 9 critères officiels CNIL/G29 appliquée (`docs/16-rgpd-compliance-dossier.md` §4), conclusion **provisoire** (AIPD complète non clairement requise en l'état) explicitement marquée comme non officielle - **validation par toi et/ou un juriste restant à faire avant mise en production**, cette case reste décochée tant qu'elle ne l'est pas
- [ ] Processus de traitement des demandes de droits des personnes opérationnel, y compris la procédure de vérification manuelle sécurisée pour les demandes de tiers non-utilisateurs (section 40)

## 69. Questions ouvertes

### Décisions actées (2026)

Les points suivants, précédemment signalés comme questions ouvertes dans une version antérieure de ce document, ont été tranchés par une décision produit. Ils sont repris ici avec leur formulation de référence ; **les points strictement juridiques listés dans la seconde partie de cette section restent, eux, explicitement non tranchés.**

| Question initiale                                                                                              | Décision retenue                                                                                                                                                                                                               | Statut                                                                                                                                                       |
| -------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Nécessité d'un antivirus sur les documents uploadés (section 22)                                               | Non indispensable pour le MVP en environnement local/dev (validation MIME, magic bytes, taille, parseur sécurisé suffisent) ; **requis avant une mise en production réelle**, ces deux seuils ne devant jamais être confondus. | Résolu - décision produit, par stade                                                                                                                         |
| RPO/RTO (section 59)                                                                                           | **24h maximum / 24h maximum** pour le MVP, à réévaluer selon les attentes commerciales futures.                                                                                                                                | Résolu - décision produit pour le MVP                                                                                                                        |
| Screening/nécessité d'une AIPD (section 46)                                                                    | **Screening de nécessité obligatoire avant mise en production** ; AIPD complète réalisée seulement si ce screening conclut qu'elle est requise.                                                                                | Résolu sur le **principe et le processus** - la conclusion du screening lui-même (AIPD complète nécessaire ou non) reste ouverte par nature, voir ci-dessous |
| Authentification d'une demande de droit RGPD émanant d'un client tiers non utilisateur du produit (section 40) | **Au MVP : traitement manuel, avec vérification d'identité sécurisée assurée par l'équipe.** Automatisation envisageable plus tard.                                                                                            | Résolu sur le principe pour le MVP ; formalisation précise de la procédure et validation juridique encore à faire                                            |
| Durée de conservation de la facture originale (section 38)                                                     | **10 ans**, reprise de la décision produit déjà actée dans `02-regulatory-study.md` (section 23, mis à jour).                                                                                                                  | Résolu - décision produit ; durées fines des données dérivées et délai de purge exact restent ouverts (voir ci-dessous)                                      |

### Restent explicitement ouvertes (points juridiques et fournisseurs non tranchés)

**Voir `docs/16-rgpd-compliance-dossier.md` (Phase 17)** pour le registre des traitements
détaillé, le dossier fournisseur Mistral (points précis vérifiés dans son DPA public au
26/08/2026, notamment sur les transferts internationaux et l'entraînement de modèles) et
le screening AIPD appliqué aux 9 critères CNIL/G29 - ce document en prépare la matière,
il ne tranche aucune des questions listées ci-dessous.

| Question                                                                                                                                                                            | Pourquoi elle est importante                                                                                                                                                            | Où la trancher                                                                       |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Paramètres précis du JWT (durées exactes access/refresh, bibliothèque Symfony retenue) - mécanisme lui-même désormais confirmé (section 12, `06-technical-architecture.md` ADR-007) | Conditionne le calibrage fin de la sécurité de session                                                                                                                                  | Décision d'implémentation                                                            |
| Délai de purge exact après les 10 ans de conservation de la facture, et durées de conservation fines des données techniques dérivées (section 38)                                   | `02-regulatory-study.md` fixe la durée de la facture elle-même mais pas ces modalités fines                                                                                             | Validation juridique / décision d'implémentation                                     |
| Base légale précise de chaque traitement (section 41)                                                                                                                               | Nécessaire avant toute formalisation d'une politique de confidentialité destinée aux utilisateurs - seule une orientation générale est posée (section 41), jamais une base légale actée | Validation juridique                                                                 |
| Qualification responsable de traitement / sous-traitant (section 43)                                                                                                                | Détermine les obligations contractuelles envers les utilisateurs professionnels - seule une orientation générale est posée (section 43), jamais une qualification actée                 | Validation juridique                                                                 |
| Conclusion effective du screening AIPD, et contenu de l'AIPD complète si elle s'avère requise (section 46)                                                                          | Détermine une obligation procédurale potentiellement contraignante ; le processus est tranché, pas son résultat                                                                         | Screening puis, le cas échéant, AIPD, avant mise en production                       |
| Formalisation juridique précise de la procédure de vérification d'identité pour les demandes RGPD de tiers non-utilisateurs (section 40)                                            | Le principe (traitement manuel au MVP) est acté, sa mise en œuvre précise doit rester conforme au RGPD                                                                                  | Validation juridique avant qu'une demande réelle ne survienne                        |
| Localisation exacte des serveurs de traitement Mistral et conditions contractuelles précises (DPA, non-entraînement) - fournisseur lui-même désormais choisi (section 30)           | Conditionne la conclusion définitive sur l'absence de transfert hors UE (section 45)                                                                                                    | Vérification contractuelle avant mise en production                                  |
| Fournisseur de vérification d'entreprise encore non choisi (section 44-45) - le fournisseur email est désormais tranché (Brevo, Phase 18, 27/08/2026)                              | Conditionne l'évaluation de transfert international et de sous-traitance pour cette catégorie restante                                                                                  | `04-product-requirements.md` section 32, décision à prendre avant mise en production |
| Localisation exacte des serveurs de traitement Brevo et exécution contractuelle précise du DPA (sections 44-45) - fournisseur lui-même désormais choisi                            | Conditionne la conclusion définitive sur l'absence de transfert hors UE pour cette catégorie, même réserve que pour Mistral                                                             | Vérification contractuelle avant mise en production                                 |

## 70. Impact sur le Frontend Design System

## Informations nécessaires au Frontend Design System

À l'attention de `11-frontend-design-system.md`, sans rédiger le design lui-même :

- **Affichage des erreurs** - distinction visuelle systématique entre une erreur technique (section 18 de ce document, section 46 de `08-api-specification.md`) et un résultat de conformité `NON_CONFORME`, ce dernier n'étant jamais présenté comme un échec du système.
- **Messages de validation** - les erreurs de validation (SIREN manquant, montant incohérent) doivent être affichées au niveau du champ concerné, en cohérence avec le format `field`/`issue` du contrat API.
- **États d'accès refusé** - un `404` masquant une ressource d'un autre tenant (section 17) doit être présenté à l'utilisateur comme une ressource introuvable, jamais comme une erreur de permission qui laisserait deviner l'existence de la ressource ailleurs.
- **Sessions expirées** - redirection claire vers la reconnexion, sans perte brutale du travail en cours si possible (sauvegarde de brouillon à envisager pour la saisie de facture, non tranché ici).
- **Confirmation des actions sensibles** - suppression d'un document (section 22, 39), suppression de compte (section 39-40) : une confirmation explicite doit être demandée, avec une explication claire de ce qui est réellement supprimé et de ce qui est conservé (cohérent avec l'interdiction de promettre une suppression totale non garantie, section 39).
- **Upload** - retour visuel clair sur les limites de taille et de format acceptés (section 22), et sur les erreurs de validation associées, distinctes d'un problème de conformité de la facture elle-même.
- **Avertissements de confidentialité** - si le produit affiche un jour explicitement qu'une donnée sera transmise à l'assistant IA, cette transmission (déjà minimisée par construction, section 29) devrait être mentionnée de façon transparente plutôt que silencieuse.
- **Affichage des données sensibles** - éviter d'afficher des données très sensibles (aucune identifiée au niveau interface utilisateur au-delà de ce que l'utilisateur a lui-même saisi) sans nécessité, et prévoir un mécanisme de masquage si des informations comme un SIREN complet devaient être jugées sensibles dans un contexte de partage d'écran (non tranché, à évaluer).
- **Consentements** - si un consentement explicite s'avère nécessaire pour un traitement donné (dépendant des bases légales à confirmer, section 41), l'interface doit prévoir un mécanisme de recueil clair et non pré-coché.
- **Notifications de sécurité** - information claire à l'utilisateur en cas d'événement de sécurité le concernant (réinitialisation de mot de passe, connexion depuis un nouveau contexte si cette détection est implémentée).
- **Accessibilité des messages d'erreur** - cohérent avec `09-test-strategy.md` (section 39) : les messages d'erreur et de refus d'accès doivent rester perceptibles et compréhensibles via un lecteur d'écran, pas seulement par une couleur ou une icône.
