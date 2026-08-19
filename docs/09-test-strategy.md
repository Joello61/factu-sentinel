# Test Strategy — Assistant de conformité à la facturation électronique

> Ce document définit la stratégie et l'organisation des tests, à partir de `01-intent-note.md` à `08-api-specification.md`. Il ne contient ni suite de tests complète, ni code, ni stratégie de sécurité détaillée (`10-security-privacy.md`). Chaque catégorie de test est justifiée par une exigence déjà actée dans les documents précédents ; aucune nouvelle exigence produit ou obligation réglementaire n'est introduite ici.

## 1. Introduction

Ce produit doit être testé comme un **système de conformité**, pas seulement comme une application web. Une fonctionnalité peut être techniquement irréprochable (API répond, facture enregistrée) tout en étant fonctionnellement incorrecte si le Compliance Engine applique la mauvaise règle — ce cas doit être traité comme une **défaillance critique**, au même titre qu'une fuite de données cross-tenant, jamais comme un simple bug fonctionnel mineur. Cette priorité structure l'ensemble du document : exactitude des règles, versionnement, traçabilité, déterminisme et reproductibilité reçoivent une attention systématiquement supérieure à celle d'une application CRUD classique.

## 2. Quality Objectives

| Objectif | Signification pour ce produit |
|---|---|
| **Correctness** | Le système produit le bon résultat — en particulier, le bon résultat de conformité, pas seulement une réponse HTTP valide |
| **Reliability** | Le comportement du système est prévisible dans le temps, y compris pour les traitements asynchrones (`06-technical-architecture.md`, section 12-13) |
| **Security** | Les données financières et personnelles sont protégées, en particulier l'isolation multi-tenant (`06-technical-architecture.md`, section 20) |
| **Compliance correctness** | Les règles réglementaires applicables (`02-regulatory-study.md`) sont correctement sélectionnées et évaluées — objectif spécifique à ce produit, distinct de la correction fonctionnelle générale |
| **Auditability** | Toute décision de conformité peut être expliquée et reconstruite a posteriori (`04-product-requirements.md`, section 24 ; `07-data-model.md`, section 19-20) |
| **Performance** | Le système reste suffisamment réactif pour ne pas dégrader l'expérience de vérification (`06-technical-architecture.md`, section 23) |
| **Maintainability** | Un changement réglementaire peut être intégré sans casser le comportement historique du système (`07-data-model.md`, ADR-003 immutabilité) |
| **Usability** | Un résultat de conformité, y compris une erreur, reste compréhensible par un non-spécialiste (`04-product-requirements.md`, section 15) |
| **Compatibility** | Le système fonctionne dans les environnements réellement utilisés par la cible (section 40) |

## 3. Test Scope

**In Scope** : le Compliance Engine et ses règles versionnées ; l'API définie dans `08-api-specification.md` ; les parcours utilisateurs P0/P1 de `05-user-stories.md` ; l'isolation multi-tenant ; l'authentification et l'autorisation ; le traitement de documents (import, extraction, distinction PDF/facture structurée) ; les traitements asynchrones ; la couche IA d'explication (dans son rôle strictement défini) ; la sécurité applicative de premier niveau ; les performances des opérations critiques.

**Out of Scope (pour cette version de la stratégie)** : tout ce qui relève des fonctionnalités explicitement hors périmètre du PRD (`04-product-requirements.md`, section 30) — émission/transmission réelle de factures, comptabilité, paie, CRM, paiement ; les tests de charge à grande échelle non justifiés par le volume attendu (`06-technical-architecture.md`, section 33) ; les tests d'intégration avec une plateforme agréée ou un outil de validation Factur-X tiers, aucune intégration de ce type n'étant active au MVP (`08-api-specification.md`, section 36).

**Future Scope** : tests de gestion de rôles multiples (persona secondaire C) ; tests d'intégrations externes lorsqu'elles seront activées ; tests de charge à volume réel une fois des données de production disponibles.

## 4. Test Principles

- **Un test de conformité incorrect est plus grave qu'un test fonctionnel manquant** — priorité de traitement inverse à ce qui serait usuel dans un produit sans dimension réglementaire.
- **Reproductibilité avant tout** : tout résultat de conformité obtenu en test doit pouvoir être rejoué à l'identique tant que le contexte et la version de règle n'ont pas changé (section 11).
- **Les scénarios négatifs comptent autant que les scénarios nominaux** — en particulier pour la sécurité et le multi-tenant (sections 22-24).
- **Les dépendances externes sont mockées par défaut** dans la majorité des tests (unit, integration, API), sauf tests dédiés d'intégration (section 28).
- **Automatiser ce qui est critique et répétitif, garder l'humain sur ce qui exige un jugement réglementaire ou UX** (sections 41-42).

## 5. Test Pyramid

```text
                    E2E (peu nombreux, parcours critiques uniquement)
                   /    \
              Contract / Integration
             /                      \
        API Tests                    Compliance Engine Tests (unit, denses)
       /         \                  /
  Unit / Domain Tests (les plus nombreux)
```

**Principe retenu, sans pourcentage arbitraire** : la majorité des tests doivent être rapides, ciblés et proches du code (unit et Compliance Engine), car c'est à ce niveau que l'exactitude réglementaire — l'enjeu qualité le plus critique de ce produit (section 1) — peut être vérifiée exhaustivement et rapidement, scénario par scénario. Les tests E2E, coûteux et lents, sont réservés aux parcours dont la valeur ne peut être garantie que bout en bout (section 38). Le Compliance Engine constitue une exception notable à la pyramide classique : son volume de tests unitaires doit être **proportionnellement plus dense** que pour un module métier ordinaire, du fait du nombre de scénarios réglementaires à couvrir individuellement (section 9-10).

## 6. Test Levels

**Unit Tests** — règles de conformité individuelles (section 10), calculs de montants/TVA (`07-data-model.md`, section 11), validators, transitions d'état (`07-data-model.md`, section 29), services purs du domaine.

**Integration Tests** — interaction avec la base de données (contraintes, isolation multi-tenant, `07-data-model.md` section 34-37), le stockage objet, la file de tâches, les services externes **simulés** (section 28).

**API Tests** — chaque endpoint de `08-api-specification.md` : statuts HTTP, validation, authentification, autorisation, erreurs (sections 20-21 de ce document).

**E2E Tests** — parcours utilisateurs complets, navigateur réel ou simulé (section 38).

**Contract Tests** — compatibilité entre le contrat documenté dans `08-api-specification.md` et son implémentation réelle côté backend, et entre ce contrat et les attentes du frontend (section 21).

**Security Tests** — isolation, permissions, authentification, vecteurs d'attaque courants (sections 32-33).

**Performance Tests** — API, Compliance Engine, traitement documentaire, jobs asynchrones (section 34).

## 7. Test Environments

| Environnement | Objectif | Données | Services externes | Niveau de tests | Contraintes |
|---|---|---|---|---|---|
| Local | Développement quotidien | Synthétiques, générées à la volée | Simulés/fictifs (`06-technical-architecture.md`, section 31) | Unit, Integration | Ne doit dépendre d'aucun service payant |
| CI | Validation automatique à chaque commit/PR | Synthétiques, fixtures versionnées (section 54) | Systématiquement simulés | Unit, Integration, API, Contract, Security (statique) | Doit s'exécuter rapidement (section 46) ; aucune dépendance réseau réelle |
| Test | Exécution étendue, avant fusion vers l'environnement de staging | Synthétiques, jeux de données réglementaires complets (sections 8, 9) | Simulés, avec quelques stubs de comportements précis (section 28) | Tout, y compris E2E ciblés | Reproductible, réinitialisable |
| Staging | Validation dans des conditions proches du réel, avant mise en production | Synthétiques mais réalistes en volume | Comptes de test réels des fournisseurs externes (`06-technical-architecture.md`, section 31) | E2E critiques, tests exploratoires, tests de performance légers | Jamais de données personnelles réelles (section 10 renvoyée ci-dessous) |
| Production | Usage réel | Réelles | Réels | Monitoring, pas de tests destructifs | Toute vérification post-déploiement reste passive (santé, alerting) |

## 8. Test Data Strategy

