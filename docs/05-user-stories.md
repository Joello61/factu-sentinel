# User Stories & Use Cases — Assistant de conformité à la facturation électronique

> Ce document dérive `04-product-requirements.md` (PRD, document de référence principal) en User Stories, Use Cases et critères d'acceptation. Il s'appuie également sur `01-intent-note.md` (vision), `02-regulatory-study.md` (source de vérité réglementaire) et `03-market-analysis.md` (personas, différenciation). Toute User Story sans ancrage direct dans ces documents est explicitement signalée comme une proposition à valider, non comme une exigence actée.

## 1. Introduction

Ce document répond à la question : comment les utilisateurs identifiés dans le PRD vont-ils réellement utiliser le produit pour atteindre leurs objectifs ? Il transforme les exigences fonctionnelles du PRD (`FR-*`, `MVP-*`) en User Stories traçables, avec des critères d'acceptation testables, sans entrer dans les choix d'architecture, de modèle de données ou d'API (qui relèvent des documents suivants).

## 2. Personas

Repris de `04-product-requirements.md`, section 3, eux-mêmes issus de `03-market-analysis.md`, section 4.

- **Persona principal (P)** — Le freelance qui « pense être déjà en règle » (Persona 1) : facture des clients professionnels via PDF/email, sans logiciel dédié, croit à tort que sa pratique restera valable.
- **Persona secondaire A (SA)** — La micro-entrepreneuse en franchise en base de TVA (Persona 2) : pense à tort ne pas être concernée, budget très limité.
- **Persona secondaire B (SB)** — Le dirigeant de TPE avec quelques salariés (Persona 3) : utilise déjà un outil de facturation/comptabilité établi, usage plus ponctuel du produit.
- **Persona secondaire C (SC)** — Le collaborateur de cabinet comptable (Persona 4) : non prioritaire pour le MVP (PRD, section 3) ; mentionné uniquement pour les User Stories explicitement classées Future.

Dans les User Stories ci-dessous, la mention « Utilisateur » sans précision désigne indifféremment P, SA ou SB, ces trois personas partageant le même rôle unique de propriétaire du compte défini par le PRD (section 21 : pas de gestion de rôles multiples au MVP).

## 3. User Story Map

```text
Découvrir / Comprendre
   ↓
Créer son compte (EPIC-AUTH)
   ↓
Configurer son entreprise (EPIC-COMPANY)
   ↓
Obtenir un diagnostic d'éligibilité (EPIC-COMPLIANCE)
   ↓
Ajouter un client à la facture à analyser (EPIC-CUSTOMERS)
   ↓
Importer / saisir une facture à analyser (EPIC-INVOICES)
   ↓
Analyser la conformité (EPIC-COMPLIANCE)
   ↓
Comprendre les problèmes détectés (EPIC-COMPLIANCE / EPIC-AI-ASSISTANT)
   ↓
Corriger et relancer l'analyse (EPIC-COMPLIANCE)
   ↓
Suivre son état de conformité dans le temps (EPIC-DASHBOARD)
   ↓
Consulter l'historique (EPIC-DASHBOARD)
```

Contrairement à l'exemple de structure du brief, ce parcours ne se termine pas par une étape « Valider / Exporter / Transmettre » : le PRD exclut explicitement l'émission ou la transmission de factures du périmètre produit (PRD, section 7 et section 30, DEC-001). Le parcours se termine par la vérification confirmée et l'orientation de l'utilisateur vers son propre outil de facturation pour l'émission réelle (PRD, parcours 6).

Cette User Story Map ne comporte donc pas d'Epic dédiée à la « facturation » en tant que production de documents destinés à des clients, conformément au PRD.

## 4. Epics

| Epic | Justification | Présent au MVP ? |
|---|---|---|
| EPIC-AUTH | Nécessaire pour tout suivi individualisé (PRD, section 9 — Authentification) | Oui |
| EPIC-ONBOARDING | Parcours 1-2 du PRD (section 12) | Oui |
| EPIC-COMPANY | FR-COMPANY-001, FR-COMPANY-002 | Oui |
| EPIC-CUSTOMERS | Nécessaire de façon minimale pour la vérification des mentions (PRD, section 9 — Clients) | Oui, périmètre minimal |
| EPIC-INVOICES | FR-INVOICE-001 — import/saisie à des fins d'analyse uniquement | Oui, périmètre minimal (pas de création destinée à l'émission) |
| EPIC-COMPLIANCE | Cœur du produit — FR-DIAGNOSTIC-001, FR-COMPLIANCE-001 à 004 | Oui |
| EPIC-DOCUMENTS | Support de EPIC-INVOICES (PRD, section 16) | Oui, périmètre minimal |
| EPIC-DASHBOARD | FR-DASHBOARD-001, FR-HISTORY-001 | Oui, priorité P1 |
| EPIC-AI-ASSISTANT | Section 17 du PRD — couche d'assistance et de reformulation | Oui, périmètre restreint (P1) |
| EPIC-NOTIFICATIONS | Section 19 du PRD — analysée mais non engagée au MVP | Partiellement — P2/Future selon la notification |
| EPIC-SETTINGS | Nécessaire pour gérer le compte et l'entreprise après la configuration initiale | Oui, périmètre minimal |
| EPIC-ADMINISTRATION | Non prévue comme fonctionnalité utilisateur par le PRD (pas de rôles multiples au MVP, section 21) ; nécessaire malgré tout comme fonction interne minimale (gestion des règles de conformité elles-mêmes) | Oui, mais strictement interne (voir section 26) |

Aucune Epic « EPIC-CRM », « EPIC-PAYMENT » ou « EPIC-ACCOUNTING » n'est créée, conformément au hors périmètre défini par le PRD (section 30).

## 5. Priorisation

Reprise des niveaux du PRD :
- **P0** — indispensable au MVP, sans quoi la proposition de valeur ne fonctionne pas.
- **P1** — importante, non bloquante pour valider le concept initial.
- **P2** — utile, peut attendre une itération ultérieure.
- **FUTURE** — hors MVP, mentionnée pour mémoire ou comme proposition à valider.

## 6. User Stories

### Epic EPIC-AUTH

**US-AUTH-001**
Epic : EPIC-AUTH — Persona : Utilisateur (tous)
Titre : Créer un compte
En tant qu'utilisateur non inscrit, je veux créer un compte afin de pouvoir accéder à mes diagnostics et historiques de conformité.
Priorité : P0
Dépendances : aucune
Traçabilité PRD : FR-AUTH-001
Critères d'acceptation :
```text
Given je ne possède pas encore de compte
When je renseigne les informations nécessaires à la création d'un compte
Then un compte est créé
And je suis reconnu comme propriétaire de ce compte
And j'accède au parcours d'onboarding (US-ONBOARDING-001).
```
```text
Given je tente de créer un compte avec des informations invalides ou incomplètes
When je soumets le formulaire
Then le système m'indique précisément quelle information corriger
And aucun compte n'est créé tant que l'erreur persiste.
```

