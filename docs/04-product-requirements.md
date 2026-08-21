# Product Requirements Document - Assistant de conformité à la facturation électronique

> Ce document s'appuie sur `01-intent-note.md` (vision et intention), `02-regulatory-study.md` (source de vérité réglementaire) et `03-market-analysis.md` (positionnement et différenciation). Toute exigence réglementaire mentionnée ici renvoie à une section précise de `02-regulatory-study.md` ; aucune règle n'est réinterprétée ou inventée dans ce document.

## 1. Résumé exécutif

**Nom de travail** : Assistant de conformité à la facturation électronique _(aucun nom de marque définitif n'est arrêté à ce stade)_.

**Description courte** : un outil qui aide les micro-entrepreneurs, indépendants et TPE françaises à comprendre s'ils sont concernés par la réforme de la facturation électronique, à vérifier si leurs factures sont conformes, à comprendre pourquoi elles ne le sont pas, et à savoir comment les corriger - sans remplacer leur outil de facturation existant.

**Problème** : un écart documenté entre la notoriété de la réforme (76-86 % des dirigeants en ont entendu parler) et sa compréhension réelle (seulement 18-35 % se disent réellement informés ou prêts), avec une confusion fréquente et mesurée entre un PDF simple et une facture électronique conforme (`03-market-analysis.md`, section 14).

**Cible** : en priorité les micro-entrepreneurs et indépendants qui pensent, à tort, être déjà conformes ou non concernés (`03-market-analysis.md`, personas 1 et 2).

**Proposition de valeur** : comprendre pourquoi une facture n'est pas conforme et comment la corriger, en langage clair, quel que soit l'outil de facturation déjà utilisé par ailleurs.

**Différenciation** : aucun acteur du marché étudié ne se positionne comme un assistant de compréhension/vérification distinct d'un outil de production de factures (`03-market-analysis.md`, section 6). Notre produit occupe cet espace, en restant complémentaire plutôt que substitutif.

**Objectif du MVP** : valider que des utilisateurs réels utilisent un outil _distinct_ de leur outil de facturation pour comprendre et vérifier leur conformité - l'hypothèse la plus critique identifiée dans `03-market-analysis.md`, section 23.

## 2. Product Vision

**Vision** : devenir la référence à laquelle une petite entreprise française pense en premier lorsqu'elle se demande « suis-je en règle avec la facturation électronique ? », avant même de penser à un logiciel de facturation.

**Mission** : transformer une réglementation complexe et dispersée en vérifications concrètes, compréhensibles et actionnables pour des utilisateurs sans compétence comptable ou technique.

**Proposition de valeur** : à chaque vérification, l'utilisateur ne reçoit pas seulement un verdict, mais une explication de la règle appliquée et une action de correction concrète.

**Principes produit** (repris et opérationnalisés depuis `01-intent-note.md`, section 8) :

- Toute non-conformité affichée doit être accompagnée d'une explication en langage courant, jamais d'un simple code d'erreur.
- Le produit ne doit jamais affirmer qu'une facture est conforme ou non sans pouvoir indiquer la règle et la source qui fondent ce résultat (voir section 18).
- Le produit reste complémentaire à l'outil de facturation de l'utilisateur : il ne cherche pas à le remplacer au MVP (`01-intent-note.md`, section 3).
- Toute incertitude réglementaire doit être présentée comme telle à l'utilisateur, jamais masquée pour paraître plus complet (cohérent avec `02-regulatory-study.md`, section 26).

**Promesse utilisateur** : « Vous saurez toujours pourquoi, jamais seulement si. »

## 3. Personas

Repris de `03-market-analysis.md`, section 4, avec le niveau de détail nécessaire au PRD.

### Persona primaire - Le freelance qui « pense être déjà en règle » (Persona 1)

- **Rôle** : consultant/prestataire indépendant, facture 5 à 10 clients professionnels par mois via PDF/email.
- **Contexte** : pas de logiciel de facturation dédié, pas d'expert-comptable suivi de près, ou expert-comptable peu proactif sur le sujet.
- **Objectifs** : continuer à facturer sans interruption ni erreur ; éviter une amende.
- **Problèmes** : croit à tort que son PDF actuel restera valable ; ne sait pas à partir de quand il doit changer sa pratique.
- **Niveau de connaissance** : a entendu parler de la réforme, n'en connaît pas le détail (cohérent avec `03-market-analysis.md`, section 14).
- **Comportement attendu vis-à-vis du produit** : arrive avec une question simple (« suis-je concerné ? »), veut une réponse rapide et compréhensible, puis potentiellement une vérification de ses pratiques actuelles.

### Persona secondaire A - La micro-entrepreneuse en franchise en base de TVA (Persona 2)

- **Rôle** : vend des prestations à des particuliers et occasionnellement à des professionnels.
- **Contexte** : ne facture pas de TVA, budget très limité.
- **Objectifs** : rester en règle sans frais et sans complexité.
- **Problèmes** : pense à tort ne pas être concernée par une réforme qu'elle associe à la TVA (`02-regulatory-study.md`, section 6).
- **Niveau de connaissance** : faible, sujet perçu comme non prioritaire.
- **Comportement attendu** : a besoin d'abord d'un diagnostic d'éligibilité avant toute autre fonctionnalité.

### Persona secondaire B - Le dirigeant de TPE avec quelques salariés (Persona 3)

- **Rôle** : utilise déjà un outil de facturation/comptabilité établi, accompagné par un expert-comptable.
- **Contexte** : délègue largement le sujet.
- **Comportement attendu vis-à-vis du produit** : usage plus ponctuel, probablement pour faire vérifier un point précis plutôt que pour un suivi continu. Non prioritaire pour le MVP.

### Persona secondaire C - Le collaborateur de cabinet comptable (Persona 4)

- Non prioritaire pour le MVP (`01-intent-note.md`, section 5 : cible secondaire). Mentionné ici pour cohérence, à ne pas concevoir de fonctionnalité dédiée avant validation du MVP primaire.

## 4. Jobs To Be Done

Pour le persona primaire et les personas secondaires A/B :

- Quand j'entends parler de la réforme de la facturation électronique, je veux savoir rapidement si mon entreprise est concernée et à partir de quand, afin de ne pas paniquer ni ignorer le sujet à tort.
- Quand j'ai déjà une pratique de facturation (PDF, logiciel existant), je veux savoir si elle restera valable, afin de décider si je dois changer quelque chose.
- Quand je regarde une facture que j'ai émise ou que je m'apprête à émettre, je veux savoir si elle respecte les mentions et le format attendus, afin d'éviter une erreur ou une sanction.
- Quand une facture n'est pas conforme, je veux comprendre pourquoi en langage clair, afin de savoir exactement quoi corriger sans avoir à déchiffrer un jargon juridique ou technique.
- Quand je ne sais pas si une règle s'applique à ma situation particulière (client mixte B2B/B2C, franchise en base, etc.), je veux une réponse adaptée à mon cas précis plutôt qu'une réponse générique.
- Quand le temps passe, je veux pouvoir vérifier que je suis toujours à jour de mes obligations, sans avoir à ressuivre toute la réglementation moi-même.

## 5. Problèmes prioritaires

| ID    | Problème                                                                                       | Persona      | Importance                                                                                      | Fréquence                     | Priorité |
| ----- | ---------------------------------------------------------------------------------------------- | ------------ | ----------------------------------------------------------------------------------------------- | ----------------------------- | -------- |
| PB-01 | Ne sait pas s'il est concerné par la réforme et à partir de quand                              | Persona 1, 2 | Élevée - condition d'entrée dans tout le reste du produit                                       | Ponctuelle mais critique      | P0       |
| PB-02 | Confond un PDF/email avec une facture électronique conforme                                    | Persona 1    | Élevée - confusion documentée chez ~4 indépendants sur 10 (`03-market-analysis.md`, section 14) | Récurrente jusqu'à correction | P0       |
| PB-03 | Ne connaît pas les mentions obligatoires nouvelles (SIREN client, catégorie d'opération, etc.) | Persona 1, 3 | Élevée - obligation directe dès le 1er septembre 2026 (`02-regulatory-study.md`, section 10)    | À chaque facture              | P0       |
| PB-04 | Ne comprend pas la différence entre assujetti et redevable de la TVA                           | Persona 2    | Élevée - condition d'éligibilité mal comprise (`02-regulatory-study.md`, section 6)             | Ponctuelle mais bloquante     | P0       |
| PB-05 | Ne sait pas comment traiter une clientèle mixte (professionnels et particuliers)               | Persona 1, 3 | Moyenne - cas fréquent mais plus complexe (`02-regulatory-study.md`, section 18, scénario C)    | Récurrente                    | P1       |
| PB-06 | N'a pas de vision d'ensemble de son état de conformité dans le temps                           | Persona 3    | Moyenne                                                                                         | Continue                      | P1       |
| PB-07 | Ne sait pas si une règle a changé depuis sa dernière vérification                              | Tous         | Moyenne - réglementation évolutive documentée (`02-regulatory-study.md`, section 4)             | Occasionnelle                 | P2       |

## 6. Proposition de valeur

> Reprise et déclinée depuis `01-intent-note.md`, section 7, et validée par le vide concurrentiel identifié dans `03-market-analysis.md`, section 19.

Le produit transforme une problématique réglementaire complexe en un parcours en trois temps, toujours dans cet ordre : **comprendre** (suis-je concerné, et par quoi précisément) → **vérifier** (mes pratiques actuelles ou une facture donnée sont-elles conformes) → **corriger** (que dois-je changer, concrètement). Ce triptyque doit rester visible et explicite à chaque étape de l'expérience produit, car c'est lui qui distingue le produit d'un simple outil de production de factures (`03-market-analysis.md`, section 19).

## 7. Product Scope

### In Scope (MVP et V1)

- Diagnostic d'éligibilité à la réforme (statut TVA, taille d'entreprise, calendrier applicable).
- Vérification des mentions obligatoires d'une facture existante (import ou saisie manuelle).
- Explication pédagogique de chaque non-conformité détectée, avec source réglementaire associée.
- Distinction pédagogique entre un document informel (PDF/email) et une facture électronique conforme.
- Suivi dans le temps de l'état de conformité déclaré/vérifié de l'entreprise (V1).
- Gestion des cas de clientèle mixte B2B/B2C (V1).

### Out of Scope (explicitement exclu du produit, à ce stade)

- Émission ou transmission réelle de factures électroniques (rôle réservé à une plateforme agréée, voir `02-regulatory-study.md`, section 13, et `01-intent-note.md`, section 11).
- Comptabilité complète (déclarations fiscales, liasse, bilan).
- Gestion de la paie, des notes de frais, des achats fournisseurs au sens large.
- CRM ou gestion commerciale.
- Toute fonctionnalité qui ferait de notre produit une plateforme agréée ou un substitut à une plateforme agréée.

### Future Scope (envisageable après validation du MVP, non engagé)

- Intégration technique avec des outils de validation Factur-X existants ou des plateformes agréées (`03-market-analysis.md`, section 9).
- Ouverture vers le segment cabinet comptable (Persona 4).
- Notifications proactives liées à des échéances ou changements réglementaires.

## 8. MVP

| ID     | Fonctionnalité                                                          | Problème résolu | Persona | Valeur                                                                                                                      | Priorité |
| ------ | ----------------------------------------------------------------------- | --------------- | ------- | --------------------------------------------------------------------------------------------------------------------------- | -------- |
| MVP-01 | Diagnostic d'éligibilité (statut TVA, taille, calendrier)               | PB-01, PB-04    | 1, 2    | Répond à la question d'entrée avant tout le reste                                                                           | P0       |
| MVP-02 | Vérification des mentions obligatoires d'une facture (import ou saisie) | PB-02, PB-03    | 1       | Cœur de la proposition de valeur « vérifier »                                                                               | P0       |
| MVP-03 | Explication pédagogique de chaque anomalie détectée, avec source        | PB-02, PB-03    | 1, 2    | Cœur de la proposition de valeur « comprendre/corriger » ; différenciation principale (`03-market-analysis.md`, section 18) | P0       |
| MVP-04 | Distinction explicite PDF/email vs facture électronique conforme        | PB-02           | 1       | Cible directement la confusion la plus documentée du marché                                                                 | P0       |
| MVP-05 | Historique des vérifications effectuées                                 | PB-06 (partiel) | 1, 3    | Permet à l'utilisateur de retrouver ses vérifications passées                                                               | P1       |
| MVP-06 | Tableau de bord synthétique de l'état de conformité déclaré             | PB-06           | 1, 3    | Vue d'ensemble, moins critique que la vérification unitaire au MVP                                                          | P1       |
| MVP-07 | Gestion des cas de clientèle mixte B2B/B2C dans le diagnostic           | PB-05           | 1, 3    | Cas fréquent mais peut être traité de façon simplifiée au MVP                                                               | P2       |
| MVP-08 | Signalement d'une règle obsolète ou d'un désaccord par l'utilisateur    | - (confiance)   | Tous    | Renforce l'explicabilité (section 18) mais non bloquant pour valider la proposition de valeur                               | Future   |

## 9. Fonctionnalités principales

Chaque module ci-dessous a été évalué pour son inclusion ou non au MVP, avec justification.

### Authentification

Nécessaire dès le MVP pour permettre un suivi individualisé (historique, tableau de bord). Périmètre minimal : inscription, connexion, récupération de compte. La vérification d'email et les mécanismes avancés (SSO, 2FA) sont repoussés à une itération ultérieure sauf exigence de sécurité contraire (voir `10-security-privacy.md`).

### Entreprise

Indispensable au MVP : sans les informations de base sur l'entreprise (statut TVA, taille, régime), le diagnostic d'éligibilité (MVP-01) ne peut pas fonctionner. Périmètre minimal : informations légales de base, statut TVA (assujetti/redevable, franchise en base ou non), taille (pour déterminer la date d'obligation applicable, `02-regulatory-study.md`, section 5).

### Clients

Nécessaire de façon minimale pour la vérification des mentions obligatoires (le statut du client - professionnel français, particulier, étranger - détermine en partie les règles applicables, `02-regulatory-study.md`, section 7). Une gestion complète (catégorisation avancée, historique par client) n'est pas indispensable au MVP.

### Factures

Nécessaire uniquement en tant que **support de vérification**, pas en tant que module de production. Le MVP doit permettre l'import ou la saisie manuelle des informations d'une facture existante pour analyse, mais **pas** sa création en tant que document destiné à être envoyé à un client (cela relèverait de l'émission, hors périmètre - voir section 7). Cette distinction est structurante et doit être rappelée dans `05-user-stories.md`.

### Compliance Engine

Module central, détaillé en section 10. Indispensable au MVP.

### Assistant (couche d'explication)

Nécessaire au MVP sous une forme simple : reformulation pédagogique des résultats du Compliance Engine (voir section 17 sur la place de l'IA). Une fonctionnalité de question libre ouverte ("chatbot" conversationnel) n'est pas indispensable au MVP et peut être envisagée en V1/V2.

### Documents

Nécessaire a minima pour permettre l'import d'une facture existante à analyser (voir section 16). Le stockage à long terme et la gestion de versions de documents ne sont pas indispensables au MVP.

### Dashboard

Utile mais non bloquant pour valider la proposition de valeur centrale (MVP-06, priorité P1). Peut être limité à une vue très simple en première itération.

## 10. Compliance Engine

Le Compliance Engine est le cœur du produit. Il doit :

1. **Identifier le contexte** de la facture ou de la situation analysée (statut TVA et taille de l'entreprise émettrice, statut du client, nature de l'opération - biens/services/mixte, date de l'opération).
2. **Déterminer les règles applicables** à ce contexte, en s'appuyant sur les catégories de règles identifiées dans `02-regulatory-study.md`, section 20 (règles de qualification d'opération, de mentions obligatoires, de statut de l'émetteur).
3. **Vérifier les données** disponibles par rapport à chaque règle applicable.
4. **Produire un résultat** structuré par vérification individuelle, et un résultat global.
5. **Expliquer** chaque résultat non conforme ou incertain en langage courant, avec la règle et la source associées (voir section 18).
6. **Proposer une correction** concrète et actionnable pour chaque non-conformité.
7. **Conserver une trace** de l'analyse (règle appliquée, version, date) pour permettre l'audit ultérieur (voir section 24).

### États possibles d'une vérification

À partir des besoins identifiés dans `02-regulatory-study.md` (sections 6, 7, 10, 18, 23), les états suivants sont nécessaires - cette liste va au-delà de l'exemple binaire conforme/non conforme donné dans le brief, car la réglementation elle-même comporte des zones d'incertitude documentées (`02-regulatory-study.md`, section 23) qu'un simple binaire masquerait :

```text
CONFORME          - la règle est respectée, aucune action nécessaire
NON_CONFORME       - la règle n'est pas respectée, une correction est nécessaire
AVERTISSEMENT      - la règle est probablement respectée mais un point mérite attention (ex. donnée ambiguë)
NON_APPLICABLE     - la règle ne s'applique pas à ce contexte précis
A_VERIFIER         - le système ne dispose pas de données suffisantes pour conclure
INCERTAIN_REGLEMENTAIRE - la règle elle-même repose sur un point signalé comme non confirmé dans 02-regulatory-study.md (section 23)
```

Le dernier état (`INCERTAIN_REGLEMENTAIRE`) découle directement du principe de `02-regulatory-study.md` selon lequel une incertitude réglementaire ne doit jamais être présentée comme un fait établi. Il doit rester rare et n'être utilisé que pour les points explicitement listés comme incertains dans l'étude réglementaire.

## 11. Résultats de conformité

Reprenant la structure du brief, chaque analyse doit produire un résultat comportant, pour l'ensemble de la facture ou de la situation analysée :

- un **statut global** (dérivé des états de la section 10 : par exemple, non conforme si au moins une vérification individuelle est non conforme) ;
- le **détail de chaque vérification individuelle**, avec son propre statut ;
- pour chaque vérification non conforme, en avertissement ou incertaine : un **problème énoncé simplement**, un **« pourquoi » pédagogique**, et une **action de correction concrète** ;
- la **règle et la source réglementaire** sous-jacentes à chaque vérification (voir section 18) ;
- la **date de l'analyse** et, si applicable, la version de la règle utilisée (voir section 24).

Exigence fonctionnelle associée : le système ne doit jamais afficher un statut global sans permettre à l'utilisateur d'accéder au détail des vérifications individuelles qui le composent - l'agrégation ne doit pas masquer la traçabilité.

## 12. Parcours utilisateurs principaux

### Parcours 1 - Nouvel utilisateur → création de son entreprise

Déclenché à l'inscription. Doit rester bref : informations strictement nécessaires au diagnostic d'éligibilité (section 9 - Entreprise).

### Parcours 2 - Entreprise → configuration de son contexte fiscal

Suite naturelle du parcours 1 : statut TVA, taille, régime. Doit produire immédiatement un premier résultat de diagnostic (MVP-01), pour donner une valeur perçue dès les premières minutes d'usage.

### Parcours 3 - Import ou saisie d'une facture à analyser

L'utilisateur importe un document existant (PDF, autre) ou saisit manuellement les informations d'une facture. Doit gérer explicitement le cas d'un document non structuré (voir section 15, gestion des erreurs).

### Parcours 4 - Analyse de conformité

Déclenchement du Compliance Engine (section 10) sur la facture importée/saisie. Doit afficher un état de progression si l'analyse n'est pas instantanée (voir section 14, performance).

### Parcours 5 - Facture non conforme → correction

L'utilisateur consulte le détail de chaque non-conformité (section 11) et les actions de correction proposées. Ce parcours doit permettre de relancer une nouvelle analyse après correction, pour vérifier que le problème est résolu.

### Parcours 6 - Facture conforme → étape suivante

Le produit ne générant ni ne transmettant de facture (hors périmètre, section 7), ce parcours se limite à confirmer la conformité constatée et, le cas échéant, à orienter l'utilisateur vers son propre outil de facturation/plateforme agréée pour l'émission réelle - sans effectuer cette émission lui-même.

### Parcours 7 - Consultation de l'historique

L'utilisateur retrouve ses analyses précédentes (MVP-05). Doit permettre de retrouver la règle et la version appliquées à une date donnée (voir section 24).

### Parcours 8 - Consultation du dashboard de conformité

L'utilisateur obtient une vue d'ensemble de son état de conformité déclaré (MVP-06). Priorité P1 - peut être une version simplifiée au MVP.

### Parcours 9 - Diagnostic d'éligibilité isolé (sans facture à analyser)

Ajouté à la liste du brief car il correspond au JTBD le plus fondamental identifié (section 4) : un utilisateur peut vouloir simplement répondre à « suis-je concerné ? » sans avoir de facture prête à analyser. Ce parcours doit être accessible indépendamment du parcours 3.

## 13. Exigences fonctionnelles

> Chaque exigence renvoie, lorsque pertinent, à la section de `02-regulatory-study.md` qui la justifie.

**FR-AUTH-001** - _Créer un compte utilisateur_
Description : le système doit permettre à un utilisateur de créer un compte pour accéder à ses diagnostics et historiques.
Justification : nécessaire pour tout suivi individualisé (section 9).
Priorité : P0.

**FR-AUTH-002** - _Se connecter / récupérer l'accès à son compte_
Priorité : P0.

**FR-COMPANY-001** - _Renseigner le statut TVA de l'entreprise_
Description : le système doit permettre de renseigner si l'entreprise est assujettie, redevable, en franchise en base.
Justification : condition d'éligibilité (`02-regulatory-study.md`, section 6).
Priorité : P0.

**FR-COMPANY-002** - _Renseigner la taille de l'entreprise_
Description : le système doit permettre de déterminer la taille de l'entreprise selon les critères pertinents pour la date d'obligation applicable.
Justification : détermine la date d'obligation d'émission (`02-regulatory-study.md`, section 5).
Priorité : P0.

**FR-DIAGNOSTIC-001** - _Produire un diagnostic d'éligibilité et de calendrier_
Description : à partir des informations de l'entreprise, le système doit indiquer si et à partir de quand elle est concernée par l'obligation de réception et d'émission.
Justification : répond au JTBD principal (section 4, PB-01).
Priorité : P0.

**FR-INVOICE-001** - _Importer ou saisir les informations d'une facture existante_
Description : le système doit permettre d'importer un document ou de saisir manuellement les données d'une facture, à des fins d'analyse uniquement (pas d'émission, voir section 7).
Priorité : P0.

**FR-COMPLIANCE-001** - _Analyser une facture_
Description : le système doit permettre à l'utilisateur de lancer une analyse de conformité sur une facture importée ou saisie, produisant un résultat structuré (section 11).
Priorité : P0.

**FR-COMPLIANCE-002** - _Expliquer chaque résultat de vérification_
Description : pour chaque vérification non conforme, en avertissement, incertaine ou à vérifier, le système doit fournir une explication en langage courant et la source réglementaire associée.
Justification : cœur de la différenciation produit (`03-market-analysis.md`, section 18).
Priorité : P0.

**FR-COMPLIANCE-003** - _Proposer une action de correction_
Description : pour chaque non-conformité, le système doit proposer une action concrète permettant de la résoudre.
Priorité : P0.

**FR-COMPLIANCE-004** - _Distinguer document informel et facture électronique conforme_
Description : le système doit être capable d'expliquer explicitement pourquoi un PDF/email ne constitue pas une facture électronique conforme au sens de la réforme.
Justification : cible directement la confusion la plus documentée du marché (`03-market-analysis.md`, section 14 ; `02-regulatory-study.md`, section 8).
Priorité : P0.

**FR-HISTORY-001** - _Consulter l'historique des analyses effectuées_
Priorité : P1.

**FR-DASHBOARD-001** - _Consulter une vue d'ensemble de l'état de conformité déclaré_
Priorité : P1.

**FR-MIXED-001** - _Gérer un contexte de clientèle mixte B2B/B2C dans le diagnostic_
Justification : `02-regulatory-study.md`, section 18, scénario C.
Priorité : P2.

**FR-TRUST-001** - _Permettre à l'utilisateur de signaler un désaccord avec un résultat_
Priorité : Future.

**FR-SETTINGS-001** - _Gérer les paramètres de son compte_
Description : le système doit permettre à l'utilisateur de consulter et modifier les informations de son compte (email, mot de passe).
Priorité : P1.

**FR-SETTINGS-002** - _Supprimer son compte_
Description : le système doit permettre à l'utilisateur de demander la suppression de son compte, dans les limites posées par les obligations de conservation légale (voir `10-security-privacy.md`, sections 38-39).
Priorité : P1.

**FR-TEAM-001** - _Inviter un membre dans son organisation_
Description : un `OWNER` ou un `ADMIN` doit pouvoir inviter une personne à rejoindre son organisation avec un rôle donné (section 21).
Justification : décision produit du 21/08/2026 - voir DEC-009, section 21.
Priorité : P1.

**FR-TEAM-002** - _Gérer le rôle d'un membre_
Description : un `OWNER` doit pouvoir modifier le rôle d'un membre existant (`ADMIN`/`COLLABORATOR`), dans les limites de la matrice de permissions (section 21). Un `ADMIN` ne peut jamais promouvoir un membre au rôle `OWNER` ni retirer l'`OWNER`.
Priorité : P1.

**FR-TEAM-003** - _Retirer un membre de son organisation_
Description : un `OWNER` ou un `ADMIN` doit pouvoir retirer un membre de l'organisation (jamais l'`OWNER` lui-même par un `ADMIN`, voir matrice de permissions section 21).
Priorité : P1.

**FR-NOTIFICATION-001** - _Envoyer une notification aux membres de son organisation_
Description : un `OWNER` ou un `ADMIN` doit pouvoir composer et envoyer une notification à un ou plusieurs membres de sa propre organisation.
Justification : distinct des notifications système de la section 19 - ici l'expéditeur est un humain de l'organisation, jamais le système lui-même.
Priorité : P1.

**FR-PLATFORMADMIN-001** - _Consulter la liste des organisations et des comptes_
Description : un `PlatformAdministrator` (rôle distinct, jamais un rôle d'organisation - voir `06-technical-architecture.md`, ADR-009) doit pouvoir consulter, à travers toutes les organisations, la liste des comptes et organisations existants.
Priorité : P1.

**FR-PLATFORMADMIN-002** - _Suspendre ou réactiver un compte_
Description : un `PlatformAdministrator` doit pouvoir suspendre l'accès d'une organisation ou d'un compte utilisateur, et le réactiver.
Priorité : P1.

**FR-PLATFORMADMIN-003** - _Consulter l'audit trail cross-tenant_
Description : un `PlatformAdministrator` doit pouvoir consulter le journal d'audit à travers toutes les organisations, à des fins de support et d'investigation.
Priorité : P1.

**FR-PLATFORMADMIN-004** - _Envoyer une notification ciblée ou diffusée_
Description : un `PlatformAdministrator` doit pouvoir composer une notification et la cibler sur un utilisateur précis, une organisation entière, un segment défini par critère (ex. statut TVA, catégorie de taille), ou l'ensemble des utilisateurs (diffusion globale).
Priorité : P1.

**FR-PLATFORMADMIN-005** - _Consulter la santé applicative_
Description : un `PlatformAdministrator` doit pouvoir consulter des indicateurs de santé applicative : taux d'échec du Compliance Engine, jobs asynchrones en échec/dead-letter, volume et coût des appels IA, statut `/api/health`. Explicitement limité au niveau applicatif - le monitoring d'infrastructure réelle (uptime, ressources serveur) reste hors périmètre tant qu'aucun hébergeur n'est retenu (Phase 17, `12-roadmap.md`).
Priorité : P2.

**FR-ANALYTICS-001** - _Consulter des statistiques agrégées d'usage_
Description : un `PlatformAdministrator` doit pouvoir consulter des statistiques agrégées (nombre d'organisations, d'utilisateurs, d'analyses de conformité effectuées, taux de conformité) sur l'ensemble de la plateforme.
Priorité : P2.

**FR-ANALYTICS-002** - _Visualiser l'évolution de ces statistiques dans le temps_
Description : les statistiques de FR-ANALYTICS-001 doivent pouvoir être visualisées sous forme de graphiques d'évolution temporelle. Ne remet pas en cause DL-008 (`12-roadmap.md`, « pas de graphiques au MVP ») : cette exigence concerne exclusivement la surface d'administration plateforme, jamais le dashboard de l'utilisateur final (section 20), dont le périmètre reste inchangé.
Priorité : P2.

## 14. Exigences non fonctionnelles

### Performance

L'analyse d'une facture (FR-COMPLIANCE-001) doit fournir un résultat en un temps perçu comme raisonnable par un utilisateur non technique ; si un traitement asynchrone est nécessaire (par exemple pour l'analyse d'un document importé), l'utilisateur doit être informé de la progression plutôt que de rester face à un écran sans réponse.

### Disponibilité

Le produit n'ayant pas vocation à assurer une mission critique en temps réel (il n'émet ni ne transmet de factures), une indisponibilité temporaire ne doit pas empêcher un utilisateur de facturer par ailleurs via son propre outil - cohérent avec le positionnement complémentaire du produit (section 7).

### Sécurité

Authentification requise pour accéder aux données d'une entreprise ; isolation stricte des données entre entreprises/utilisateurs différents. Le détail des mécanismes relève de `10-security-privacy.md`.

### Confidentialité

Les données manipulées (informations d'entreprise, factures, informations clients) sont sensibles (`02-regulatory-study.md`, section 26 des impacts produit). Le système doit appliquer un principe de minimisation : ne demander et ne conserver que les données nécessaires au diagnostic et à la vérification, pas plus.

### Scalabilité

Non critique au MVP compte tenu du volume attendu, mais le Compliance Engine (section 10) doit être conçu de façon modulaire pour pouvoir absorber de nouvelles catégories de règles sans refonte complète (voir aussi Maintenabilité).

### Maintenabilité

Le Compliance Engine doit pouvoir intégrer de nouvelles règles ou faire évoluer des règles existantes à mesure que la réglementation change, conformément au besoin de versionnement identifié dans `02-regulatory-study.md`, section 21.

### Auditabilité

Chaque résultat de conformité doit rester traçable dans le temps : quelle règle, quelle version, quelle date (voir section 24). C'est une exigence non négociable compte tenu du principe d'explicabilité du produit.

### Accessibilité

Le produit s'adressant explicitement à des utilisateurs non spécialistes (`01-intent-note.md`), le langage utilisé dans toute l'interface doit rester compréhensible sans vocabulaire juridique ou technique non expliqué.

### Compatibilité

Le produit doit être utilisable sur les navigateurs et formats d'écran courants pour la cible (y compris mobile, une part significative des micro-entrepreneurs et indépendants gérant leur administratif en dehors d'un poste de travail fixe).

## 15. Gestion des erreurs

Distinction fondamentale à respecter dans tout le produit : une **erreur technique** (le système ne fonctionne pas) doit toujours être présentée différemment d'un **problème de conformité** (le système fonctionne, mais la facture ou la situation a un problème réglementaire).

Catégories d'erreurs à couvrir :

- **Donnée manquante** - un champ nécessaire à une vérification n'est pas renseigné → état `A_VERIFIER` (section 10), pas une erreur technique.
- **Donnée invalide** - une donnée renseignée est manifestement incorrecte (format impossible) → message de correction de saisie, distinct d'un problème de conformité.
- **Incohérence** - deux données renseignées se contredisent → signalé explicitement à l'utilisateur avant toute analyse de conformité.
- **Règle non applicable** - état `NON_APPLICABLE` (section 10), pas une erreur.
- **Règle impossible à déterminer avec certitude** - état `INCERTAIN_REGLEMENTAIRE` (section 10), pas une erreur technique.
- **Document illisible ou format non supporté** - erreur technique claire, distincte d'un problème de conformité, avec indication de ce que l'utilisateur peut faire (réessayer, saisir manuellement).
- **Service externe indisponible** (si une dépendance externe existe, voir section 22) - erreur technique, ne doit jamais être présentée comme un résultat de conformité.

## 16. Documents et fichiers

Besoins fonctionnels minimaux pour le MVP (l'infrastructure technique relève de `06-technical-architecture.md`) :

- **Upload/import** d'un document représentant une facture existante, à des fins d'analyse (FR-INVOICE-001).
- **Validation basique** du fichier importé (format lisible ou non) avant tentative d'analyse.
- **Consultation** du document importé associé à une analyse passée (lié à l'historique, section 12, parcours 7).
- **Suppression** d'un document à la demande de l'utilisateur.

Non nécessaires au MVP : gestion de versions multiples d'un même document, stockage à long terme au-delà du besoin de consultation de l'historique, export dans des formats structurés (Factur-X, UBL, CII) - le produit n'émettant pas de factures (section 7), il n'a pas vocation à générer ces formats au MVP.

## 17. Assistant IA

### Ce que l'IA peut faire

- Reformuler en langage courant le résultat d'une vérification produite par le Compliance Engine déterministe (FR-COMPLIANCE-002).
- Aider à formuler une explication pédagogique adaptée au contexte précis de l'utilisateur, à partir des éléments fournis par le Compliance Engine.
- Répondre à des questions générales de compréhension (par exemple, « qu'est-ce qu'un SIREN ? »), en s'appuyant sur le contenu de `02-regulatory-study.md`.
- Aider à guider l'utilisateur vers l'action de correction déterminée par le Compliance Engine.

### Ce que l'IA ne doit pas faire

- Déterminer elle-même, de façon autonome, si une facture est conforme ou non : ce résultat doit toujours provenir du Compliance Engine déterministe (section 10), jamais d'une génération libre.
- Inventer une obligation, un montant, une date ou une sanction qui ne serait pas directement traçable à `02-regulatory-study.md`.
- Affirmer un niveau de certitude supérieur à celui documenté dans l'étude réglementaire (par exemple, présenter comme certaine une information marquée « à confirmer » dans `02-regulatory-study.md`, section 23).
- Remplacer la nécessité, pour l'utilisateur, de pouvoir consulter la règle et la source exactes derrière un résultat (section 18).

**Principe directeur** : le moteur déterministe de conformité est la source de vérité ; l'IA est une couche d'assistance et de reformulation, jamais l'autorité réglementaire elle-même. Cette séparation doit rester visible dans l'architecture produit, même si son implémentation technique relève de `06-technical-architecture.md`.

## 18. Explicabilité et confiance

Exigences transverses à tout résultat produit par le Compliance Engine :

- Chaque résultat doit être accompagné de la **règle appliquée**, formulée en langage compréhensible.
- Chaque règle doit être traçable à une **source** identifiable (référence à `02-regulatory-study.md` ou à la source officielle sous-jacente).
- Chaque résultat doit indiquer la **date** à laquelle l'analyse a été effectuée.
- Lorsque la règle sous-jacente est elle-même marquée comme incertaine dans `02-regulatory-study.md` (section 23), le résultat doit refléter ce niveau d'incertitude (état `INCERTAIN_REGLEMENTAIRE`, section 10) plutôt que d'afficher une fausse certitude.
- L'utilisateur doit pouvoir **signaler un désaccord** avec un résultat (FR-TRUST-001), même si cette fonctionnalité n'est pas bloquante pour le MVP.

## 19. Notifications

### 19.1 Notifications système (analyse initiale MVP, sans engagement)

- **Problème détecté** lors d'une analyse - retour immédiat dans le parcours d'analyse lui-même (section 12, parcours 4-5), pas nécessairement une notification asynchrone séparée au MVP.
- **Changement réglementaire** affectant une règle déjà appliquée à l'utilisateur - utile mais suppose un mécanisme de veille et de versionnement déjà mature (`02-regulatory-study.md`, section 21) ; repoussé en Future Scope.
- **Échéance à venir** (par exemple, rappel de la date d'obligation d'émission déterminée par le diagnostic, section 12 parcours 2) - utile et relativement simple à mettre en œuvre une fois le diagnostic établi ; candidat raisonnable pour V1, non P0 au MVP.
- **Action requise suite à une correction non effectuée** - repoussé, suppose un usage récurrent déjà établi.

### 19.2 Notifications envoyées par un humain (décision produit du 21/08/2026, DEC-009/DEC-010)

Distinctes des notifications système ci-dessus : ici, l'expéditeur est une personne, jamais le
système lui-même. Deux portées, correspondant à deux rôles différents (section 21) :

- **Portée organisation** (FR-NOTIFICATION-001, Phase 14) : un `OWNER` ou un `ADMIN` notifie un
  ou plusieurs membres de sa propre organisation. Reste strictement dans les frontières
  tenant existantes.
- **Portée plateforme** (FR-PLATFORMADMIN-004, Phase 15) : un `PlatformAdministrator` cible un
  utilisateur précis, une organisation entière, un segment défini par critère (statut TVA,
  catégorie de taille INSEE, etc.), ou diffuse à l'ensemble des utilisateurs. Cette portée
  traverse les organisations - elle n'est accessible qu'au rôle `PlatformAdministrator`,
  jamais à un `OWNER`/`ADMIN` d'organisation (voir `06-technical-architecture.md`, ADR-009).

Dans les deux cas, une notification portée par un humain reste soumise aux mêmes règles de
minimisation des données que le reste du produit (`10-security-privacy.md`) - son contenu n'est
jamais utilisé pour déduire ou afficher un résultat de conformité, qui reste la seule autorité
du Compliance Engine (section 10).

## 20. Dashboard

Rôle du dashboard : répondre à « est-ce que mon entreprise est prête et conforme ? » en un coup d'œil, sans devoir relancer une analyse manuelle.

Informations essentielles à afficher (contenu, pas design - voir `11-frontend-design-system.md`) :

- **État global déclaré** de conformité (dérivé des dernières analyses effectuées, pas une certification absolue - voir section 28, limitations).
- **Problèmes non résolus** identifiés lors des dernières analyses.
- **Avertissements** en cours.
- **Actions recommandées**, reprises des résultats de conformité (section 11).
- **Historique synthétique** des analyses passées (nombre, évolution dans le temps).

Le dashboard reste priorité P1 (section 8) : une version minimale (liste simple des dernières analyses et de leur statut) suffit pour le MVP ; une vue synthétique avancée (tendances, score global) est repoussée en V1.

## 21. Utilisateurs et rôles

**Historique de la décision** : le MVP (Phases 0-12) s'est délibérément limité à un rôle
utilisateur unique, le **propriétaire du compte** (DEC-003, ci-dessous), la cible primaire
(micro-entrepreneur, indépendant) correspondant très majoritairement à un usage
mono-utilisateur. Cette décision est **révisée par décision produit explicite du 21/08/2026**
(DEC-009) : l'utilisateur souhaite une application couvrant la gestion d'équipe au sein d'une
organisation avant la mise en production (Phase 14, `12-roadmap.md`) - ce n'est pas une
substitution silencieuse de préférence technique, mais une décision produit assumée, qui
réouvre le persona secondaire C (cabinet comptable, `03-market-analysis.md` section 4) plus tôt
que prévu initialement.

### 21.1 Rôles au sein d'une organisation (Phase 14)

Trois rôles, portés par la relation `Membership` entre un `User` et une `Organization`
(jamais par le `User` directement - un même utilisateur peut avoir un rôle différent dans
chaque organisation à laquelle il appartient, voir `07-data-model.md` section 5) :

| Rôle            | Description                                                                          |
| --------------- | ------------------------------------------------------------------------------------- |
| `OWNER`         | Contrôle complet de l'organisation : gestion des membres, des rôles, des notifications d'équipe, et de toutes les données métier. Un seul `OWNER` par organisation à la création, transférable (mécanisme à préciser en implémentation). |
| `ADMIN`         | Administration opérationnelle courante (gestion des factures, clients, analyses, invitation/retrait de membres) - sans les actions les plus sensibles réservées à `OWNER`. |
| `COLLABORATOR`  | Usage métier courant (saisie, consultation, analyse) - aucune gestion d'équipe. |

**Matrice de permissions (niveau produit, le détail technique relève de `10-security-privacy.md` section 15 et `08-api-specification.md`)** :

| Action                                                    | OWNER | ADMIN | COLLABORATOR |
| ---------------------------------------------------------- | :---: | :---: | :----------: |
| Consulter/saisir/analyser des factures, clients, diagnostics | Oui   | Oui   | Oui          |
| Envoyer une notification aux membres de l'organisation (FR-NOTIFICATION-001) | Oui | Oui | Non |
| Inviter un membre (FR-TEAM-001)                            | Oui   | Oui   | Non          |
| Modifier le rôle d'un membre (FR-TEAM-002)                 | Oui   | Non   | Non          |
| Retirer un membre (FR-TEAM-003)                            | Oui   | Oui (jamais l'`OWNER`) | Non |
| Modifier le contexte fiscal de l'organisation               | Oui   | Oui   | Non          |
| Supprimer l'organisation / transférer la propriété          | Oui   | Non   | Non          |

Cette matrice doit être reprise à l'identique dans `10-security-privacy.md` (section 15) et
implémentée comme une vérification centralisée, jamais dupliquée par endpoint (cohérent avec
`06-technical-architecture.md`, ADR-004 et section 19).

### 21.2 Rôle plateforme (Phase 15, distinct des rôles d'organisation)

Un quatrième rôle, **`PlatformAdministrator`**, est introduit en Phase 15 pour les besoins
opérationnels internes (support, modération, communication) - voir section 19.2 et
`06-technical-architecture.md` ADR-009. Ce rôle est **structurellement distinct** des rôles
`OWNER`/`ADMIN`/`COLLABORATOR` ci-dessus : il n'appartient à aucune `Organization`, traverse
l'isolation tenant de façon contrôlée et auditée, et n'est jamais accordé à un utilisateur final
du produit. Un `PlatformAdministrator` ne peut jamais se voir accorder un rôle d'organisation
sur le même compte (identités structurellement séparées, jamais un simple indicateur sur
l'entité `User` existante).

### 21.3 Décisions produit associées

Voir DEC-009 (rôles d'organisation), DEC-010 (rôle plateforme, isolation), DEC-011 (analytics)
en section 31.

## 22. Intégrations externes

| Intégration                                                                               | Besoin                                                                                                                                                             | Valeur                                                                                                                                 | Dépendance                                                                                                                                                                                                                                                                                                         | Priorité                                                                                                                                                                               |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Vérification d'entreprise (SIREN/statut)                                                  | Fiabiliser les informations saisies par l'utilisateur (entreprise et client)                                                                                       | Réduit les erreurs de saisie, améliore la fiabilité du diagnostic                                                                      | **Résolu : API Sirene/INSEE**, intégrée via un `CompanyVerificationService` avec cache (voir section 32)                                                                                                                                                                                                           | Non bloquant pour tout le MVP - la saisie manuelle reste possible en repli ; intégration ciblée V1                                                                                     |
| Outils de validation technique de factures existants (`03-market-analysis.md`, section 9) | Éviter de reconstruire en interne la validation technique fine d'un fichier structuré                                                                              | Accélère le développement, s'appuie sur des outils déjà éprouvés                                                                       | **Résolu : Mustangproject** (Java, open source), étudié comme composant de validation isolé - Symfony n'embarque pas de Java, le backend appelle un **Validator Container** séparé qui encapsule Mustang et renvoie un rapport de validation (voir section 32)                                                     | Future - à examiner en priorité avant toute reconstruction interne (recommandation de `03-market-analysis.md`, section 17) ; hors périmètre de validation XML fine au MVP (section 28) |
| Service d'email (notifications, récupération de compte)                                   | Fonctionnement de base de l'authentification (section 17 auth)                                                                                                     | Indispensable au MVP                                                                                                                   | Fournisseur non tranché ici                                                                                                                                                                                                                                                                                        | P0                                                                                                                                                                                     |
| Stockage de documents                                                                     | Conserver les documents importés pour analyse                                                                                                                      | Nécessaire pour FR-INVOICE-001                                                                                                         | **Résolu au MVP : filesystem local**, abstrait derrière une `StorageInterface` (implémentation `LocalFilesystemStorage`) - acceptable pour développement/MVP, pas une architecture définitive de production à grande échelle ; une implémentation future `S3Storage` doit rester possible sans réécrire l'appelant | P0                                                                                                                                                                                     |
| Plateforme(s) agréée(s)                                                                   | Orientation de l'utilisateur vers l'émission réelle (parcours 6, section 12), sans que notre produit n'émette lui-même                                             | Cohérence avec le positionnement complémentaire (section 7)                                                                            | Aucune intégration technique nécessaire au MVP - un simple renvoi informatif suffit                                                                                                                                                                                                                                | Future                                                                                                                                                                                 |
| Fournisseur IA (section 17)                                                               | Reformulation pédagogique des résultats                                                                                                                            | Améliore l'expérience d'explicabilité                                                                                                  | **Résolu : Mistral.** Flux à respecter : `Compliance Engine → Result → Mistral → Explanation` - Mistral reformule pédagogiquement un résultat déjà déterminé par le moteur déterministe, il ne détermine jamais lui-même la conformité (voir section 32)                                                           | P1 - le Compliance Engine déterministe doit pouvoir fonctionner sans cette dépendance (section 17)                                                                                     |
| Paiement                                                                                  | Non nécessaire au cœur du MVP : le modèle économique retenu à titre provisoire (freemium + abonnement Pro, section 32) ne requiert pas d'intégration PSP immédiate | Prévoir une architecture extensible (`Subscription`, `Plan`, `SubscriptionStatus`) sans intégrer Stripe ni un autre PSP dès maintenant | -                                                                                                                                                                                                                                                                                                                  | Future                                                                                                                                                                                 |
| Comptabilité                                                                              | Hors périmètre produit (section 7)                                                                                                                                 | -                                                                                                                                      | -                                                                                                                                                                                                                                                                                                                  | Hors scope                                                                                                                                                                             |

## 23. Données métier nécessaires

> Catégories de données identifiées à partir des exigences ci-dessus, sans modélisation relationnelle (celle-ci relève de `07-data-model.md`).

- **Utilisateur** - identité du compte, informations de connexion.
- **Entreprise** - informations légales de base, statut TVA (assujetti/redevable/franchise en base), taille, date de référence.
- **Client** (au sens de la facture analysée) - statut (professionnel français, particulier, étranger), informations nécessaires aux vérifications de mentions (par exemple SIREN si professionnel français).
- **Facture** (importée ou saisie à des fins d'analyse) - mentions présentes, montants, dates, nature de l'opération.
- **Ligne de facture** - le cas échéant, si nécessaire pour vérifier une mention au niveau ligne plutôt qu'au niveau facture globale.
- **Document** - fichier importé associé à une facture analysée.
- **Règle de conformité** - définition, source, version, date d'entrée en vigueur (et de fin le cas échéant).
- **Résultat de conformité** - statut par vérification, règle appliquée, version, date, explication associée.
- **Diagnostic d'éligibilité** - résultat du parcours 2/9, distinct du résultat d'analyse d'une facture spécifique.
- **Notification** (si implémentée, section 19) - type, échéance associée, statut lu/non lu.

## 24. Audit et historique

Besoins fonctionnels : le système doit pouvoir répondre a posteriori à une question du type « pourquoi cette facture était-elle considérée comme non conforme le [date] ? » en retrouvant :

- l'analyse effectuée à cette date ;
- chaque résultat de vérification individuel produit à ce moment ;
- la règle et la **version de la règle** appliquées à cette date (cohérent avec le besoin de versionnement de `02-regulatory-study.md`, section 21-22) ;
- les actions éventuellement effectuées par l'utilisateur suite à ce résultat (par exemple relance d'une nouvelle analyse après correction).

Cette exigence est directement liée à l'auditabilité (section 14) et à l'explicabilité (section 18) : elle ne doit pas être traitée comme une fonctionnalité secondaire, mais comme une conséquence structurelle du principe de traçabilité du produit.

## 25. Business Rules

**BR-COMPLIANCE-001**
Une analyse de conformité doit être effectuée en fonction du contexte de l'entreprise (statut TVA, taille), du type d'opération (biens/services/mixte, domestique/international) et des données disponibles sur la facture analysée.

**BR-COMPLIANCE-002**
Un résultat de conformité ne peut être affiché sans que la règle et la source qui le fondent soient également accessibles à l'utilisateur (voir section 18).

**BR-COMPLIANCE-003**
Une donnée manquante nécessaire à une vérification produit l'état `A_VERIFIER` pour cette vérification, jamais un état `NON_CONFORME` par défaut - une absence d'information ne doit jamais être interprétée comme une preuve de non-conformité.

**BR-COMPLIANCE-004**
Une règle marquée comme incertaine dans `02-regulatory-study.md` (section 23) ne peut produire qu'un résultat `INCERTAIN_REGLEMENTAIRE`, jamais `CONFORME` ou `NON_CONFORME` de façon catégorique.

**BR-ELIGIBILITY-001**
Une entreprise en franchise en base de TVA reste éligible aux vérifications de la réforme (assujettie même si non redevable), conformément à `02-regulatory-study.md`, section 6 - le système ne doit jamais exclure automatiquement une entreprise en franchise en base du diagnostic.

**BR-SCOPE-001**
Le système ne doit jamais présenter une fonctionnalité de vérification comme une émission ou une transmission réelle de facture au sens réglementaire (section 7) - la distinction doit rester explicite dans toute l'expérience produit.

## 26. Critères d'acceptation

> Critères de haut niveau pour les fonctionnalités P0 du MVP. Le détail exhaustif relève de `09-test-strategy.md`.

**FR-DIAGNOSTIC-001**

```text
Given une entreprise dont le statut TVA et la taille sont renseignés
When l'utilisateur demande un diagnostic d'éligibilité
Then le système indique si l'entreprise est concernée par la réforme
et à partir de quelle date pour la réception et pour l'émission.
```

**FR-COMPLIANCE-001**

```text
Given une facture importée ou saisie avec des données suffisantes
When l'utilisateur lance une analyse de conformité
Then le système retourne un statut global
et le détail de chaque vérification individuelle effectuée.
```

**FR-COMPLIANCE-002**

```text
Given un résultat de vérification non conforme, en avertissement ou incertain
When l'utilisateur consulte le détail de ce résultat
Then le système affiche une explication en langage courant
et la règle/source réglementaire associée.
```

**FR-COMPLIANCE-003**

```text
Given un résultat de vérification non conforme
When l'utilisateur consulte le détail de ce résultat
Then le système propose une action de correction concrète et actionnable.
```

**FR-COMPLIANCE-004**

```text
Given un document importé de type PDF simple sans données structurées
When l'utilisateur soumet ce document pour analyse
Then le système explique explicitement pourquoi ce document
ne constitue pas une facture électronique conforme au sens de la réforme.
```

## 27. Métriques produit

### Activation

- Entreprise créée avec statut TVA et taille renseignés (condition d'accès au diagnostic).
- Premier diagnostic d'éligibilité obtenu.
- Première facture analysée.

_Pourquoi_ : ces trois jalons correspondent au chemin minimal permettant à l'utilisateur d'atteindre la première valeur perçue du produit (comprendre, puis vérifier).

### Engagement

- Nombre d'analyses de conformité effectuées par utilisateur actif.
- Consultation du détail d'une explication (et pas seulement du statut global) - indicateur de l'usage réel de la couche pédagogique, qui est le cœur de la différenciation produit.
- Utilisation du dashboard.

_Pourquoi_ : mesurer l'engagement au niveau de l'explication (pas seulement du statut) permet de vérifier concrètement si la proposition de valeur pédagogique est perçue et utilisée, et pas seulement la fonction de vérification brute.

### Valeur

- Proportion de non-conformités détectées suivies d'une nouvelle analyse (proxy d'une tentative de correction).
- Temps entre la détection d'une non-conformité et une nouvelle analyse indiquant sa résolution.

_Pourquoi_ : ces indicateurs permettent d'estimer si le produit aide réellement à corriger, et pas seulement à diagnostiquer.

### Rétention

- Retour d'un utilisateur pour une nouvelle analyse au-delà de la première session.
- Usage du produit après la première échéance réglementaire pertinente pour l'utilisateur (signal directement lié au risque de pérennité identifié dans `03-market-analysis.md`, section 15).

_Pourquoi_ : ce dernier point est spécifiquement destiné à tester l'hypothèse de pérennité au-delà de la phase de mise en conformité initiale, un risque explicitement identifié dans l'étude de marché.

> Aucun chiffre cible n'est fixé dans ce document, conformément à la consigne : ces métriques définissent ce qu'il faut mesurer, pas des objectifs chiffrés qui n'ont pas de base empirique à ce stade.

## 28. Limitations connues du MVP

- Le MVP ne couvre pas l'ensemble des cas réglementaires identifiés dans `02-regulatory-study.md` (par exemple, les opérations internationales complexes ou les régimes de TVA particuliers au-delà de la franchise en base) ; son périmètre de règles doit être volontairement restreint (voir section 10).
- Le MVP ne vérifie pas la validité technique fine d'un format structuré (Factur-X/UBL/CII) au niveau XML - cette capacité, si elle est ajoutée, devrait s'appuyer sur des outils tiers existants plutôt que d'être reconstruite (section 22).
- Le MVP ne se connecte à aucune plateforme agréée ni service gouvernemental de vérification d'entreprise ; les données saisies par l'utilisateur ne sont donc pas vérifiées automatiquement contre une source officielle.
- Le MVP ne couvre pas les cas de changement de régime en cours d'année (par exemple franchissement de seuil de franchise en base en cours d'exercice).
- L'assistant IA (section 17) ne remplace pas un conseil fiscal ou juridique professionnel ; le produit doit rester transparent sur cette limite.
- Le dashboard du MVP reste une vue simplifiée (section 20), sans indicateurs de tendance avancés.
- Le produit ne certifie ni ne garantit juridiquement la conformité d'une entreprise : il fournit une aide à la vérification, pas une certification opposable (voir aussi section 29, risques).

## 29. Risques produit

| Risque                                                             | Description                                                                                                                                                   | Impact                                                            | Mitigation envisagée                                                                                                         |
| ------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Mauvaise interprétation réglementaire                              | Une règle codée dans le Compliance Engine s'écarterait de la réglementation réelle                                                                            | Élevé - atteinte à la confiance et risque pour l'utilisateur      | Traçabilité systématique vers `02-regulatory-study.md` (BR-COMPLIANCE-002) ; revue périodique du contenu réglementaire       |
| Règles qui évoluent sans mise à jour du produit                    | La réglementation change (comme documenté en 2026, `02-regulatory-study.md` section 4) sans que le moteur soit mis à jour                                     | Élevé                                                             | Mécanisme de versionnement des règles (section 24) ; veille réglementaire continue (hors périmètre technique de ce document) |
| Faux positifs (facture signalée non conforme à tort)               | Génère de la défiance et de la confusion inutile                                                                                                              | Moyen à élevé                                                     | États intermédiaires (`A_VERIFIER`, `AVERTISSEMENT`) plutôt qu'un binaire strict (section 10)                                |
| Faux négatifs (facture signalée conforme à tort)                   | Risque direct pour l'utilisateur (sanction potentielle non anticipée)                                                                                         | Élevé                                                             | Limitations explicitement communiquées (section 28) ; ne jamais présenter un résultat comme une garantie absolue             |
| Confiance excessive de l'utilisateur envers le produit             | L'utilisateur pourrait considérer le produit comme une garantie juridique                                                                                     | Élevé                                                             | Communication explicite des limites (section 28) ; le produit reste un outil d'aide, pas une certification                   |
| Dépendance à des services externes (IA, vérification d'entreprise) | Indisponibilité ou changement de ces services impacterait le produit                                                                                          | Moyen                                                             | Le Compliance Engine déterministe doit pouvoir fonctionner sans la couche IA (section 17)                                    |
| Complexité excessive                                               | Le produit dérive vers une richesse fonctionnelle qui nuit à la simplicité pour la cible primaire                                                             | Moyen - contraire au principe de simplicité (`01-intent-note.md`) | Discipline de scope stricte (sections 7, 30)                                                                                 |
| Mauvaise compréhension de la cible                                 | Les hypothèses de `03-market-analysis.md` (section 23) s'avèrent fausses (par exemple, la cible ne souhaite pas d'outil distinct de son outil de facturation) | Élevé - remettrait en cause la proposition de valeur elle-même    | Validation prévue par entretiens utilisateurs avant investissement massif (`03-market-analysis.md`, section 23)              |

## 30. Hors périmètre définitif

Pour se protéger explicitement contre le risque de dérive « puisqu'on a déjà une facture, ajoutons... » :

- **Pas de génération ou d'émission réelle de factures** destinées à être transmises à un client (section 7).
- **Pas de comptabilité** (déclarations fiscales, bilan, liasse).
- **Pas de gestion de la paie.**
- **Pas de gestion des notes de frais ou des achats fournisseurs.**
- **Pas de CRM ni de gestion commerciale** (pipeline, opportunités).
- **Pas de rôle de plateforme agréée**, ni d'ambition d'en devenir une à court ou moyen terme -
  **précision (Phase 15)** : l'introduction d'un rôle `PlatformAdministrator` interne (section
  21.2) est une capacité opérationnelle de support/communication, elle ne rapproche en rien le
  produit d'une plateforme agréée au sens réglementaire (`02-regulatory-study.md`, section 23) ;
  cette exclusion reste pleinement valide.
- **Pas de paiement intégré** (encaissement, relances de paiement) au MVP.
- ~~**Pas de multi-utilisateurs/rôles avancés** au MVP (section 21).~~ **Levé par décision
  produit (DEC-009, 21/08/2026, Phase 14)** : les rôles d'organisation (`OWNER`/`ADMIN`/
  `COLLABORATOR`, section 21.1) sont désormais engagés avant la mise en production. Cette
  levée ne concerne que les rôles **au sein d'une organisation** - elle ne rouvre aucune des
  autres exclusions de cette liste.

Toute proposition de fonctionnalité qui rapprocherait le produit de l'une de ces catégories doit être explicitement réévaluée au regard de la vision (`01-intent-note.md`) avant d'être intégrée à une itération future.

## 31. Décisions produit

| ID      | Décision                                                                                                                                                             | Justification                                                                                                   |
| ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| DEC-001 | Le produit est centré sur la compréhension et la vérification de la conformité, pas sur la production ou l'émission de factures                                      | `01-intent-note.md`, section 3 ; `03-market-analysis.md`, section 19                                            |
| DEC-002 | Le Compliance Engine déterministe est la seule source de vérité pour un résultat de conformité ; l'IA n'a qu'un rôle d'assistance et de reformulation                | Consigne du brief, section 20 ; `02-regulatory-study.md`, section 22                                            |
| DEC-003 | ~~Le MVP se limite à un rôle utilisateur unique (propriétaire du compte)~~ - **Révisé par DEC-009** (21/08/2026) | Cohérence avec les personas primaires (mono-utilisateur), simplicité (`01-intent-note.md`) - valable pour le MVP (Phases 0-12), révisé ensuite |
| DEC-004 | Les états de conformité incluent des états intermédiaires (`A_VERIFIER`, `AVERTISSEMENT`, `INCERTAIN_REGLEMENTAIRE`), pas seulement un binaire conforme/non conforme | Reflète les zones d'incertitude documentées dans `02-regulatory-study.md`, section 23                           |
| DEC-005 | Le produit ne se connecte à aucune plateforme agréée ni service externe critique au MVP                                                                              | Faisabilité pour un développeur solo (`03-market-analysis.md`, section 17)                                      |
| DEC-006 | Le dashboard et l'historique sont priorité P1, pas P0                                                                                                                | La valeur centrale (comprendre/vérifier une facture donnée) ne dépend pas d'une vue d'ensemble pour être testée |
| DEC-007 | Toute non-conformité affichée doit être accompagnée d'une explication et d'une action de correction, sans exception                                                  | Cœur de la différenciation produit (`03-market-analysis.md`, section 18)                                        |
| DEC-008 | Le produit reste explicitement complémentaire à l'outil de facturation existant de l'utilisateur, jamais substitutif au MVP                                          | `01-intent-note.md` ; réduit le risque de concurrence frontale (`03-market-analysis.md`, section 6)             |
| DEC-009 | Rôles d'organisation `OWNER`/`ADMIN`/`COLLABORATOR` (section 21.1), portés par `Membership` ; un `User` peut appartenir à plusieurs `Organization` avec un rôle différent dans chacune | Décision produit du 21/08/2026 - l'utilisateur souhaite une application complète avant le lancement, réouvre le persona secondaire C (cabinet comptable) plus tôt que prévu ; architecture déjà anticipée (`06-technical-architecture.md` section 19/39) |
| DEC-010 | Rôle plateforme `PlatformAdministrator` (section 21.2), structurellement séparé des rôles d'organisation, jamais un indicateur sur `User` ; MFA obligatoire ; surface d'administration en application séparée si le coût reste raisonnable pour un développeur solo, sinon route strictement isolée ; test d'intrusion ciblé avant activation | Franchit délibérément et de façon contrôlée l'isolation tenant posée depuis la Phase 2 (`06-technical-architecture.md` ADR-004) - décision qui exige son propre ADR (ADR-009) et sa propre revue de sécurité, jamais une exception glissée dans le code existant |
| DEC-011 | Statistiques et graphiques agrégés (FR-ANALYTICS-001/002) réservés à la surface d'administration plateforme, jamais au dashboard utilisateur final (section 20) | Ne contredit pas DL-008 (`12-roadmap.md`, « pas de graphiques au MVP ») qui portait exclusivement sur le dashboard client - distinction de périmètre assumée, pas une réouverture silencieuse de DL-008 |

## 32. Questions ouvertes

| Question                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | Pourquoi elle est importante                                                                                                                                                                                                                                                                                                               | Document qui devra la résoudre |
| --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------ |
| ~~Quelle architecture technique pour le Compliance Engine~~ - **Résolu : moteur de règles configurable + implémentation métier explicite.** Architecture retenue : `Rule Registry` (référentiel des règles), `Rule Versions` (versions immuables), `Rule Evaluators` (logique d'évaluation, codée), `Context Builder` (résolution du contexte), `Evaluation Engine` (orchestration), `Findings` (résultats). Les règles sont des objets structurés et versionnés, mais leur exécution est codée explicitement (pas un moteur générique ultra-abstrait) - voir `06-technical-architecture.md` (section 9, mise à jour) et `07-data-model.md` (sections 15-16, mises à jour). | Résolu - décision produit                                                                                                                                                                                                                                                                                                                  |
| ~~Quel modèle de données pour une règle versionnée~~ - **Résolu**, avec un raffinement : `ComplianceRule` (identité stable de la règle : code, catégorie, sévérité, référence légale) distincte de `ComplianceRuleVersion` (version : `effective_from`/`effective_to`, statut, configuration), et `ComplianceEvaluation` (association `rule_version_id` + `invoice_id` + résultat + date), cohérent avec `07-data-model.md` (sections 15-18, mises à jour).                                                                                                                                                                                                                 | Résolu - décision produit                                                                                                                                                                                                                                                                                                                  |
| ~~Faut-il une intégration technique avec un outil de validation Factur-X existant~~ - **Résolu : oui**, plutôt que réimplémenter un parseur complet. Outil retenu à évaluer en priorité : Mustangproject (support Factur-X/CII/UBL, règles françaises incluses dans ses versions récentes), exécuté comme un composant de validation **isolé** (conteneur séparé, appelé depuis Symfony par HTTP ou process, jamais intégré directement dans le runtime PHP) - voir `06-technical-architecture.md` (section 11, mise à jour).                                                                                                                                               | Résolu - décision produit, isolation technique à respecter                                                                                                                                                                                                                                                                                 |
| Quelle stratégie d'authentification ?                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | - **Résolu : JWT.** Access token à courte durée de vie conservé en mémoire côté frontend (jamais `localStorage`), refresh token en cookie `HttpOnly`/`Secure`/`SameSite`, Symfony restant l'autorité d'émission et de validation - voir `06-technical-architecture.md` (ADR-007) et `10-security-privacy.md` (section 12, déjà détaillée). | Résolu - décision produit      |
| ~~Quel modèle économique~~ - **Décision provisoire : Freemium** (offre gratuite avec nombre d'analyses limité + dashboard + conformité de base ; offre Pro avec analyses illimitées, historique, documents, IA, fonctionnalités avancées). Les prix ne sont pas fixés à ce stade. **Cette décision reste soumise à validation marché** (`03-market-analysis.md`, section 23, mise à jour) - ce n'est pas encore un modèle économique définitif, mais une hypothèse de travail suffisante pour concevoir l'architecture (`07-data-model.md`, section 24).                                                                                                                    | Décision provisoire, validation marché requise avant confirmation définitive                                                                                                                                                                                                                                                               |
| ~~Quelle stratégie de vérification externe des données d'entreprise (SIREN)~~ - **Résolu : intégration d'une source officielle/API publique (type API Sirene/INSEE), avec cache**, non bloquante pour l'ensemble du MVP (dégradation gracieuse déjà prévue, `06-technical-architecture.md` section 22).                                                                                                                                                                                                                                                                                                                                                                     | Résolu - décision produit, non bloquante                                                                                                                                                                                                                                                                                                   |

## 32 bis. Correction de positionnement (mise à jour)

Un point de positionnement mérite d'être réaffirmé explicitement à la lumière de la vérification réglementaire complémentaire (`02-regulatory-study.md`, section 23) : les entreprises devront nécessairement passer par une **plateforme agréée** pour les flux réels d'e-invoicing et d'e-reporting ; une simple solution compatible - ce que ce produit est et reste - ne peut assurer elle-même les fonctions réservées à une plateforme agréée. Cette contrainte ne change rien au périmètre déjà défini dans ce document (section 7, section 30 : notre produit n'a jamais eu vocation à devenir une plateforme agréée), mais la reformulation suivante est retenue pour clarifier le positionnement dans les communications produit et les documents suivants : _« un assistant de préparation, de contrôle et de compréhension de la conformité, qui aide le TPE/micro-entrepreneur à comprendre ce qu'il doit corriger et à se préparer à utiliser sa plateforme agréée »_. Cohérent avec `01-intent-note.md` et sans élargissement de périmètre.

## 33. Impact sur les prochains documents

- **`05-user-stories.md`** doit transformer les parcours de la section 12 et les personas de la section 3 en user stories détaillées, en respectant strictement le périmètre défini en sections 7, 8 et 30.
- **`06-technical-architecture.md`** doit répondre aux questions ouvertes de la section 32 concernant le Compliance Engine, l'IA et les intégrations, en respectant le principe DEC-002 (moteur déterministe comme source de vérité).
- **`07-data-model.md`** doit modéliser les catégories de données de la section 23, avec une attention particulière au versionnement des règles (section 24) et à la structure des résultats de conformité (section 11).
- **`08-api-specification.md`** doit définir les contrats permettant les parcours de la section 12, notamment l'analyse de conformité (FR-COMPLIANCE-001) et sa gestion des erreurs (section 15).
- **`09-test-strategy.md`** doit détailler les critères d'acceptation de haut niveau de la section 26 et couvrir les cas de faux positifs/négatifs identifiés comme risques en section 29.
- **`10-security-privacy.md`** doit détailler les exigences de confidentialité et de sécurité esquissées en section 14, notamment concernant le stockage de documents et l'isolation des données entre entreprises.
- **`11-frontend-design-system.md`** doit traduire visuellement le triptyque comprendre/vérifier/corriger (section 6) et les états de conformité (section 10), en cohérence avec l'exigence d'accessibilité (section 14).
- **`12-roadmap.md`** doit séquencer les priorités P0/P1/P2/Future des sections 8 et 13, et intégrer la question du modèle économique laissée ouverte en section 32.

## Préparation des User Stories

Les grands domaines fonctionnels suivants, issus de ce PRD, devront être transformés en User Stories / Use Cases dans `05-user-stories.md` :

- **Onboarding** - création de compte, configuration initiale de l'entreprise (parcours 1-2).
- **Diagnostic d'éligibilité** - parcours 9, FR-DIAGNOSTIC-001.
- **Gestion de l'entreprise** - statut TVA, taille (section 9, module Entreprise).
- **Gestion des clients** (au sens minimal défini en section 9).
- **Import et saisie de factures** - parcours 3, FR-INVOICE-001.
- **Analyse de conformité** - parcours 4, FR-COMPLIANCE-001 à 004.
- **Correction et re-vérification** - parcours 5.
- **Historique** - parcours 7, FR-HISTORY-001.
- **Dashboard** - parcours 8, FR-DASHBOARD-001.
- **Explicabilité et signalement** - section 18, FR-TRUST-001.
- **Notifications** (si retenues en V1, section 19).

Ce document ne rédige pas les user stories elles-mêmes ; il fournit la base fonctionnelle nécessaire à leur rédaction.