| Catégorie | Description | Usage principal |
|---|---|---|
| Données nominales | Entreprises, clients, factures représentant les cas courants des personas primaires (`05-user-stories.md`, section 2) | Tests unitaires, API, E2E de base |
| Données invalides | Champs manquants, formats incorrects, incohérences de montants | Tests de validation (section 21) |
| Données limites | Montants nuls, quantités à la limite, dates aux bornes de périodes de validité de règles | Tests unitaires du Compliance Engine, tests de propriété (section 15) |
| Données réglementaires | Scénarios directement issus de `02-regulatory-study.md` (franchise en base, clientèle mixte, opérations internationales) | Matrice réglementaire (section 9) |
| Données multi-tenant | Au moins deux organisations distinctes avec des données volontairement proches (mêmes noms, structures similaires) pour maximiser la sensibilité des tests d'isolation | Tests multi-tenant (section 22) |
| Données historiques | Plusieurs versions de règles avec des `effective_from`/`effective_until` distincts | Tests de versionnement (section 12) |
| Données documentaires | PDF simple, Factur-X, UBL, CII, fichiers corrompus ou incomplets | Tests documentaires (sections 18-19) |

## 9. Regulatory Test Matrix

> Cette matrice est directement dérivée des scénarios déjà documentés dans `02-regulatory-study.md` et `05-user-stories.md` (section 11, scénarios réglementaires). Aucun scénario n'est ajouté sans ancrage dans ces documents.

| ID | Scénario réglementaire | Données | Règle concernée | Résultat attendu | Niveau de test |
|---|---|---|---|---|---|
| REG-001 | Micro-entrepreneur en franchise en base, client professionnel français | `vat_status=ASSUJETTI_FRANCHISE_EN_BASE`, client `PROFESSIONNEL_FRANCAIS` | Éligibilité (`02-regulatory-study.md` §6) | Diagnostic confirme l'assujettissement malgré la franchise (BR-ELIGIBILITY-001) | Unit (Compliance Engine), API |
| REG-002 | TPE, client particulier | Client `PARTICULIER` | Distinction e-invoicing/e-reporting (§7) | Règles d'e-reporting appliquées, pas d'e-invoicing | Unit, API |
| REG-003 | Client professionnel étranger (international) | Client `PROFESSIONNEL_ETRANGER` | §7-8 | Règles d'e-reporting international appliquées | Unit |
| REG-004 | Facture avec SIREN client manquant, client professionnel français | `customer.siren = null` | Mention obligatoire SIREN (§10) | `NON_CONFORME` sur la vérification SIREN, `A_VERIFIER` si autre donnée manquante ailleurs | Unit, API |
| REG-005 | Facture avec plusieurs taux de TVA sur des lignes distinctes | Lignes à 20% et 5,5% | Cohérence des montants (`07-data-model.md` §11) | Totaux HT/TVA/TTC cohérents par ligne et agrégés | Unit, Property-based |
| REG-006 | PDF simple envoyé par email, sans structuration | `Document.file_format = pdf_simple` | Définition de la facture électronique (§8) | Finding explicite expliquant la non-conformité du format, même si les mentions textuelles sont présentes | Unit, E2E (E2E-004) |
| REG-007 | Opération exonérée de TVA | Opération hors champ TVA | Périmètre de la réforme (§6-7) | `NON_APPLICABLE`, jamais `NON_CONFORME` | Unit |
| REG-008 | Clientèle mixte (professionnels et particuliers) dans une même organisation | Plusieurs `Invoice` avec des `customer_type` différents | §18, scénario C | Chaque facture reçoit le jeu de règles correspondant à son propre client, sans confusion entre eux | Integration, E2E |
| REG-009 | Entreprise dont la taille détermine l'obligation d'émission à une date encore future | `company_size_category = PME_TPE_MICRO` | Calendrier différencié (§5) | Diagnostic affiche la date du 1er septembre 2027, pas 2026 | Unit, API |
| REG-010 | Règle marquée de confiance « Faible » ou « Moyen » dans l'étude réglementaire | `RuleVersion.confidence_level != ELEVE` | §26 | Résultat `INCERTAIN_REGLEMENTAIRE`, jamais un verdict catégorique | Unit (BR-COMPLIANCE-004) |

## 10. Compliance Engine Testing

Pour chaque règle du référentiel (`07-data-model.md`, section 15-16), la structure de test suit systématiquement :

```text
Input (facture + contexte)
   ↓
Contexte résolu (statut TVA, taille, statut client, opération)
   ↓
Règle sélectionnée (version active à la date du test)
   ↓
Résultat attendu (l'un des six états, 05-user-stories.md §8)
```

Chaque règle doit être testée sous ses variantes suivantes, sans exception :
- **Règle applicable, condition respectée** → `CONFORME`.
- **Règle applicable, condition non respectée** → `NON_CONFORME`, avec message et action de correction non vides.
- **Règle non applicable au contexte** → `NON_APPLICABLE`.
- **Donnée nécessaire manquante** → `A_VERIFIER`, jamais `NON_CONFORME` (BR-COMPLIANCE-003).
- **Règle de confiance non élevée** → `INCERTAIN_REGLEMENTAIRE` si applicable (BR-COMPLIANCE-004).
- **Cas limite propre à la règle** (par exemple une date d'opération exactement à la frontière d'une période de validité).

## 11. Determinism

Propriété non négociable du Compliance Engine (`06-technical-architecture.md`, ADR-002 et section 3) :

```text
Même entrée + même contexte + même version de règle = même résultat, à chaque exécution.
```