**US-AUTH-002**
Epic : EPIC-AUTH — Persona : Utilisateur (tous)
Titre : Se connecter à mon compte
En tant qu'utilisateur possédant déjà un compte, je veux me connecter afin de retrouver mes données de conformité.
Priorité : P0
Traçabilité PRD : FR-AUTH-002
Critères d'acceptation :
```text
Given je possède un compte existant
When je saisis des identifiants valides
Then j'accède à mon compte et à mes données précédemment enregistrées.
```
```text
Given je saisis des identifiants invalides
When je tente de me connecter
Then le système m'indique que la connexion a échoué
And ne révèle pas si c'est l'identifiant ou le mot de passe qui est erroné (cas limite de sécurité — à confirmer dans `10-security-privacy.md`).
```

**US-AUTH-003**
Epic : EPIC-AUTH — Persona : Utilisateur (tous)
Titre : Récupérer l'accès à mon compte
En tant qu'utilisateur ayant perdu l'accès à mon compte, je veux pouvoir le récupérer afin de ne pas perdre mon historique de conformité.
Priorité : P0
Traçabilité PRD : FR-AUTH-002
Critères d'acceptation :
```text
Given j'ai perdu l'accès à mon compte
When j'engage une procédure de récupération
Then je peux recréer un accès valide à mon compte existant
And mes données précédentes restent intactes.
```

> **Note (résolue)** : le PRD (section 9) mentionne la vérification d'email comme un mécanisme « repoussé à une itération ultérieure sauf exigence de sécurité contraire ». Décision produit retenue (voir section 18, Questions ouvertes) : la vérification d'email est **obligatoire avant les fonctionnalités sensibles** (upload de document, analyses persistantes, usage de l'IA), mais **pas nécessairement bloquante avant toute utilisation basique du compte**. Aucune User Story de vérification d'email n'est donc créée en P0 ici — un compte peut être créé et utilisé de façon limitée sans email vérifié ; la restriction d'accès aux fonctionnalités sensibles est un comportement transverse plutôt qu'une User Story dédiée, à détailler dans `10-security-privacy.md`.

### Epic EPIC-ONBOARDING

**US-ONBOARDING-001**
Epic : EPIC-ONBOARDING — Persona : Utilisateur (tous)
Titre : Être guidé après la création de mon compte
En tant que nouvel utilisateur, je veux être guidé immédiatement vers la configuration de mon entreprise afin d'obtenir rapidement un premier résultat utile (le diagnostic d'éligibilité).
Priorité : P0
Dépendances : US-AUTH-001
Traçabilité PRD : parcours 1-2 (section 12)
Critères d'acceptation :
```text
Given je viens de créer mon compte
When j'arrive pour la première fois sur le produit
Then je suis orienté vers la configuration de mon entreprise (US-COMPANY-001)
And je comprends que cette étape est nécessaire pour obtenir un diagnostic.
```

### Epic EPIC-COMPANY

**US-COMPANY-001**
Epic : EPIC-COMPANY — Persona : Utilisateur (tous)
Titre : Renseigner le statut TVA de mon entreprise
En tant qu'utilisateur, je veux indiquer si mon entreprise est assujettie à la TVA, redevable, ou en franchise en base, afin que le système détermine correctement si je suis concerné par la réforme.
Priorité : P0
Traçabilité PRD : FR-COMPANY-001 ; source réglementaire `02-regulatory-study.md`, section 6.
Critères d'acceptation :
```text
Given je configure mon entreprise pour la première fois
When je renseigne mon statut TVA, y compris si je suis en franchise en base
Then le système enregistre ce statut
And ne m'exclut jamais automatiquement du diagnostic au seul motif que je suis en franchise en base (BR-ELIGIBILITY-001 du PRD).
```
Cas limite : une entreprise en franchise en base qui pense, comme le persona 2, ne pas être concernée doit voir cette hypothèse explicitement contredite par le système avec une explication (voir US-COMPLIANCE-002).

**US-COMPANY-002**
Epic : EPIC-COMPANY — Persona : Utilisateur (tous)
Titre : Renseigner la taille de mon entreprise
En tant qu'utilisateur, je veux indiquer la taille de mon entreprise afin que le système détermine la date à partir de laquelle je dois émettre des factures électroniques conformes.
Priorité : P0
Traçabilité PRD : FR-COMPANY-002 ; source réglementaire `02-regulatory-study.md`, section 5.
Critères d'acceptation :
```text
Given j'ai renseigné mon statut TVA
When je renseigne mon effectif salarié, mon chiffre d'affaires annuel et le total de mon bilan annuel
Then le système détermine ma catégorie de taille et peut établir la date d'obligation d'émission qui m'est applicable (1er septembre 2026 ou 1er septembre 2027 selon le cas, `02-regulatory-study.md` section 5).
```
> **Ambiguïté résolue** : les données précises demandées à l'utilisateur sont désormais actées — effectif salarié (`employees_count`), chiffre d'affaires annuel (`annual_turnover`), total du bilan annuel (`annual_balance_sheet_total`), cohérent avec les critères de catégorisation d'entreprise de l'INSEE. **Point d'attention à conserver dans le contenu pédagogique du produit** : une micro-entreprise au sens fiscal (régime micro-fiscal/micro-social) n'est pas automatiquement synonyme de la catégorie statistique « microentreprise » utilisée par l'INSEE pour ces mêmes critères — l'INSEE fait explicitement cette distinction, et le produit doit éviter de laisser croire à une équivalence directe entre les deux notions. Voir `07-data-model.md` (section 7, mise à jour) pour la traduction en modèle de données.

**US-COMPANY-003**
Epic : EPIC-COMPANY — Persona : Utilisateur (tous)
Titre : Modifier les informations de mon entreprise
En tant qu'utilisateur, je veux pouvoir modifier les informations de mon entreprise (statut TVA, taille) afin de refléter un changement de ma situation.
Priorité : P1
Traçabilité PRD : conséquence logique de FR-COMPANY-001/002, non explicitement une exigence distincte du PRD, cohérente avec le fait que le statut d'une entreprise peut évoluer (par exemple, franchissement de seuil, mentionné comme limitation en PRD section 28). **Confirmée au MVP (P1) par décision produit** : cette story est conservée car elle concerne une configuration indispensable à la conformité (voir section 18, Questions ouvertes).
Critères d'acceptation :
```text
Given mon entreprise est déjà configurée
When je modifie mon statut TVA ou ma taille
Then le système recalcule mon diagnostic d'éligibilité en conséquence
And les analyses de conformité passées conservent la trace du contexte qui était le leur au moment de leur réalisation (cohérence avec l'auditabilité, PRD section 24).
```

### Epic EPIC-CUSTOMERS

**US-CUSTOMER-001**
Epic : EPIC-CUSTOMERS — Persona : Utilisateur (tous)
Titre : Renseigner le statut de mon client pour une facture à analyser
En tant qu'utilisateur, je veux indiquer si mon client est un professionnel français, un particulier, ou un client étranger, afin que le système sache quelles règles appliquer à la facture correspondante.
Priorité : P0
Traçabilité PRD : section 9 (module Clients), lié à FR-INVOICE-001 ; source réglementaire `02-regulatory-study.md`, section 7.
Critères d'acceptation :
```text
Given je m'apprête à faire analyser une facture
When je renseigne le statut de mon client (professionnel français / particulier / étranger)
Then le système adapte les vérifications de conformité effectuées à ce statut (par exemple, e-invoicing pour un professionnel français, e-reporting pour un particulier — `02-regulatory-study.md` section 7).
```

**US-CUSTOMER-002**
Epic : EPIC-CUSTOMERS — Persona : Utilisateur (tous)
Titre : Renseigner le SIREN d'un client professionnel français
En tant qu'utilisateur, je veux pouvoir renseigner le SIREN de mon client professionnel français afin que le système puisse vérifier la présence de cette mention désormais obligatoire.
Priorité : P0
Traçabilité PRD : lié à FR-COMPLIANCE-002 ; source réglementaire `02-regulatory-study.md`, section 10 (nouvelle mention obligatoire depuis le 1er septembre 2026).
Critères d'acceptation :
```text
Given mon client est identifié comme professionnel français
When je ne renseigne pas son SIREN
Then le système signale cette absence comme une vérification en attente (état A_VERIFIER, PRD section 10), pas comme une non-conformité automatique.
```

**US-CUSTOMER-003** *(P2 — proposition à valider)*
Epic : EPIC-CUSTOMERS — Persona : Utilisateur SB
Titre : Gérer plusieurs clients enregistrés
En tant qu'utilisateur ayant plusieurs clients récurrents, je veux pouvoir enregistrer et réutiliser les informations de mes clients afin de ne pas les ressaisir à chaque analyse.
Priorité : P2
Traçabilité PRD : le PRD section 9 indique explicitement qu'« une gestion complète (catégorisation avancée, historique par client) n'est pas indispensable au MVP » — cette story reste donc P2, cohérente avec cette réserve.

### Epic EPIC-INVOICES / EPIC-DOCUMENTS

**US-INVOICE-001**
Epic : EPIC-INVOICES — Persona : Utilisateur (tous)
Titre : Importer un document représentant une facture existante
En tant qu'utilisateur, je veux importer un document (par exemple un PDF) représentant une facture que j'ai déjà émise ou que je m'apprête à émettre, afin de le faire analyser.
Priorité : P0
Traçabilité PRD : FR-INVOICE-001, section 16.
Critères d'acceptation :
```text
Given je dispose d'un document représentant une facture
When j'importe ce document dans un format supporté
Then le document est associé à mon compte et disponible pour analyse (US-COMPLIANCE-001).
```
```text
Given le document que j'importe est dans un format non supporté ou illisible
When je tente de l'importer
Then le système m'indique clairement que le document ne peut pas être traité techniquement
And me propose de saisir manuellement les informations à la place (US-INVOICE-002)
And cette erreur est présentée comme une erreur technique, jamais comme un problème de conformité (PRD, section 15).
```