**Tests dédiés** : exécuter la même analyse plusieurs fois (y compris en parallèle, si l'implémentation le permet) et vérifier l'identité stricte des résultats produits, y compris l'ordre des `ComplianceFinding` si celui-ci est censé être stable. Ce test doit être exécuté systématiquement en CI pour toute modification touchant au Compliance Engine — une variation de résultat sans changement d'entrée, de contexte ou de version de règle constitue un **échec critique bloquant** (section 45).

## 12. Rule Version Testing

Deux garanties distinctes à tester :
1. **Deux versions différentes d'une même règle peuvent produire des résultats différents lorsque c'est attendu** — par exemple, une règle qui change de gravité ou de condition entre une V1 et une V2 doit démontrer ce changement dans un test dédié comparant explicitement les deux versions sur les mêmes données.
2. **Une analyse historique ne doit jamais changer de résultat parce qu'une nouvelle version de règle devient active** — test consistant à : exécuter une analyse avec `RuleVersion V1` active, activer ensuite `V2`, puis re-consulter (sans relancer) l'analyse historique, et vérifier que son résultat et son `rule_version_id` référencé restent strictement identiques à ce qu'ils étaient (cohérent avec `07-data-model.md`, ADR-003 et section 19).

Ce deuxième test est **particulièrement critique** : une régression sur ce point romprait silencieusement l'exigence d'auditabilité posée dès `04-product-requirements.md` (section 24).

## 13. Regression Testing

```text
Nouvelle version de règle
        ↓
Exécution de la suite de tests existante (Golden Test Cases, section 14, inclus)
        ↓
Analyse des écarts
        ↓
Chaque écart doit être explicitement attendu et documenté, ou traité comme une régression
        ↓
Release seulement si tous les écarts sont classés « attendus »
```

**Objectif spécifique** : distinguer un changement de résultat **voulu** (la règle a effectivement changé, comme en section 12) d'un changement de résultat **non voulu** sur une règle qui n'aurait pas dû être affectée (conflit entre règles, effet de bord d'une modification de la logique d'agrégation, `06-technical-architecture.md` section 9). Toute règle non concernée par une modification donnée doit produire un résultat strictement identique avant/après sur son jeu de tests dédié.

**Déclenchement** : cette suite de régression s'exécute à **chaque** publication d'une nouvelle `RuleVersion`, sans hypothèse de fréquence — le référentiel réglementaire suit une logique **event-driven** (une nouvelle version peut être reçue à tout moment) et non une logique de calendrier fixe (section 60), ce qui exclut toute optimisation de la stratégie de régression reposant sur un rythme de publication supposé régulier.

## 14. Golden Test Cases

Ensemble de scénarios de référence, dont le résultat attendu est **connu, documenté et doit rester stable** sauf changement réglementaire explicite et volontaire. Ils couvrent :
- les cas nominaux des personas primaires (`05-user-stories.md`, section 2) ;
- les scénarios réglementaires les plus importants de la matrice (section 9), en particulier REG-001 (franchise en base) et REG-006 (PDF non conforme), directement liés aux confusions les plus documentées du marché (`03-market-analysis.md`, section 14) ;
- les cas limites identifiés lors du développement (accumulation progressive) ;
- tout cas ayant historiquement révélé un bug ou une mauvaise interprétation réglementaire — ajouté au jeu de Golden Test Cases dès sa correction, pour empêcher toute régression future sur ce point précis.

Ces cas constituent une **référence permanente** : leur modification ne doit jamais être un simple ajustement pour faire passer un test, mais une décision explicite et documentée (justifiée par un changement réglementaire réel, section 13).

## 15. Property-Based Testing

Pertinent pour les calculs de montants et de TVA (`07-data-model.md`, section 11), où certaines propriétés sont vraies **indépendamment du contexte réglementaire précis** :
- `Σ InvoiceLine.line_amount_ht = Invoice.total_amount_ht` — vraie pour toute facture, quel que soit son contexte.
- `line_amount_ht + line_amount_vat = line_amount_ttc` — vraie pour toute ligne, à la tolérance d'arrondi près.
- `line_amount_ht × vat_rate = line_amount_vat` — vraie tant que `vat_rate` est correctement appliqué.

**Propriété volontairement exclue** : aucune propriété de la forme « toute facture avec `vat_rate = 0` est exonérée » n'est testée par property-based testing, car ce lien n'est **pas une vérité mathématique** mais une conséquence contextuelle d'une règle réglementaire (un taux à 0 peut refléter une exonération, une non-application de la TVA, ou une erreur de saisie) — ce type d'assertion relève des tests de règles individuelles (section 10), pas d'une propriété générale.

## 16. Mutation Testing

**Pertinent** pour : les règles de conformité individuelles (section 10) et les calculs de montants (section 15), où une mutation non détectée (par exemple, un opérateur de comparaison inversé dans une condition de règle) aurait un impact direct et grave sur l'exactitude réglementaire — le cœur de la proposition de valeur du produit. Également pertinent pour les vérifications d'autorisation (sections 22-23), où une mutation non détectée pourrait ouvrir une brèche de sécurité.

**Excessif** pour : le code d'infrastructure (configuration, logging), les composants frontend, et plus généralement tout code dont une mutation non détectée n'aurait qu'un impact cosmétique ou de performance — y consacrer un effort de mutation testing ne serait pas proportionné au risque.

## 17. Invoice Testing

Couverture spécifique à `Invoice` (`07-data-model.md`, section 10) :
- Création par saisie manuelle et par import de document (US-INVOICE-001/002).
- Modification d'une facture déjà `ANALYZED` — comportement désormais tranché (`07-data-model.md`, `08-api-specification.md`) : la modification ne crée **jamais** une nouvelle facture/version, la facture reste la même entité, mais son statut passe de `ANALYZED` à **`ANALYSIS_STALE`** dès qu'une donnée pertinente pour la conformité change. Scénario de test attendu : modifier un champ pertinent pour la conformité (par exemple le SIREN du client ou un montant de ligne) sur une facture `ANALYZED` → vérifier que le statut devient `ANALYSIS_STALE`, que l'ancien `ComplianceEvaluation`/résultat reste consultable tel quel dans l'historique (auditabilité), et qu'aucune nouvelle facture n'est créée. Vérifier également qu'une modification d'un champ non pertinent pour la conformité (par exemple une note interne, si un tel champ existe) ne déclenche pas cette transition. Le repassage à `ANALYZED` nécessite explicitement le déclenchement d'une nouvelle analyse par l'utilisateur, produisant un nouveau `ComplianceEvaluation`.
- Transitions d'état (`DRAFT → READY_FOR_ANALYSIS → ANALYZED`, section 25 de ce document).
- Calculs de montants et TVA (section 15).
- Lignes de facture, y compris plusieurs lignes à taux différents (REG-005).
- Association avec un ou plusieurs `Document`.
- Cohérence des données client référencées (statut, SIREN si professionnel français).

**Non testé** (hors périmètre, cohérent avec `04-product-requirements.md` section 30) : numérotation générée par le système (le `invoice_number` est une donnée extraite/saisie, pas générée — `07-data-model.md` section 10), validation ou annulation d'émission (n'existe pas dans ce produit).

## 18. Document Testing

Pour chaque format identifié dans `02-regulatory-study.md` (section 9) et repris dans `06-technical-architecture.md` (section 11) :

| Format | Document valide | Document invalide | Document incomplet | Document corrompu | Format inconnu |
|---|---|---|---|---|---|
| PDF simple | Import réussi, traité comme non structuré (REG-006) | — | — | Erreur technique catégorisée `FAILED` (`07-data-model.md` §14), jamais un résultat de conformité | — |
| Factur-X | Extraction réussie des données structurées | Structure XML invalide | Mentions manquantes dans le XML | Erreur technique | — |
| UBL / CII | Idem, selon le périmètre effectivement supporté par l'implémentation (`06-technical-architecture.md` section 11 : périmètre volontairement restreint au MVP) | Idem | Idem | Idem | — |
| Tout format | — | — | — | — | `Document.file_format = inconnu`, message explicite invitant à la saisie manuelle (US-INVOICE-001) |

**Principe transverse** : quel que soit le format, une défaillance de lecture du fichier est toujours catégorisée comme erreur technique (`DocumentProcessingRecord.status = FAILED`), jamais traduite en un résultat de conformité de la facture elle-même (cohérent avec `06-technical-architecture.md`, section 25).

## 19. Document Security Testing

- **Extension falsifiée** : fichier renommé en `.pdf` mais contenant un autre type de contenu — doit être détecté par inspection du contenu réel, pas uniquement de l'extension.
- **MIME type incohérent** entre l'en-tête déclaré et le contenu effectif.
- **Fichiers volumineux** au-delà de la limite de **20 Mo par fichier** retenue pour le MVP (`08-api-specification.md`, section 31 ; `10-security-privacy.md`, section 22) — doit produire une erreur `413` propre, pas un comportement indéfini.
- **Contenu inattendu** (fichier exécutable renommé, script embarqué dans un PDF).
- **Noms de fichiers dangereux** (séquences de traversée de répertoire, caractères de contrôle) — doivent être neutralisés avant tout usage du nom de fichier dans un chemin de stockage.
- **Archives** : si un format archive (ZIP contenant un Factur-X, par exemple) devait être supporté, tester le comportement face à une archive imbriquée excessive (bombe zip) — actuellement hors périmètre du MVP tel que défini par `06-technical-architecture.md`, à réévaluer si ce cas devient pertinent.

## 20. API Testing

Couverture systématique de chaque endpoint de `08-api-specification.md` :
- Statuts HTTP retournés conformes au tableau de la section 42 du document API.
- Validation de requête (chaque règle de la section 15 de `08-api-specification.md`).
- Conformité du schéma de réponse (présence, absence, types).
- Authentification requise/non requise selon l'endpoint (section 7 de l'API).
- Autorisation (rôle unique `OWNER` au MVP, mais vérification systématique que l'action est bien exécutée dans le contexte du tenant de l'appelant).
- Pagination (comportement aux bornes : page vide, dernière page, `per_page` maximal).
- Filtrage et tri (section 16 de l'API).
- Idempotence sur les opérations concernées (section 20 de l'API) : clés d'idempotence conservées **24h par défaut** (TTL applicatif ; `POST /invoices/{id}/compliance-analyses` l'implémente depuis la Phase 5 via un store PostgreSQL transactionnel, `Shared/Idempotency/`, pas Redis — voir `08-api-specification.md` section 20). Test dédié : rejouer une même requête A (même `Idempotency-Key`) plusieurs fois de suite doit produire **une seule opération métier effective** (par exemple, une seule `ComplianceAnalysis` créée), la même réponse étant renvoyée à chaque rejeu tant que la fenêtre de 24h n'est pas expirée ; passé ce délai, une requête portant la même clé doit être traitée comme une nouvelle opération distincte. Sous requêtes concurrentes portant la même clé, une seule doit exécuter le travail réel : vérifié par un test de concurrence réelle (deux processus/connexions séparés), pas seulement séquentiel (Phase 5).
- Concurrence (`If-Match`/`ETag` sur `PATCH /invoices/{id}`, section 21 de l'API).
- Rate limiting (comportement au-delà du seuil, une fois calibré).

## 21. API Contract Testing

```text
Attente frontend
        ↕
Contrat documenté (08-api-specification.md)
        ↕
Implémentation backend réelle
```

**Objectif** : détecter automatiquement tout écart entre le contrat documenté et l'implémentation réelle — champ manquant ou renommé, type incorrect, statut HTTP différent de celui documenté. Ce test doit idéalement s'appuyer sur une source unique de vérité du contrat (le futur `openapi.yaml`, `08-api-specification.md` section 50) plutôt que sur une duplication manuelle des attentes côté frontend et côté tests backend — une divergence entre les deux serait elle-même un signal de dérive du contrat.

## 22. Multi-Tenant Testing

**Considéré comme bloquant pour la production** (cohérent avec la mission et `06-technical-architecture.md`, section 20).

Scénario de base : deux organisations `Tenant A` et `Tenant B`, avec des données volontairement similaires (section 8). Pour chaque ressource tenant-scoped (`07-data-model.md`, section 25) :
- **Lecture** : `Tenant A` tente de lire une ressource de `Tenant B` par son identifiant → attendu `404` (jamais `200` ni `403`, cohérent avec `08-api-specification.md` section 42).
- **Création** : une ressource créée par `Tenant A` référence uniquement des entités de `Tenant A` (par exemple, `Invoice.customer_id` ne peut pas pointer vers un `Customer` de `Tenant B`) → tentative attendue en échec `422`/`404`.
- **Modification/Suppression** : mêmes vérifications qu'en lecture.
- **Recherche/Listing** : une recherche par `Tenant A` ne renvoie jamais d'éléments de `Tenant B`, y compris dans les cas limites (page vide, tri, filtrage).
- **Documents** : un document uploadé par `Tenant A` n'est jamais accessible via `Tenant B`, y compris via une URL de téléchargement (`08-api-specification.md`, section 31).
- **Analyses de conformité** : une analyse de `Tenant A` n'est jamais visible par `Tenant B`, y compris dans l'historique et le dashboard.
- **Audit** : `GET /audit-events` de `Tenant A` ne renvoie jamais un événement de `Tenant B`, y compris les événements globaux (jamais renvoyés du tout via cet endpoint, `08-api-specification.md` section 39).

Ces tests s'exécutent à **tous les niveaux** (integration, API, E2E — E2E-005 section 38), pas uniquement en test API isolé, car l'isolation doit être garantie de bout en bout.

## 23. Authorization Testing

Bien qu'un seul rôle (`OWNER`) existe au MVP (`04-product-requirements.md`, section 21), les cas suivants restent à tester :
- Utilisateur authentifié mais non membre d'aucune organisation (état transitoire possible, ex. juste après inscription avant configuration).
- Tentative d'accès à une ressource inexistante (`404`, distinct d'une ressource existante mais appartenant à un autre tenant, section 22).
- Tentative d'action sur `admin/rule-versions` (`08-api-specification.md`, section 38) avec les seules permissions `OWNER` → doit être strictement refusée, cette API étant réservée à un accès interne distinct.

**Préparation à l'évolution future** : bien que non nécessaire au MVP, les tests doivent être structurés de façon à pouvoir accueillir un second rôle sans réécriture complète (cohérent avec `06-technical-architecture.md`, section 19).

## 24. Authentication Testing

- Inscription : succès, email déjà utilisé, mot de passe invalide (`08-api-specification.md`, section 23).
- Connexion : succès, identifiants invalides (message volontairement non spécifique, US-AUTH-002).
- Déconnexion : invalidation effective de la session.
- Récupération de compte (US-AUTH-003) : succès, jeton invalide ou expiré.
- Expiration de session : comportement du système après expiration (redirection, réponse `401`).
- Rafraîchissement de jeton (`POST /auth/refresh`) : désormais un test P0 — le mécanisme JWT est confirmé (`06-technical-architecture.md`, ADR-007), incluant les cas de rotation du refresh token (section 12 de `10-security-privacy.md`) et de détection de réutilisation frauduleuse d'un refresh token déjà consommé.
- Vérification d'email : désormais **tranchée et obligatoire** avant toute fonctionnalité sensible (upload, analyses persistantes, usage de l'IA, fonctionnalités avancées), mais pas nécessairement bloquante avant une utilisation basique du compte (`10-security-privacy.md`, section 12). Tests à couvrir : accès refusé à une fonctionnalité sensible tant que l'email n'est pas vérifié (message explicite, pas une erreur générique), accès basique du compte non bloqué en attendant la vérification, accès rétabli après vérification réussie, jeton de vérification invalide ou expiré.

## 25. State Transition Testing

`Invoice` (`07-data-model.md`, section 29) :
```text
DRAFT → READY_FOR_ANALYSIS → ANALYZED → ANALYSIS_STALE → (nouvelle analyse) → ANALYZED
```
- Transitions valides testées dans l'ordre attendu.
- Transitions invalides explicitement rejetées (par exemple, tenter de revenir à `DRAFT` depuis `ANALYZED` sans le mécanisme prévu).
- Répétition : lancer une analyse sur une facture déjà `ANALYZED` doit être un comportement défini (nouvelle analyse ajoutée à l'historique, US-COMPLIANCE-006), pas une erreur.
- **`ANALYZED` → `ANALYSIS_STALE`** (comportement désormais tranché, section 17) : modifier une donnée pertinente pour la conformité sur une facture `ANALYZED` fait transitionner son statut vers `ANALYSIS_STALE`, sans jamais créer de nouvelle facture ; l'ancien résultat de conformité reste consultable dans l'historique ; un nouveau déclenchement d'analyse est nécessaire pour repasser à `ANALYZED` avec un nouveau `ComplianceEvaluation`. Ce test est désormais un cas de transition **P0**, au même titre que les transitions du cycle nominal.
- Concurrence : deux requêtes de modification simultanées sur la même facture (section 21 de `08-api-specification.md`, `If-Match`).

`ComplianceAnalysis` et `DocumentProcessingRecord` (`07-data-model.md`, sections 14 et 29) suivent le même principe de test des transitions valides/invalides, avec une attention particulière à la distinction entre un état terminal d'échec technique (`FAILED`) et un résultat métier `COMPLETED` avec `global_result = NON_CONFORME` (jamais confondus, section 46 de `08-api-specification.md`).

## 26. Asynchronous Processing Testing

Pour chaque traitement asynchrone identifié (`06-technical-architecture.md`, section 12-13) :
```text
Upload/Déclenchement → Job créé → Traitement → Succès / Échec
```
À tester : création correcte du job, traitement réussi (cas nominal), échec avec catégorisation explicite de l'erreur (technique, jamais un résultat de conformité), comportement de retry (nombre de tentatives limité, cohérent avec `06-technical-architecture.md` section 13), timeout, absence de duplication d'effet en cas de rejeu (idempotence, section 20 de l'API), reprise après une panne simulée du worker.

## 27. Queue Testing

Si l'implémentation retient une file de tâches distincte (`06-technical-architecture.md`, section 13, 21) : job valide traité correctement ; job invalide (payload corrompu) placé en échec explicite plutôt que de bloquer la file ; comportement de dead-letter (job en échec définitif reste visible et consultable, jamais silencieusement perdu) ; ordre de traitement lorsque l'ordre a un sens métier (à confirmer selon l'implémentation — aucun besoin d'ordre strict n'a été identifié dans les documents précédents à ce stade) ; idempotence en cas de job dupliqué (double publication accidentelle).

## 28. External Integration Testing

| Technique | Usage |
|---|---|
| **Mocks** | Tests rapides (unit, integration), pour toute dépendance externe (IA, email, stockage, vérification d'entreprise — `06-technical-architecture.md` section 17), exécutés systématiquement en CI |
| **Stubs** | Simuler des comportements précis (fournisseur IA qui timeout, service de stockage qui refuse un upload) pour tester les chemins de repli (section 26) |
| **Sandbox** | Tests d'intégration réelle mais isolée, en environnement de staging uniquement (section 7), avec des comptes de test des fournisseurs |
| **Contract Tests** | Vérifier que l'interface interne provider-agnostic (`06-technical-architecture.md`, section 17) reste respectée par chaque implémentation concrète de fournisseur |

**Principe** : la majorité des tests (CI) ne dépendent jamais d'un service externe réel — seuls les tests de sandbox en staging, en nombre volontairement limité, valident l'intégration réelle.

## 29. AI Testing

Rappel structurant, repris de `04-product-requirements.md` (section 17) et `06-technical-architecture.md` (section 14-15) : **l'IA n'est jamais l'autorité réglementaire**. Les tests IA vérifient donc un rôle strictement borné :
- **Fidélité au contexte fourni** : la reformulation produite ne doit jamais contredire le résultat déterministe du `ComplianceFinding` source.
- **Absence d'invention** : l'IA ne doit jamais introduire une obligation, un montant ou une date non présents dans le contexte transmis (`06-technical-architecture.md` section 14, minimisation) ou dans `02-regulatory-study.md`.
- **Refus explicite** plutôt qu'une réponse inventée lorsque la question posée dépasse le périmètre du contexte fourni (US-AI-002).
- **Sécurité** : absence de fuite de données au-delà du contexte minimisé transmis (section 14 de `06-technical-architecture.md`) ; résistance de base à l'injection de prompt côté utilisateur (`/assistant/questions`, `08-api-specification.md` section 35).
- **Comportement de repli** : en cas d'échec ou d'indisponibilité du fournisseur IA, le message par défaut (`ComplianceFinding.message`, `07-data-model.md` section 18) reste affiché — testé explicitement comme cas de test à part entière, pas comme un simple test d'erreur générique (section 35 de `08-api-specification.md`).

## 30. AI Evaluation

```text
Question / Finding de référence
        ↓
Caractéristiques attendues (fidélité, absence de contradiction, clarté)
        ↓
Réponse de l'IA
        ↓
Évaluation (pas une correspondance textuelle exacte)
```

L'évaluation porte sur un jeu de questions/findings de référence représentatifs des Golden Test Cases (section 14) et des personas primaires. Les critères d'évaluation, **non réductibles à une correspondance exacte de texte** : exactitude par rapport au résultat du Compliance Engine (aucune contradiction), fidélité au contexte transmis (aucune information inventée), clarté pour un non-spécialiste (cohérent avec `04-product-requirements.md`, section 14 — Accessibilité), absence de contenu à risque de sécurité (fuite, formulation trompeuse).

## 31. AI Non-Regression

```text
Ancien modèle/prompt
        ↓
Jeu d'évaluation de référence (section 30)
        ↓
Nouveau modèle/prompt
        ↓
Comparaison
```

**Condition d'acceptation d'un changement de modèle ou de prompt** : le nouveau comportement ne doit dégrader aucun des critères de la section 30 par rapport à l'ancien sur le jeu de référence — en particulier, aucune régression sur la fidélité au résultat déterministe n'est tolérée (contrainte non négociable, contrairement à la clarté ou au style qui peuvent légitimement s'améliorer ou varier). Un changement qui améliore la clarté mais dégrade la fidélité doit être **rejeté**, la fidélité étant hiérarchiquement prioritaire (cohérent avec le principe fondamental de la section 1).

## 32. Security Testing

Catégories couvertes à ce niveau de stratégie (le détail complet relève de `10-security-privacy.md`) :
- Authentification et autorisation (sections 22-24).
- Isolation tenant (section 22) — **catégorie bloquante**.
- Validation des entrées, prévention d'injection (SQL, NoSQL selon l'implémentation).
- Protection XSS sur tout contenu utilisateur potentiellement réaffiché (nom de client, description de ligne de facture).
- CSRF — mécanisme de session désormais tranché (JWT access token courte durée conservé en mémoire côté frontend + refresh token en cookie `HttpOnly`, `Secure`, `SameSite`, `10-security-privacy.md` section 12) : la surface CSRF générale de l'API est réduite par l'usage de l'en-tête `Authorization`, mais une protection CSRF ciblée reste **nécessaire** sur le seul endpoint utilisant le cookie de refresh, `POST /auth/refresh` — test dédié à prévoir sur cet endpoint (absence de `SameSite`/`Origin` cohérents → requête rejetée).
- Sécurité de l'upload de documents (section 19).
- Absence de secrets exposés dans les réponses API ou les logs (`08-api-specification.md`, section 55-56 ; `07-data-model.md`, section 22).
- Sécurité de session (expiration, invalidation à la déconnexion).
- Rate limiting (comportement effectif une fois calibré, `08-api-specification.md` section 22).
- Contrôle d'accès systématique à chaque endpoint (section 20).

## 33. OWASP

La liste OWASP Top 10 (ou équivalent API Security Top 10, plus pertinent ici compte tenu de la nature du produit) sert de **référence de vérification**, pas de check-list copiée telle quelle. Mapping des risques les plus pertinents pour ce produit spécifique :

| Risque OWASP (catégorie générale) | Pertinence pour ce produit | Test associé |
|---|---|---|
| Broken Access Control / BOLA (autorisation cassée au niveau objet) | **Très élevée** — c'est directement le risque d'isolation multi-tenant | Section 22 |
| Injection | Moyenne — dépend du mécanisme d'accès aux données retenu | Section 32 |
| Sensitive Data Exposure | Élevée — données financières et documents | Sections 19, 32 |
| Security Misconfiguration | Moyenne | Vérifications de configuration en CI/CD (section 44) |
| Server-Side Request Forgery | Faible à moyenne — pertinent si l'extraction documentaire traite des URL externes (non identifié dans les documents précédents) | À réévaluer selon l'implémentation |
| Excessive Data Exposure (API) | Moyenne — pertinent pour l'AI Gateway (minimisation, section 29) | Sections 29, 32 |

Les catégories jugées peu pertinentes pour ce produit (par exemple, des risques spécifiques à des architectures que ce système n'a pas, comme des composants tiers exposés publiquement) ne sont pas développées ici, conformément à la consigne de ne pas copier la liste telle quelle.

## 34. Performance Testing

| Type | Objectif | Priorité pour ce produit |
|---|---|---|
| Load Testing | Comportement sous charge normale attendue | API et Compliance Engine en priorité (`06-technical-architecture.md` section 33) |
| Stress Testing | Comportement au-delà de la charge normale, point de rupture | Upload de documents et déclenchement d'analyses (opérations coûteuses, section 22 de `08-api-specification.md`) |
| Spike Testing | Variation brutale de charge | Moins critique au MVP compte tenu du volume attendu (`03-market-analysis.md` ne documente pas de pic prévisible) |
| Endurance Testing | Comportement sur une durée prolongée | Pertinent pour la file de tâches et l'`AuditLogEntry` (croissance continue, `07-data-model.md` section 41) |

**Priorités, sans seuil chiffré arbitraire** (cohérent avec la consigne) : API et Compliance Engine en premier, upload/extraction de documents ensuite, jobs asynchrones et dashboard en dernier — ordre reflétant directement la criticité fonctionnelle établie en sections 1-2. Les seuils précis (temps de réponse cible, débit) seront déterminés à partir de besoins réels observés, pas fixés a priori.

## 35. Database Testing

- Contraintes d'intégrité définies dans `07-data-model.md` (section 34) : nullabilité, unicité, clés étrangères.
- Absence de chevauchement de périodes de validité (`FiscalContext`, `RuleVersion`) — testé explicitement comme un invariant critique (section 28 de `07-data-model.md`).
- Transactions : les frontières définies en section 37 de `07-data-model.md` (création de facture + lignes ; analyse + findings + snapshot + audit) doivent être testées comme réellement atomiques — un échec partiel ne doit jamais laisser un état incohérent visible.
- Isolation transactionnelle suffisante pour éviter les lectures sales sur les entités en cours de création (particulièrement `ComplianceAnalysis`).
- Index critiques (`07-data-model.md`, section 33) : vérification de leur utilisation effective sur les requêtes les plus fréquentes, notamment la sélection des règles applicables à une date donnée.
- Isolation tenant au niveau base de données elle-même, si des mécanismes déclaratifs sont utilisés (cohérent avec `07-data-model.md` sections 4 et 34).

## 36. Migration Testing

Chaque migration de schéma doit être testée : application vers l'avant sur un jeu de données représentatif, rollback si l'implémentation le supporte, absence de perte de données existantes, compatibilité avec les données déjà en production (simulée en staging).

**Attention particulière aux migrations touchant** : `RuleVersion` (toute migration ne doit jamais permettre une modification en place d'une version existante, ADR-003) ; `FiscalContext` (absence de chevauchement à préserver) ; toute migration ajoutant une contrainte `NOT NULL` sur une colonne existante déjà peuplée (risque de rupture sur des données historiques).

## 37. Backup & Recovery Testing

Sans remplacer la stratégie d'infrastructure : vérification périodique que les sauvegardes de la base de données et du stockage objet (`06-technical-architecture.md`, section 32) sont **restaurables**, pas seulement produites ; test de restauration cohérente des deux ensemble (un document sans sa métadonnée, ou l'inverse, serait problématique — `06-technical-architecture.md` section 32) ; estimation de la perte de données acceptable en cas d'incident (RPO) et du temps de reprise (RTO), à définir en cohérence avec `10-security-privacy.md`.

## 38. E2E Testing

Parcours critiques, adaptés aux documents précédents :

- **E2E-001** — Inscription → configuration entreprise (statut TVA, taille) → diagnostic d'éligibilité affiché (US-ONBOARDING-001, US-COMPANY-001/002, US-COMPLIANCE-001).
- **E2E-002** — Entreprise configurée → création d'un client professionnel français avec SIREN → saisie manuelle d'une facture conforme → analyse → résultat `CONFORME` (US-CUSTOMER-001/002, US-INVOICE-002, US-COMPLIANCE-002).
- **E2E-003** — Facture avec SIREN client manquant → analyse → résultat `NON_CONFORME` avec explication et action de correction affichées → correction → nouvelle analyse → résultat `CONFORME` (US-COMPLIANCE-003/004/006).
- **E2E-004** — Import d'un document PDF simple → traitement → analyse → finding explicite sur la non-conformité du format (US-COMPLIANCE-005, REG-006).
- **E2E-005** — Deux organisations distinctes → vérification de l'isolation complète des données à chaque étape du parcours (section 22 de ce document, exécuté en E2E en complément des niveaux inférieurs).
- **E2E-006** — Analyse historique consultée → une nouvelle version de règle est publiée en arrière-plan → l'analyse historique consultée à nouveau reste strictement inchangée (section 12 de ce document, US-HISTORY-001).

Ces six parcours constituent le socle E2E minimal ; aucun parcours supplémentaire n'est ajouté sans justification directe dans les documents précédents (cohérent avec le principe de la section 5 : peu de tests E2E, mais couvrant la valeur bout en bout).

## 39. Accessibility Testing

Stratégie, sans détail de design (renvoyé à `11-frontend-design-system.md`) : navigation complète au clavier des parcours critiques (en particulier la consultation d'un résultat de conformité et de son explication) ; formulaires de saisie de facture/entreprise/client correctement étiquetés ; messages d'erreur et résultats de conformité annoncés de façon compréhensible par un lecteur d'écran ; contraste suffisant sur les indicateurs d'état de conformité (particulièrement important puisque ces indicateurs portent une information critique, pas seulement décorative) ; messages de statut des traitements asynchrones (section 26) perceptibles sans dépendre uniquement de la couleur.

## 40. Browser Compatibility

Matrice volontairement restreinte à la cible réelle (`03-market-analysis.md`, sections 3-4 : micro-entrepreneurs et indépendants gérant souvent leur administratif en mobilité) : dernières versions stables de Chrome, Firefox et Safari (desktop et mobile), cohérent avec l'exigence de compatibilité mobile de `04-product-requirements.md` (section 14). Aucune prise en charge de navigateurs obsolètes n'est justifiée par les documents précédents.

## 41. Test Automation

**Automatiser systématiquement** : règles de conformité individuelles (section 10), calculs (section 15), endpoints API (section 20), tests de sécurité de premier niveau (section 32), tests multi-tenant (section 22, bloquants), régressions réglementaires (section 13), transitions d'état (section 25).

**Automatiser fortement** : les six parcours E2E critiques (section 38).

**Automatiser progressivement** : tests UI secondaires (dashboard détaillé, paramètres de compte), au fur et à mesure de la stabilisation de l'interface.

## 42. Manual Testing

Restent pertinents en manuel : évaluation UX qualitative (la reformulation IA est-elle réellement compréhensible pour un non-spécialiste, au-delà des critères automatisables de la section 30) ; accessibilité exploratoire (utilisation réelle au clavier/lecteur d'écran, au-delà des vérifications automatisées) ; validation réglementaire humaine d'une nouvelle règle avant sa mise en production (section 56) ; cas complexes combinant plusieurs facteurs réglementaires simultanément ; tests visuels de cohérence de l'interface.

## 43. Exploratory Testing

Pistes d'exploration, en particulier pour un produit récent avec un moteur de règles évolutif : saisie de données inattendues (caractères spéciaux dans un SIREN, montants négatifs) ; séquences inhabituelles (relancer une analyse plusieurs fois rapidement, revenir en arrière dans le parcours d'onboarding) ; interruption d'un traitement asynchrone (fermeture de l'onglet pendant une extraction de document, puis retour) ; double clic sur un bouton de déclenchement d'analyse (test de l'idempotence en conditions réelles, section 20) ; rafraîchissement de page pendant un traitement `PENDING` ; navigation arrière du navigateur après une action de correction.

## 44. Test Prioritization

| Niveau | Définition | Exemples pour ce produit |
|---|---|---|
| **Critical** | Résultat réglementaire incorrect, exposition de données, violation de l'isolation tenant, corruption de données critiques | Mauvais résultat de conformité (section 1), fuite cross-tenant (section 22), non-déterminisme (section 11) |
| **High** | Défaillance importante avec mitigation possible | Échec d'extraction documentaire sans repli clair vers la saisie manuelle |
| **Medium** | Impact limité | Erreur de formatage d'une date dans une notification |
| **Low** | Impact mineur | Incohérence cosmétique dans le dashboard |

## 45. Release Gates

```text
Build                              bloquant
Unit Tests (incl. Compliance)      bloquant
Integration Tests                  bloquant
API Contract Tests                 bloquant
Multi-Tenant Tests                 bloquant (section 22)
Security Tests (niveau critique)   bloquant
Determinism Tests (section 11)     bloquant
E2E Critical (section 38)          bloquant
Regulatory Regression (section 13) bloquant si écarts non expliqués
Performance                        non bloquant au MVP, surveillé
Accessibility                      non bloquant au MVP, surveillé
```

**Explicitement bloquant, sans exception** : échec de build, régression sur un Golden Test Case (section 14) non explicitement justifiée, toute faille de sécurité critique, toute violation d'isolation multi-tenant, tout non-déterminisme détecté dans le Compliance Engine.

## 46. CI/CD Testing Pipeline

```text
Commit
   ↓
Lint
   ↓
Unit Tests (dont Compliance Engine, sections 10-11)
   ↓
Integration Tests
   ↓
API Tests + Contract Tests
   ↓
Security Tests (statiques + niveau critique)
   ↓
Build
   ↓
E2E Critiques (section 38)
   ↓
Déploiement Staging
   ↓
Tests d'acceptation (incluant sandbox d'intégrations, section 28)
   ↓
Déploiement Production
```

Les tests de performance et d'accessibilité s'exécutent en parallèle du pipeline principal, sans bloquer le déploiement au stade MVP (section 45), mais leurs résultats sont surveillés et rapportés (section 47).

## 47. Test Reporting

Métriques suivies : nombre de tests exécutés et leur répartition par niveau (section 6) ; taux de succès/échec ; tests instables identifiés (section 49) ; durée d'exécution du pipeline ; couverture (section 48, avec ses limites) ; nombre et nature des régressions détectées (section 13) ; nombre de vulnérabilités identifiées par sévérité ; statut de la Regulatory Traceability Matrix (section 52) — à jour ou non pour chaque règle active.

## 48. Code Coverage

La couverture de code est un **indicateur**, jamais un objectif en soi. Distinction essentielle : la **quantité** de code exécuté par les tests ne garantit pas la **qualité** de cette couverture — un test qui exécute une règle de conformité sans vérifier son résultat produit une couverture trompeuse. **Aucun seuil arbitraire** (« 90 % du projet ») n'est fixé ici, faute de justification. En revanche, les domaines critiques (Compliance Engine, isolation multi-tenant, calculs de montants) doivent viser une couverture **robuste et qualitative** — chaque branche de règle, chaque état de conformité (section 10) explicitement exercé par au moins un test, plutôt qu'un pourcentage global.

## 49. Flaky Tests

**Identification** : un test qui échoue puis réussit sans modification du code ni des données est marqué suspect après une première occurrence, confirmé après récidive. **Classification** : distinction entre instabilité liée à l'environnement (timing, ressources partagées) et instabilité révélant un vrai problème de non-déterminisme applicatif — cette seconde catégorie est **critique** pour le Compliance Engine (section 11) et ne doit jamais être traitée comme un simple flaky test. **Correction** : priorité immédiate si le test concerne une zone critique (section 44) ; mise en quarantaine temporaire possible pour les zones non critiques, mais jamais utilisée pour masquer un échec réel — **un test flaky ne doit jamais devenir une excuse pour ignorer une régression réglementaire ou de sécurité**.

## 50. Test Maintenance

| Changement | Impact sur les tests |
|---|---|
| Nouvelle version de règle réglementaire | Ajout de tests dédiés (section 12), exécution de la suite de régression (section 13), mise à jour de la Regulatory Traceability Matrix (section 52) |
| Évolution de l'API (`08-api-specification.md`) | Mise à jour des tests de contrat (section 21) en cohérence avec les règles de compatibilité déjà définies |
| Évolution du modèle de données (`07-data-model.md`) | Mise à jour des fixtures (section 54) et des tests d'intégrité (section 35) |
| Changement de fournisseur IA ou de prompt | Processus de non-régression IA dédié (section 31) |
| Évolution de l'interface | Mise à jour progressive des tests E2E/UI concernés (section 41) |

**Principe central** : les tests réglementaires (sections 9-14) sont **versionnés avec les règles auxquelles ils correspondent** — un test associé à `RuleVersion V1` reste un test valide pour `V1` même après la publication de `V2`, cohérent avec l'immutabilité posée par `07-data-model.md` (ADR-003).

## 51. Traceability

| Requirement | User Story | Test Case | Type | Automatisé | Bloquant |
|---|---|---|---|---|---|
| FR-DIAGNOSTIC-001 | US-COMPLIANCE-001 | TC-COMPLIANCE-001 | Unit + API | Oui | Oui |
| FR-COMPLIANCE-001 | US-COMPLIANCE-002 | TC-COMPLIANCE-002 | Unit + API + E2E-002 | Oui | Oui |
| FR-COMPLIANCE-002 | US-COMPLIANCE-003 | TC-COMPLIANCE-003 | Unit + API | Oui | Oui |
| FR-COMPLIANCE-004 | US-COMPLIANCE-005 | TC-COMPLIANCE-005 (REG-006) | Unit + E2E-004 | Oui | Oui |
| FR-INVOICE-001 | US-INVOICE-001 | TC-DOCUMENT-001 | Integration + API | Oui | Oui |
| (isolation) | US multi-tenant transverses | TC-TENANT-001 | Integration + API + E2E-005 | Oui | Oui |
| FR-AUTH-001/002 | US-AUTH-001/002 | TC-AUTH-001 | API | Oui | Oui |
| FR-DASHBOARD-001 | US-DASHBOARD-001 | TC-DASHBOARD-001 | API + Manual UX | Partiel | Non |

Cette matrice, obligatoire pour toute exigence P0 (`04-product-requirements.md`, section 8), doit être tenue à jour au fil de l'implémentation ; l'extrait ci-dessus illustre la structure attendue plutôt que d'en constituer une version exhaustive.

## 52. Regulatory Traceability

| Réglementation (`02-regulatory-study.md`) | Règle | Test | Résultat attendu | Dernière validation |
|---|---|---|---|---|
| Section 6, assujettissement en franchise en base | `franchise-en-base-eligibilite` | REG-001 | Diagnostic confirme l'assujettissement | Implémentée et testée en Phase 3 (19/08/2026, `EligibilityDiagnosticCalculatorTest::testReg001FranchiseEnBaseRemainsInScope`) ; à renseigner de nouveau à chaque exécution suivante de la suite de régression (section 13) |
| Section 10, mention SIREN client | `mention-siren-client` | REG-004 | `NON_CONFORME` si absent | À renseigner à chaque exécution de la suite de régression (section 13), non implémentée avant Phase 5 |
| Section 8, définition facture électronique | `format-facture-electronique` | REG-006 | Finding explicite sur PDF simple | À renseigner à chaque exécution de la suite de régression (section 13), non implémentée avant Phase 7 |
| Section 5, calendrier différencié | `calendrier-obligation-emission` | REG-009 | Date d'obligation correcte selon la taille | Implémentée et testée en Phase 3 (19/08/2026, `EligibilityDiagnosticCalculatorTest::testReg009PmeTpeMicroEmissionDateIs2027`) ; à renseigner de nouveau à chaque exécution suivante de la suite de régression (section 13) |

Cette matrice répond directement à la question « quelle preuve avons-nous que cette règle réglementaire fonctionne correctement ? » — elle doit être maintenue en parallèle du référentiel de règles lui-même (`07-data-model.md`, section 15-16), chaque nouvelle `RuleVersion` entraînant une ligne correspondante.

## 53. Critical Test Cases

Catégories devant impérativement exister (liste de catégories, pas de détail exhaustif) :
```text
TC-COMPLIANCE-001   Diagnostic d'éligibilité, tous statuts TVA (REG-001, REG-002)
TC-COMPLIANCE-002   Analyse complète d'une facture nominale
TC-COMPLIANCE-003   Explication et action de correction pour chaque état non conforme
TC-COMPLIANCE-004   Déterminisme du Compliance Engine (section 11)
TC-COMPLIANCE-005   Non-rétroactivité d'une nouvelle version de règle (section 12, E2E-006)
TC-TENANT-001       Isolation complète cross-tenant (section 22)
TC-AUTH-001         Authentification, cas nominaux et négatifs (section 24)
TC-INVOICE-001      Transitions d'état et cohérence des montants (sections 17, 25)
TC-DOCUMENT-001     Formats supportés et non supportés (section 18)
TC-DOCUMENT-002     Sécurité documentaire (section 19)
TC-API-001          Conformité au contrat pour chaque endpoint (sections 20-21)
TC-AI-001           Fidélité et repli de l'assistant IA (section 29)
```

## 54. Test Fixtures

Fixtures nécessaires, reproductibles et isolées entre elles :
- **Organisation** : au moins deux profils (franchise en base ; assujettie redevable), pour couvrir directement REG-001.
- **Utilisateur** : un utilisateur `OWNER` par organisation de test.
- **Client** : un jeu couvrant les trois `customer_type` (professionnel français, particulier, professionnel étranger).
- **Facture** : un jeu couvrant les cas nominaux, les cas limites (montants, plusieurs taux de TVA) et les cas de la matrice réglementaire (section 9).
- **Règles et versions** : au moins deux versions d'une même règle avec des périodes de validité distinctes, pour les tests de la section 12.
- **Documents** : un exemplaire par format supporté (PDF simple, Factur-X, et selon périmètre UBL/CII) et un exemplaire corrompu/invalide par catégorie.
- **Analyses et findings** : générés par l'exécution des tests eux-mêmes plutôt que pré-chargés, pour garantir leur cohérence avec le contexte et les règles réellement actives au moment du test.

## 55. Regulatory Test Environment

Distinction nécessaire pour éviter qu'une modification du référentiel de règles ne rende les tests existants incohérents :
```text
Current Rules      — les versions de règles actuellement actives en production
Historical Rules   — les versions passées, conservées pour les tests de non-rétroactivité (section 12)
Test Rules         — des versions de règles créées spécifiquement pour un scénario de test, isolées du référentiel réel
```
Chaque test doit pouvoir **fixer explicitement** la date de référence et/ou la version de règle utilisée, plutôt que de dépendre implicitement de « la règle actuellement active », pour rester reproductible indépendamment du moment où le test est exécuté (cohérent avec le principe de déterminisme, section 11).

## 56. Human Regulatory Validation

Certains cas ne peuvent pas être entièrement automatisés et nécessitent un jugement humain avant mise en production :
- Toute **nouvelle règle** issue d'une évolution de `02-regulatory-study.md` — validation par relecture humaine de la correspondance entre la règle codée et la source réglementaire avant activation.
- Tout **changement réglementaire majeur** (comme ceux déjà documentés en 2025-2026, `02-regulatory-study.md` section 4).
- Tout **nouveau cas métier** non anticipé par la matrice réglementaire actuelle (section 9).
- Tout **résultat ambigu** signalé par un état `INCERTAIN_REGLEMENTAIRE` récurrent sur un cas non encore documenté — signal qu'une clarification réglementaire ou une mise à jour de `02-regulatory-study.md` est nécessaire.

Ce processus de validation humaine n'est pas une étape optionnelle de confort : il découle directement du principe fondamental de la section 1 (une règle incorrecte est une défaillance critique, pas seulement un bug).

## 57. Definition of Ready

Une fonctionnalité est prête à être testée lorsqu'elle dispose : d'une exigence claire (PRD ou User Story), de critères d'acceptation définis (`05-user-stories.md`), des données nécessaires identifiées (section 8), et — spécifiquement pour toute fonctionnalité touchant au Compliance Engine — d'une référence explicite à la section de `02-regulatory-study.md` qui la justifie.

## 58. Definition of Done

Une fonctionnalité est terminée lorsque : elle est implémentée ; couverte par des tests unitaires ; couverte par des tests d'intégration si applicable ; couverte par des tests API si un endpoint est concerné ; ses critères d'acceptation sont vérifiés ; la suite de régression (section 13) ne révèle aucun écart non attendu ; les tests de sécurité pertinents pour son niveau de risque (section 44) sont passés ; la documentation associée (y compris la Regulatory Traceability Matrix, section 52, si pertinent) est mise à jour.

## 59. Quality Risks

| Risque | Impact | Probabilité qualitative | Mitigation | Tests associés |
|---|---|---|---|---|
| Règle réglementaire mal interprétée | Critique — décision de conformité incorrecte transmise à l'utilisateur | Moyenne, malgré la rigueur de `02-regulatory-study.md` | Traçabilité systématique (section 52), validation humaine (section 56) | REG-* (section 9), TC-COMPLIANCE-* |
| Faux positif (facture signalée à tort non conforme) | Élevé — défiance utilisateur, contraire au principe de réassurance (`01-intent-note.md`) | Moyenne | États intermédiaires plutôt qu'un binaire strict (`04-product-requirements.md` section 10) | Section 10 |
| Faux négatif (facture signalée à tort conforme) | Critique — risque direct pour l'utilisateur | Moyenne | Golden Test Cases (section 14), revue humaine (section 56) | Sections 9, 14 |
| Régression après changement de règle | Élevé | Moyenne, sans discipline de régression | Regression Testing systématique (section 13) | Section 13 |
| Fuite cross-tenant | Critique | Faible si les tests bloquants sont respectés, élevée sinon | Multi-Tenant Testing bloquant (section 22) | TC-TENANT-001 |
| Corruption de facture (montants incohérents) | Élevé | Faible | Property-based testing (section 15), contraintes base de données (section 35) | Sections 15, 17 |
| Perte de document | Moyen | Faible | Backup & Recovery Testing (section 37) | Section 37 |
| Comportement non déterministe du Compliance Engine | Critique | Faible si testé systématiquement, sinon élevée et difficile à détecter | Determinism Testing (section 11), bloquant en release gate | Section 11 |
| Dépendance externe défaillante (IA, stockage) | Moyen | Moyenne | Mocks/fallback systématiques (sections 28-29) | Sections 26, 28, 29 |
| Hallucination IA | Élevé si non détectée | Moyenne, propre à la nature générative de l'IA | AI Evaluation et Non-Regression (sections 30-31), fallback (section 29) | Sections 29-31 |
| Couverture de test insuffisante sur une zone critique | Élevé | Dépend de la discipline d'équipe | Couverture qualitative ciblée (section 48), release gates (section 45) | Transversal |

## 60. Questions ouvertes

### Décisions actées (2026)

Les points suivants, précédemment signalés comme questions ouvertes dans une version antérieure de ce document, ont été tranchés par une décision produit et sont repris ici avec leur formulation de référence (cohérente avec `08-api-specification.md`, `10-security-privacy.md` et `07-data-model.md`) :

| Question initiale | Décision retenue | Statut |
|---|---|---|
| Comportement exact attendu de `PATCH /invoices/{id}` sur une facture `ANALYZED` | Aucune nouvelle facture/version créée ; le statut passe de `ANALYZED` à `ANALYSIS_STALE` dès qu'une donnée pertinente pour la conformité change, l'ancien résultat restant consultable dans l'historique. Testé en TC-INVOICE-001 (sections 17, 25). | Résolu — décision produit |
| Limite exacte de taille et formats précisément supportés pour l'upload | **20 Mo par fichier** au MVP ; formats initiaux : PDF, Factur-X (PDF avec XML embarqué), XML CII/UBL si réellement supportés. Reflété dans les jeux de données des sections 18-19. | Résolu — décision produit |
| Durée de conservation des clés d'idempotence | **24h par défaut** (Redis, `idempotency:{tenant}:{key}`, `TTL = 24h`), ajustable plus tard si une contrainte métier l'impose. Testé en section 20. | Résolu — décision produit |
| Fréquence exacte de publication de nouvelles versions de règles en production | Pas de fréquence fixe retenue : approche **event-driven** — le système doit pouvoir recevoir et activer une nouvelle version de règle à tout moment, sans hypothèse de calendrier régulier. La stratégie de régression (section 13) et la maintenance des tests (section 50) sont conçues pour s'exécuter à chaque publication, quel que soit le rythme réel observé, plutôt que pour un cycle périodique présupposé. | Résolu — décision produit sur le principe ; le rythme réel d'évolution réglementaire reste par nature imprévisible et n'a pas vocation à être fixé |

### Restent ouvertes

| Question | Pourquoi elle est importante | Où la trancher |
|---|---|---|
| Paramètres précis du JWT (durées exactes access/refresh, bibliothèque Symfony retenue) — mécanisme lui-même désormais confirmé (`06-technical-architecture.md` ADR-007) | Détermine le calibrage fin des tests d'expiration et de rafraîchissement (section 24) ; les tests CSRF restent pertinents uniquement sur `/auth/refresh` (section 32) | `10-security-privacy.md` / décision d'implémentation |

## 61. Impact sur Security & Privacy Specification

Ce document nourrit directement `10-security-privacy.md` sur les points listés ci-dessous, sans les trancher lui-même.

## Informations nécessaires à Security & Privacy Specification

- **Authentication** — mécanisme de session (JWT access token + refresh token en cookie `HttpOnly`) et vérification d'email désormais tranchés côté produit (sections 24, 32) ; reste à préciser côté `10-security-privacy.md` : politique de mot de passe et paramètres exacts du JWT (durées, bibliothèque — section 60).
- **Authorization** — modèle de permissions à détailler au-delà du rôle unique `OWNER`, en anticipation d'une évolution future (section 23).
- **Multi-tenancy** — mécanisme précis garantissant l'isolation au niveau base de données (au-delà des tests de la section 22, qui vérifient le résultat mais ne définissent pas le mécanisme).
- **Protection des documents** — chiffrement au repos, contrôle d'accès au stockage objet (sections 19, 37).
- **Données personnelles et financières** — classification précise et mesures associées (au-delà de l'identification déjà faite en `07-data-model.md`, section 35).
- **Chiffrement et secrets** — détail des mécanismes pour `IntegrationConfig.secret_reference` (`07-data-model.md` section 22).
- **IA et données envoyées aux fournisseurs** — politique précise de minimisation et de non-conservation par le fournisseur (au-delà du principe déjà posé en section 29 de ce document et en `06-technical-architecture.md` section 14).
- **Rétention et suppression** — durées précises, actuellement signalées comme incertaines ou à confirmer (`07-data-model.md` section 36).
- **Audit et logging** — granularité précise, accès restreint (`07-data-model.md` section 20, 35).
- **RGPD** — base légale, droits des personnes, registre de traitement — non couvert par ce document.
- **Sécurité API et infrastructure** — détail des mécanismes esquissés en section 32 de ce document et en `08-api-specification.md` section 55.
- **Gestion des incidents** — procédure non couverte ici.
- **Sauvegardes** — politique précise au-delà du principe de test de la section 37.
- **Contrôle d'accès** — détail technique du mécanisme centralisé déjà requis par `06-technical-architecture.md` (section 20) et `07-data-model.md` (section 4).
- **Conformité des fournisseurs externes** — évaluation non couverte ici (IA, stockage, email, vérification d'entreprise).