**US-INVOICE-002**
Epic : EPIC-INVOICES — Persona : Utilisateur (tous)
Titre : Saisir manuellement les informations d'une facture
En tant qu'utilisateur, je veux pouvoir saisir manuellement les informations d'une facture (plutôt que d'importer un document) afin de la faire analyser même si je ne dispose pas d'un fichier exploitable.
Priorité : P0
Traçabilité PRD : FR-INVOICE-001.
Critères d'acceptation :
```text
Given je ne dispose pas d'un document à importer, ou l'import a échoué
When je saisis manuellement les informations de ma facture (mentions, montants, client)
Then ces informations sont disponibles pour analyse (US-COMPLIANCE-001) au même titre qu'un document importé.
```

**US-DOCUMENT-001**
Epic : EPIC-DOCUMENTS — Persona : Utilisateur (tous)
Titre : Consulter un document précédemment importé
En tant qu'utilisateur, je veux retrouver un document que j'ai importé précédemment afin de vérifier son contenu ou l'analyse qui lui est associée.
Priorité : P1
Traçabilité PRD : section 16, lié à US-HISTORY (EPIC-DASHBOARD).

**US-DOCUMENT-002**
Epic : EPIC-DOCUMENTS — Persona : Utilisateur (tous)
Titre : Supprimer un document importé
En tant qu'utilisateur, je veux pouvoir supprimer un document que j'ai importé afin de contrôler les données que je conserve dans le produit.
Priorité : P1
Traçabilité PRD : section 16.
Critères d'acceptation :
```text
Given j'ai importé un document
When je demande sa suppression
Then le fichier original est supprimé
And les données extraites de ce document sont supprimées ou anonymisées si elles contiennent des données personnelles ou sensibles et ne sont plus nécessaires
And l'historique de l'analyse de conformité associée est conservé sous une forme minimale (résultat, règle et version appliquées, date), avec une mention explicite indiquant que le document source a été supprimé (cohérence avec l'auditabilité, PRD section 24).
```

> **Ambiguïté résolue** : la suppression d'un document supprime le fichier original et les données extraites non nécessaires, mais **conserve** l'historique de l'analyse de conformité sous une forme minimale — ce comportement concilie le droit de l'utilisateur à supprimer ses données et l'exigence d'auditabilité du PRD (section 24). Voir `07-data-model.md` (section 30, mise à jour) et `10-security-privacy.md` (section 39, mise à jour) pour la traduction en modèle de données et en exigence de sécurité.

### Epic EPIC-COMPLIANCE

**US-COMPLIANCE-001** *(cœur du produit)*
Epic : EPIC-COMPLIANCE — Persona : Utilisateur (tous)
Titre : Obtenir un diagnostic d'éligibilité à la réforme
En tant qu'utilisateur, je veux savoir si mon entreprise est concernée par la réforme de la facturation électronique et à partir de quand, afin de comprendre si et quand je dois agir.
Priorité : P0
Dépendances : US-COMPANY-001, US-COMPANY-002
Traçabilité PRD : FR-DIAGNOSTIC-001 ; réglementaire `02-regulatory-study.md`, section 5-6.
Critères d'acceptation :
```text
Given mon entreprise est configurée avec son statut TVA et sa taille
When je demande un diagnostic d'éligibilité
Then le système m'indique si je suis concerné par l'obligation de réception
And m'indique à partir de quelle date je suis concerné par l'obligation d'émission
And m'indique la source réglementaire de cette information (PRD, section 18).
```
```text
Given je suis en franchise en base de TVA
When je consulte mon diagnostic
Then le système m'explique explicitement que je reste concerné malgré mon statut de franchise en base, avec l'explication correspondante (`02-regulatory-study.md` section 6).
```

**US-COMPLIANCE-002**
Epic : EPIC-COMPLIANCE — Persona : Utilisateur (tous)
Titre : Analyser une facture importée ou saisie
En tant qu'utilisateur, je veux lancer une analyse de conformité sur une facture que j'ai importée ou saisie, afin de savoir si elle respecte les règles qui s'appliquent à ma situation.
Priorité : P0
Dépendances : US-COMPANY-001, US-CUSTOMER-001, US-INVOICE-001 ou US-INVOICE-002
Traçabilité PRD : FR-COMPLIANCE-001.
Critères d'acceptation :
```text
Given une facture importée ou saisie et un contexte d'entreprise/client renseigné
When je lance l'analyse de conformité
Then le système retourne un statut global
And le détail de chaque vérification individuelle effectuée
And la date à laquelle l'analyse a été réalisée (PRD section 18).
```
```text
Given des données nécessaires à une vérification particulière sont manquantes
When j'analyse ma facture
Then cette vérification obtient l'état A_VERIFIER
And n'est jamais automatiquement considérée comme NON_CONFORME (BR-COMPLIANCE-003 du PRD).
```

**US-COMPLIANCE-003**
Epic : EPIC-COMPLIANCE — Persona : Utilisateur (tous)
Titre : Comprendre pourquoi une vérification n'est pas conforme
En tant qu'utilisateur, je veux comprendre, en langage clair, pourquoi une vérification est signalée comme non conforme, en avertissement ou incertaine, afin de savoir ce qui doit changer.
Priorité : P0
Dépendances : US-COMPLIANCE-002
Traçabilité PRD : FR-COMPLIANCE-002 ; différenciation produit centrale (`03-market-analysis.md`, section 18).
Critères d'acceptation :
```text
Given une vérification a un statut NON_CONFORME, AVERTISSEMENT, A_VERIFIER ou INCERTAIN_REGLEMENTAIRE
When je consulte le détail de cette vérification
Then le système m'explique la règle appliquée en langage courant
And m'indique la source réglementaire associée
And si l'état est INCERTAIN_REGLEMENTAIRE, m'indique explicitement que ce point n'est pas confirmé avec certitude (PRD, DEC-004).
```

**US-COMPLIANCE-004**
Epic : EPIC-COMPLIANCE — Persona : Utilisateur (tous)
Titre : Obtenir une action de correction concrète
En tant qu'utilisateur, je veux qu'on me propose une action concrète pour corriger un problème détecté, afin de ne pas rester bloqué face à une explication seule.
Priorité : P0
Dépendances : US-COMPLIANCE-003
Traçabilité PRD : FR-COMPLIANCE-003.
Critères d'acceptation :
```text
Given une vérification NON_CONFORME
When je consulte son détail
Then le système me propose une action concrète et actionnable permettant de résoudre le problème (par exemple : « ajoutez le SIREN de votre client »).
```

**US-COMPLIANCE-005** *(point de différenciation majeur identifié en étude de marché)*
Epic : EPIC-COMPLIANCE — Persona : Utilisateur P (principalement)
Titre : Comprendre pourquoi mon PDF/email n'est pas une facture électronique conforme
En tant qu'utilisateur qui pense que sa pratique actuelle (PDF envoyé par email) suffit, je veux comprendre précisément pourquoi ce n'est pas le cas, afin de ne pas être pris au dépourvu à l'échéance qui me concerne.
Priorité : P0
Traçabilité PRD : FR-COMPLIANCE-004 ; réglementaire `02-regulatory-study.md`, section 8 ; justifié par la confusion documentée dans `03-market-analysis.md`, section 14 (environ 4 indépendants sur 10 confondent e-facture et simple PDF).
Critères d'acceptation :
```text
Given j'importe un document de type PDF simple, non structuré
When je le soumets pour analyse
Then le système m'explique explicitement que ce document ne constitue pas une facture électronique conforme au sens de la réforme
And m'explique la différence entre un document informel et une facture électronique structurée (`02-regulatory-study.md` section 8)
And cette explication apparaît même si le PDF contient par ailleurs toutes les mentions obligatoires attendues.
```

**US-COMPLIANCE-006**
Epic : EPIC-COMPLIANCE — Persona : Utilisateur (tous)
Titre : Relancer une analyse après correction
En tant qu'utilisateur ayant corrigé les informations d'une facture, je veux relancer l'analyse afin de vérifier que le problème est résolu.
Priorité : P0
Dépendances : US-COMPLIANCE-002, US-COMPLIANCE-004
Traçabilité PRD : parcours 5 (section 12).
Critères d'acceptation :
```text
Given une facture précédemment analysée comme non conforme
When je corrige les informations concernées et relance l'analyse
Then le système produit un nouveau résultat
And les résultats précédent et nouveau restent tous deux consultables dans l'historique (US-HISTORY-001), sans que le nouveau résultat n'efface la trace de l'ancien (cohérence avec l'auditabilité, PRD section 24).
```

**US-COMPLIANCE-006bis** *(ajoutée — décision produit)*
Epic : EPIC-COMPLIANCE — Persona : Utilisateur (tous)
Titre : Être averti qu'une facture modifiée nécessite une nouvelle analyse
En tant qu'utilisateur, je veux être clairement informé que le résultat de conformité affiché n'est plus à jour lorsque je modifie une facture déjà analysée, afin de ne jamais me fier à un résultat obsolète.
Priorité : P0
Dépendances : US-COMPLIANCE-002, US-INVOICE (modification)
Traçabilité PRD : conséquence directe de la question ouverte résolue en section 18 — une facture modifiée après analyse ne crée pas une nouvelle version de facture, mais fait passer le résultat existant à un état explicite d'obsolescence.
Critères d'acceptation :
```text
Given une facture au statut ANALYZED
When je modifie une information de cette facture (client, lignes, montants)
Then le statut de la facture passe explicitement à un état "analyse obsolète" (ANALYSIS_STALE)
And l'interface m'indique clairement que le résultat de conformité affiché ne reflète plus l'état actuel de la facture
And je suis invité à relancer une nouvelle analyse (US-COMPLIANCE-006) avant de considérer la facture comme vérifiée.
```

**US-COMPLIANCE-007** *(P2 — cas limite documenté dans l'étude réglementaire)*
Epic : EPIC-COMPLIANCE — Persona : Utilisateur P, SB
Titre : Gérer une facture concernant une clientèle mixte
En tant qu'utilisateur ayant à la fois des clients professionnels et des particuliers, je veux que le système applique les bonnes règles selon le statut de chaque client, afin de ne pas confondre e-invoicing et e-reporting.
Priorité : P2
Traçabilité PRD : FR-MIXED-001 ; réglementaire `02-regulatory-study.md`, section 18, scénario C.
Critères d'acceptation :
```text
Given une facture destinée à un client particulier
When j'analyse cette facture
Then le système applique les règles d'e-reporting et non celles d'e-invoicing (`02-regulatory-study.md` section 7)
And m'explique cette distinction si je m'attendais à une vérification d'e-invoicing.
```

**US-COMPLIANCE-008** *(P2 — proposition dérivée du PRD, non explicitement une US du PRD)*
Epic : EPIC-COMPLIANCE — Persona : Utilisateur SB
Titre : Signaler un désaccord avec un résultat de conformité
En tant qu'utilisateur, je veux pouvoir signaler que je ne suis pas d'accord avec un résultat, afin de contribuer à la fiabilité du produit et d'obtenir une clarification.
Priorité : P2 (le PRD, section 8, classait initialement FR-TRUST-001 en priorité « Future » ; **décision produit : reclassée P2, hors MVP** — voir section 18, Questions ouvertes)
Traçabilité PRD : FR-TRUST-001, section 18. Piste retenue pour une version ultérieure : mécanisme de feedback porté par `ComplianceFinding`, à concevoir avec `07-data-model.md`.

### Epic EPIC-AI-ASSISTANT

**US-AI-001**
Epic : EPIC-AI-ASSISTANT — Persona : Utilisateur (tous)
Titre : Obtenir une reformulation pédagogique d'un résultat de conformité
En tant qu'utilisateur non spécialiste, je veux que l'explication d'une non-conformité me soit présentée dans un langage simple et adapté à ma situation, afin de la comprendre sans connaissance juridique ou technique.
Priorité : P1
Traçabilité PRD : section 17 (« Ce que l'IA peut faire »).
Critères d'acceptation :
```text
Given un résultat de vérification produit par le moteur de conformité déterministe
When ce résultat m'est présenté
Then la reformulation proposée reste strictement fidèle au résultat et à la règle déterminés par le moteur de conformité (PRD, DEC-002)
And n'introduit aucune information, obligation ou certitude qui ne proviendrait pas de ce résultat.
```

**US-AI-002**
Epic : EPIC-AI-ASSISTANT — Persona : Utilisateur (tous)
Titre : Poser une question générale de compréhension
En tant qu'utilisateur, je veux pouvoir poser une question simple (par exemple « qu'est-ce qu'un SIREN ? ») afin de mieux comprendre le contexte d'une vérification.
Priorité : P1
Traçabilité PRD : section 17.
Critères d'acceptation :
```text
Given je consulte le détail d'une vérification
When je pose une question générale de compréhension
Then la réponse s'appuie sur le contenu de 02-regulatory-study.md
And n'affirme jamais un fait avec plus de certitude que ce document ne le permet (par exemple, un point marqué « à confirmer » dans l'étude réglementaire ne doit jamais être présenté comme certain — PRD section 17).
```

> **Rappel explicite (non une User Story, mais une contrainte transverse à toute l'Epic EPIC-AI-ASSISTANT)** : conformément au PRD (section 17, section 20 du brief), aucune User Story de cette Epic ne doit permettre à l'IA de déterminer elle-même si une facture est conforme. Toute proposition de fonctionnalité de ce type (par exemple, un « chatbot » répondant librement à « est-ce que ma facture est conforme ? ») devrait obligatoirement s'appuyer sur le résultat déjà produit par US-COMPLIANCE-002, jamais générer un verdict de façon autonome.

### Epic EPIC-DASHBOARD

**US-DASHBOARD-001**
Epic : EPIC-DASHBOARD — Persona : Utilisateur SB (principalement)
Titre : Consulter une vue d'ensemble de mon état de conformité
En tant qu'utilisateur, je veux consulter une vue synthétique de mon état de conformité déclaré, afin de savoir rapidement si je dois agir.
Priorité : P1
Traçabilité PRD : FR-DASHBOARD-001, section 20.
Critères d'acceptation :
```text
Given j'ai effectué au moins une analyse de conformité
When j'accède au dashboard
Then je vois un état global déclaré (dérivé de mes dernières analyses)
And la liste des problèmes non résolus
And la liste des avertissements en cours
And les actions recommandées correspondantes.
```
```text
Given je n'ai encore effectué aucune analyse
When j'accède au dashboard
Then le système m'oriente vers le diagnostic d'éligibilité (US-COMPLIANCE-001) ou l'analyse d'une première facture (US-COMPLIANCE-002), plutôt que d'afficher un état vide sans action proposée.
```

**US-HISTORY-001**
Epic : EPIC-DASHBOARD — Persona : Utilisateur (tous)
Titre : Consulter l'historique de mes analyses
En tant qu'utilisateur, je veux retrouver mes analyses de conformité passées, afin de suivre l'évolution de ma situation ou de justifier une décision passée.
Priorité : P1
Traçabilité PRD : FR-HISTORY-001, section 24 (audit et historique).
Critères d'acceptation :
```text
Given j'ai effectué plusieurs analyses dans le temps
When je consulte mon historique
Then je retrouve chaque analyse avec sa date, son résultat, et la règle/version appliquée à ce moment-là (PRD, section 24)
And je peux répondre à la question « pourquoi cette facture était-elle considérée comme non conforme le [date] ? ».
```

### Epic EPIC-NOTIFICATIONS

**US-NOTIFICATION-001** *(P2 — analysé par le PRD comme raisonnable pour V1, non P0)*
Epic : EPIC-NOTIFICATIONS — Persona : Utilisateur (tous)
Titre : Être notifié de l'échéance qui me concerne
En tant qu'utilisateur, je veux être informé de la date à partir de laquelle je devrai émettre des factures électroniques conformes, afin de ne pas être pris au dépourvu.
Priorité : P2
Traçabilité PRD : section 19 (« candidat raisonnable pour V1, non P0 au MVP »).
Critères d'acceptation :
```text
Given mon diagnostic d'éligibilité a déterminé une date d'obligation d'émission
When cette échéance approche
Then je reçois une notification me le rappelant.
```

**US-NOTIFICATION-002** *(FUTURE)*
Epic : EPIC-NOTIFICATIONS — Persona : Utilisateur (tous)
Titre : Être notifié d'un changement réglementaire affectant une règle déjà appliquée
Priorité : FUTURE — le PRD indique explicitement que cette fonctionnalité « suppose un mécanisme de veille et de versionnement déjà mature » et la classe en Future Scope (section 19).

### Epic EPIC-SETTINGS

**US-SETTINGS-001**
Epic : EPIC-SETTINGS — Persona : Utilisateur (tous)
Titre : Gérer les informations de mon compte
En tant qu'utilisateur, je veux pouvoir consulter et modifier les informations de mon compte, afin de les garder à jour.
Priorité : P1
Traçabilité PRD : conséquence logique d'EPIC-AUTH, non détaillée telle quelle dans le PRD. **Priorité confirmée par décision produit** : P1, probablement hors MVP au sens strict (voir section 18, Questions ouvertes) — le MVP reste centré sur le parcours entreprise → facture → analyse → correction.

**US-SETTINGS-002**
Epic : EPIC-SETTINGS — Persona : Utilisateur (tous)
Titre : Supprimer mon compte et mes données
En tant qu'utilisateur, je veux pouvoir supprimer mon compte et mes données, afin de garder le contrôle sur les informations que je confie au produit.
Priorité : P1
Traçabilité PRD : cohérent avec le principe de minimisation des données (PRD, section 14 — Confidentialité). **Priorité confirmée par décision produit** : P1/P2, probablement hors MVP au sens strict (voir section 18, Questions ouvertes) ; les modalités précises de suppression restent à détailler dans `10-security-privacy.md`.

### Epic EPIC-ADMINISTRATION (fonction interne, non utilisateur)

Le PRD (section 21) exclut explicitement une gestion de rôles multiples au MVP ; il n'y a donc **aucune User Story orientée utilisateur final** dans cette Epic. Elle couvre uniquement un besoin interne nécessaire au fonctionnement du produit, distinct de toute fonctionnalité accessible à l'utilisateur (voir section 26).

## 7. Parcours utilisateurs

Les parcours détaillés (onboarding, configuration entreprise, client, facture, conformité) sont couverts par les User Stories des sections précédentes (US-ONBOARDING-001 à US-COMPLIANCE-006 notamment) et par la User Story Map (section 3). Le détail pas-à-pas de chaque étape suit directement l'enchaînement de dépendances documenté en section 6 pour chaque story, sans qu'il soit nécessaire de le dupliquer ici.

## 8. États de conformité

Repris tels que définis dans le PRD (section 10), sans modification :

| État | Définition | Comportement utilisateur | Actions possibles |
|---|---|---|---|
| `CONFORME` | La règle est respectée | Affiché comme validé, sans action requise | Consulter le détail si souhaité |
| `NON_CONFORME` | La règle n'est pas respectée | Affiché comme problème à corriger, avec explication et action (US-COMPLIANCE-003, 004) | Corriger puis relancer l'analyse (US-COMPLIANCE-006) |
| `AVERTISSEMENT` | Probablement respectée, mais un point mérite attention | Affiché distinctement d'un `NON_CONFORME`, avec explication | Vérifier, corriger si pertinent |
| `NON_APPLICABLE` | La règle ne s'applique pas à ce contexte | Affiché comme non pertinent pour cette facture | Aucune action |
| `A_VERIFIER` | Données insuffisantes pour conclure | Affiché comme incomplet, jamais comme non conforme (BR-COMPLIANCE-003) | Compléter les informations manquantes |
| `INCERTAIN_REGLEMENTAIRE` | La règle elle-même repose sur un point signalé comme non confirmé dans `02-regulatory-study.md` | Affiché avec une mention explicite d'incertitude réglementaire | Consulter la source, éventuellement vérifier auprès d'un professionnel |

Un état supplémentaire, non un état de *résultat* mais un état de *traitement*, est nécessaire pour couvrir le cycle de vie complet d'une analyse (implicite dans le parcours du PRD mais non nommé explicitement) :

| État | Définition |
|---|---|
| `NON_ANALYSEE` | La facture a été importée/saisie mais aucune analyse n'a encore été lancée |
| `ANALYSE_EN_COURS` | L'analyse est en cours de traitement (pertinent si le traitement n'est pas instantané — PRD section 14, Performance) |

> Ces deux derniers états sont une **proposition dérivée du besoin exprimé en PRD section 14** (traitement potentiellement asynchrone), pas une exigence explicitement nommée dans le PRD. Ils sont signalés ici comme cohérents avec le PRD plutôt que comme une exigence déjà actée.

## 9. Gravité des problèmes

Le PRD ne définit pas de classification de gravité distincte des états de conformité eux-mêmes (section 10 du PRD utilise les états comme seule classification). Plutôt que d'inventer une classification de gravité parallèle non prévue par le PRD, ce document retient que la **gravité découle directement de l'état de conformité** :

| État | Niveau de gravité correspondant |
|---|---|
| `NON_CONFORME` | Équivalent à « critique/erreur » — nécessite une correction |
| `AVERTISSEMENT` | Équivalent à « avertissement » — mérite attention sans bloquer |
| `A_VERIFIER`, `INCERTAIN_REGLEMENTAIRE` | Équivalent à « information/à vérifier » — ni une erreur, ni une confirmation |
| `CONFORME`, `NON_APPLICABLE` | Aucune gravité — pas d'action requise |

Cette approche évite de créer une double taxonomie (états + gravités indépendantes) qui ajouterait de la complexité non justifiée par le PRD.

## 10. Use Cases critiques

**UC-COMPLIANCE-001**
Nom : Analyser une facture
Acteur principal : Utilisateur (propriétaire du compte)
Préconditions : l'entreprise est configurée (statut TVA, taille) ; le client de la facture est renseigné (statut) ; une facture a été importée ou saisie.
Déclencheur : l'utilisateur demande le lancement de l'analyse.
Scénario principal :
1. Le système identifie le contexte (statut/taille de l'entreprise, statut du client, nature de l'opération).
2. Le système détermine les règles applicables à ce contexte (`02-regulatory-study.md`, section 20).
3. Le système vérifie chaque règle applicable par rapport aux données disponibles.
4. Le système produit un résultat par vérification (état parmi ceux de la section 8) et un statut global.
5. Le système associe à chaque résultat non conforme, en avertissement ou incertain une explication et une action de correction.
6. Le système enregistre l'analyse dans l'historique avec sa date et la version des règles appliquées.
7. Le résultat est présenté à l'utilisateur.

Scénarios alternatifs :
- A1 : une donnée nécessaire à une vérification est absente → cette vérification obtient l'état `A_VERIFIER` (étape 3 modifiée), le reste du scénario se poursuit normalement pour les autres vérifications.
- A2 : le document importé est illisible → l'analyse ne peut pas être lancée ; le système propose la saisie manuelle (US-INVOICE-002) avant de revenir à l'étape 1.

Exceptions :
- E1 : une règle applicable est marquée comme incertaine dans `02-regulatory-study.md` → le résultat correspondant est `INCERTAIN_REGLEMENTAIRE`, jamais `CONFORME` ou `NON_CONFORME` (BR-COMPLIANCE-004 du PRD).

Postconditions : un résultat de conformité est disponible et consultable, associé à la facture analysée et conservé dans l'historique.

Règles métier : BR-COMPLIANCE-001 à 004, BR-ELIGIBILITY-001, BR-SCOPE-001 (PRD, section 25).

User Stories associées : US-COMPLIANCE-002, US-COMPLIANCE-003, US-COMPLIANCE-004, US-COMPLIANCE-005.

**UC-COMPLIANCE-002**
Nom : Obtenir un diagnostic d'éligibilité
Acteur principal : Utilisateur (propriétaire du compte)
Préconditions : le statut TVA et la taille de l'entreprise sont renseignés.
Déclencheur : l'utilisateur demande son diagnostic (à l'onboarding ou ultérieurement).
Scénario principal :
1. Le système récupère le statut TVA et la taille de l'entreprise.
2. Le système détermine si l'entreprise est concernée par l'obligation de réception et à partir de quand.
3. Le système détermine si l'entreprise est concernée par l'obligation d'émission et à partir de quand, selon sa taille.
4. Le système présente ce diagnostic avec les sources réglementaires associées (`02-regulatory-study.md`, section 5).

Scénarios alternatifs :
- A1 : l'entreprise est en franchise en base de TVA → le système inclut explicitement une explication rappelant qu'elle reste concernée (BR-ELIGIBILITY-001).

Postconditions : l'utilisateur dispose d'une réponse claire à « suis-je concerné, et à partir de quand ? ».

User Stories associées : US-COMPLIANCE-001.

## 11. Scénarios réglementaires

| Scénario | Contexte | Règles pertinentes (`02-regulatory-study.md`) | User Stories concernées |
|---|---|---|---|
| B2B France | Client professionnel français assujetti à la TVA | Section 7 (e-invoicing) ; section 10 (mentions obligatoires) | US-CUSTOMER-001, US-CUSTOMER-002, US-COMPLIANCE-002 à 004 |
| B2C | Client particulier | Section 7, section 15 (e-reporting) | US-CUSTOMER-001, US-COMPLIANCE-007 |
| International | Client étranger (UE ou hors UE) | Section 7, section 15 (e-reporting) | US-CUSTOMER-001, US-COMPLIANCE-007 |
| Clientèle mixte | Entreprise ayant à la fois des clients professionnels français et des particuliers/étrangers | Section 18, scénario C | US-COMPLIANCE-007 |
| Franchise en base de TVA | Micro-entrepreneur assujetti mais non redevable | Section 6, section 18, scénario A | US-COMPANY-001, US-COMPLIANCE-001 |
| Document non structuré (PDF/email) | Toute entreprise pensant être déjà conforme avec un PDF | Section 8 | US-COMPLIANCE-005 |
| Opération exonérée de TVA | Hors champ de l'e-invoicing et de l'e-reporting | Section 6, section 7 | US-COMPLIANCE-002 (état `NON_APPLICABLE`) |

## 12. Matrice de traçabilité

| Problème (PRD, section 5) | Exigence PRD | Epic | User Story | Critère d'acceptation associé |
|---|---|---|---|---|
| PB-01 (ne sait pas s'il est concerné) | FR-DIAGNOSTIC-001 | EPIC-COMPLIANCE | US-COMPLIANCE-001 | Diagnostic avec date d'obligation |
| PB-02 (confond PDF et facture électronique) | FR-COMPLIANCE-004 | EPIC-COMPLIANCE | US-COMPLIANCE-005 | Explication explicite PDF non conforme |
| PB-03 (ne connaît pas les nouvelles mentions) | FR-COMPLIANCE-001/002 | EPIC-COMPLIANCE, EPIC-CUSTOMERS | US-COMPLIANCE-002, US-CUSTOMER-002 | Vérification des mentions, état A_VERIFIER si absent |
| PB-04 (confusion assujetti/redevable) | FR-COMPANY-001 | EPIC-COMPANY | US-COMPANY-001 | Franchise en base non exclue du diagnostic |
| PB-05 (clientèle mixte) | FR-MIXED-001 | EPIC-COMPLIANCE | US-COMPLIANCE-007 | Application distincte e-invoicing/e-reporting |
| PB-06 (pas de vision d'ensemble) | FR-DASHBOARD-001, FR-HISTORY-001 | EPIC-DASHBOARD | US-DASHBOARD-001, US-HISTORY-001 | Vue synthétique, historique daté |
| PB-07 (évolution des règles) | Section 19 du PRD | EPIC-NOTIFICATIONS | US-NOTIFICATION-002 | (Future — non couvert au MVP) |

## 13. Traçabilité réglementaire

| User Story | Source réglementaire |
|---|---|
| US-COMPANY-001 | `02-regulatory-study.md`, section 6 (assujettissement même en franchise en base) |
| US-COMPANY-002 | `02-regulatory-study.md`, section 5 (calendrier différencié par taille) |
| US-CUSTOMER-001 | `02-regulatory-study.md`, section 7 (types d'opérations) |
| US-CUSTOMER-002 | `02-regulatory-study.md`, section 10 (nouvelle mention SIREN client) |
| US-COMPLIANCE-001 | `02-regulatory-study.md`, sections 5 et 6 |
| US-COMPLIANCE-005 | `02-regulatory-study.md`, section 8 (définition de la facture électronique) |
| US-COMPLIANCE-007 | `02-regulatory-study.md`, section 18, scénario C |

## 14. Definition of Ready

Une User Story est prête à entrer en développement lorsqu'elle possède :
- un objectif clair et une valeur exprimée pour un persona identifié ;
- une priorité (P0/P1/P2/FUTURE) ;
- des critères d'acceptation testables au format Given/When/Then ;
- ses dépendances identifiées (section 15 de ce document) ;
- les règles métier ou réglementaires associées explicitement référencées (section 12, 13) ;
- toute ambiguïté signalée dans ce document (voir section 18) explicitement résolue par une décision documentée, plutôt que laissée à l'appréciation de la personne qui développe.

## 15. Definition of Done

Une User Story est considérée comme terminée lorsque :
- la fonctionnalité correspond aux critères d'acceptation définis ;
- les cas d'erreur et cas limites identifiés pour cette story ont été pris en compte, pas seulement le scénario nominal ;
- une revue a été effectuée par au moins une personne autre que celle ayant développé la fonctionnalité (ou, à défaut pour un développeur solo, une relecture différée à froid) ;
- la traçabilité vers le PRD et, le cas échéant, vers `02-regulatory-study.md` reste vérifiable ;
- aucune régression connue n'est introduite sur les User Stories déjà terminées.

## 16. Dépendances entre User Stories

```text
US-AUTH-001
     ↓
US-ONBOARDING-001
     ↓
US-COMPANY-001 → US-COMPANY-002
     ↓
US-COMPLIANCE-001 (diagnostic d'éligibilité)
     ↓
US-CUSTOMER-001 → US-CUSTOMER-002
     ↓
US-INVOICE-001 / US-INVOICE-002
     ↓
US-COMPLIANCE-002 (analyse)
     ↓
US-COMPLIANCE-003 → US-COMPLIANCE-004 → US-COMPLIANCE-006 (correction/relance)
     ↓
US-HISTORY-001 → US-DASHBOARD-001
```

Dépendances transverses (non séquentielles) :
- US-COMPLIANCE-005 dépend techniquement de US-INVOICE-001 (import d'un document), mais peut se déclencher dès la première tentative d'import, avant même une analyse complète.
- US-AI-001 et US-AI-002 dépendent de l'existence d'un résultat produit par US-COMPLIANCE-002/003 : l'IA ne peut reformuler que ce que le moteur déterministe a déjà produit (PRD, DEC-002).
- US-NOTIFICATION-001 dépend de US-COMPLIANCE-001 (le diagnostic doit exister pour connaître l'échéance à rappeler).

## 17. Priorisation finale

| Epic | P0 | P1 | P2 | FUTURE |
|---|--:|--:|--:|--:|
| EPIC-AUTH | 3 | 0 | 0 | 0 |
| EPIC-ONBOARDING | 1 | 0 | 0 | 0 |
| EPIC-COMPANY | 2 | 1 | 0 | 0 |
| EPIC-CUSTOMERS | 2 | 0 | 1 | 0 |
| EPIC-INVOICES | 2 | 0 | 0 | 0 |
| EPIC-DOCUMENTS | 0 | 2 | 0 | 0 |
| EPIC-COMPLIANCE | 6 | 0 | 3 | 0 |
| EPIC-AI-ASSISTANT | 0 | 2 | 0 | 0 |
| EPIC-DASHBOARD | 0 | 2 | 0 | 0 |
| EPIC-NOTIFICATIONS | 0 | 0 | 1 | 1 |
| EPIC-SETTINGS | 0 | 2 | 0 | 0 |
| EPIC-ADMINISTRATION | — | — | — | — *(fonction interne, hors priorisation utilisateur — voir section 26)* |

**Lecture** : le MVP (P0) est concentré presque exclusivement sur EPIC-AUTH, EPIC-ONBOARDING, EPIC-COMPANY, EPIC-CUSTOMERS, EPIC-INVOICES et surtout EPIC-COMPLIANCE (6 User Stories P0), ce qui est cohérent avec le PRD : le Compliance Engine et le triptyque comprendre/vérifier/corriger sont le cœur du MVP, tandis que le dashboard, l'historique, l'IA et les paramètres de compte, bien qu'importants, restent P1 et ne conditionnent pas la validation de la proposition de valeur initiale.

## 18. Questions ouvertes — état après décisions produit (2026)

| Question | Décision retenue | Statut |
|---|---|---|
| Donnée précise pour déterminer la « taille » de l'entreprise (US-COMPANY-002) | **Résolu** : effectif salarié, chiffre d'affaires annuel, total du bilan annuel (voir US-COMPANY-002, mise à jour, et note sur la distinction avec la catégorie statistique INSEE) | Résolu |
| Suppression d'un document (US-DOCUMENT-002) et historique d'analyse associé | **Résolu** : le fichier et les données extraites non nécessaires sont supprimés ; l'historique de l'analyse est conservé sous forme minimale avec mention explicite de la suppression du document source (voir US-DOCUMENT-002, mise à jour) | Résolu |
| Vérification d'email obligatoire dès le MVP (US-AUTH-001) ? | **Résolu : oui, mais pas avant toute utilisation.** Un compte peut être créé et utilisé de façon limitée sans vérification d'email ; celle-ci devient obligatoire avant l'accès aux fonctionnalités sensibles (upload de document, analyses persistantes, usage de l'assistant IA) — voir US-AUTH-001 et section 6 (User Story Map). | Résolu |
| US-COMPANY-003 et US-SETTINGS-001/002 nécessaires au MVP ? | **Résolu** : US-COMPANY-003 est conservée au MVP (P1) car elle concerne une configuration indispensable à la conformité (changement de statut TVA/taille en cours d'usage). US-SETTINGS-001/002 restent P1/P2, non bloquantes — le MVP reste centré sur le parcours entreprise → facture → analyse → correction. | Résolu |
| Communication du traitement asynchrone d'une analyse | **Résolu** : `Upload → 202 Accepted → PROCESSING → (job Redis) → COMPLETED`, avec un indicateur de progression explicite côté frontend (« Analyse en cours... »). Le mécanisme de mise à jour du statut côté client repose sur du **polling** au MVP ; WebSocket/SSE reste une amélioration future, non nécessaire au MVP. | Résolu |
| US-COMPLIANCE-008 (signalement de désaccord) : Future ou P2 ? | **Résolu : P2, hors MVP.** Reclassée depuis Future ; prévoir éventuellement un mécanisme de feedback sur un `ComplianceFinding` pour une version ultérieure. | Résolu |

**Nouvelle décision ajoutée, non présente dans la version initiale de ce document** : une facture modifiée après une première analyse (US-COMPLIANCE-006bis, ajoutée ci-dessus) ne crée **pas** une nouvelle version de facture — elle fait passer le résultat de conformité existant à un état explicite d'obsolescence (`ANALYSIS_STALE`), nécessitant une nouvelle analyse. Voir `07-data-model.md` (section 29, mise à jour) et `08-api-specification.md` (section 28, mise à jour).

## 19. Impact sur les prochains documents

- **`06-technical-architecture.md`** doit statuer sur le traitement synchrone/asynchrone de l'analyse (US-COMPLIANCE-002, section 8), sur l'architecture du Compliance Engine capable de produire les états définis en section 8, et sur les questions ouvertes techniques de la section 18.
- **`07-data-model.md`** doit modéliser les entités nécessaires à chaque User Story : compte, entreprise (avec historique de modification, US-COMPANY-003), client, document, facture, résultat de conformité versionné (US-HISTORY-001, UC-COMPLIANCE-001).
- **`08-api-specification.md`** doit définir les contrats permettant chaque parcours (import de document, lancement d'analyse, consultation d'historique), en respectant la distinction erreur technique / problème de conformité (section 8, PRD section 15).
- **`09-test-strategy.md`** doit reprendre l'ensemble des critères d'acceptation Given/When/Then de ce document comme base de ses scénarios de test, en couvrant systématiquement les cas nominaux et les cas d'erreur/limites identifiés.
- **`10-security-privacy.md`** doit trancher les questions ouvertes relatives à la suppression de données (US-DOCUMENT-002, US-SETTINGS-002) et à la vérification d'email (US-AUTH-001).
- **`11-frontend-design-system.md`** doit traduire visuellement les six états de conformité (section 8) et la hiérarchie de gravité qui en découle (section 9), ainsi que le parcours d'explication en trois temps (problème/pourquoi/comment corriger, brief section 19).
- **`12-roadmap.md`** doit séquencer les Epics selon la priorisation de la section 17, et arbitrer les User Stories dont la priorité précise reste à affiner dans le backlog (US-CUSTOMER-003, US-SETTINGS-001/002) — US-COMPANY-003 (P1, MVP) et US-COMPLIANCE-008 (P2, hors MVP) ont désormais une priorité tranchée par décision produit (section 18) et n'ont plus besoin d'arbitrage sur ce point.

## Informations nécessaires à l'architecture

Sans concevoir de solution technique, les éléments suivants, identifiés à partir des User Stories ci-dessus, devront être pris en compte dans `06-technical-architecture.md` :

- **Traitement synchrone ou asynchrone** de l'analyse de conformité (US-COMPLIANCE-002) — dépend du volume de règles et de la complexité de l'import de documents (US-INVOICE-001).
- **Gestion de documents** — import, validation de format, stockage, suppression, lien avec l'historique (US-INVOICE-001, US-DOCUMENT-001/002).
- **Moteur de règles** capable de produire les six états de conformité de la section 8, avec gestion explicite de l'état `A_VERIFIER` en cas de donnée manquante (BR-COMPLIANCE-003 du PRD).
- **Historique et audit** — conservation datée de chaque analyse avec la version des règles appliquées (US-HISTORY-001, UC-COMPLIANCE-001), y compris après modification du contexte entreprise (US-COMPANY-003).
- **Versionnement des règles** de conformité — nécessaire pour répondre à « pourquoi cette facture était-elle conforme à telle date » (section 24 du PRD, US-HISTORY-001).
- **Intégration de la couche IA** (US-AI-001, US-AI-002) — doit pouvoir consommer le résultat du moteur déterministe sans jamais le contourner (PRD, DEC-002).
- **Notifications** (US-NOTIFICATION-001) — mécanisme de déclenchement lié à une échéance calculée par le diagnostic (US-COMPLIANCE-001).
- **Authentification et gestion de compte** (US-AUTH-001 à 003, US-SETTINGS-001/002) — y compris la question ouverte de la vérification d'email (section 18).
- **Permissions** — le MVP ne nécessitant qu'un rôle unique (PRD, section 21), aucune architecture de permissions complexe n'est requise au MVP ; à garder en tête pour une éventuelle évolution future vers le persona cabinet comptable (Persona 4, Epic non prioritaire).
