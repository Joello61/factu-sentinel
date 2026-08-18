# Note d'intention — Assistant de conformité à la facturation électronique pour les TPE et indépendants

## 1. Résumé exécutif

La France met en œuvre une réforme de la facturation électronique qui va modifier progressivement la manière dont les entreprises émettent, reçoivent et transmettent leurs factures. Cette réforme représente une source d'incertitude importante pour les micro-entrepreneurs, indépendants, freelances et TPE, qui ne disposent généralement pas d'un service comptable ou informatique dédié pour anticiper et gérer ce changement.

Ce projet vise à construire un **assistant de conformité et de préparation à la facturation électronique**, destiné à aider ces petites structures à comprendre leurs obligations, vérifier l'état de conformité de leurs factures, identifier les erreurs, et se préparer sereinement à la réforme.

Il ne s'agit pas, à ce stade, d'un logiciel de facturation classique. L'ambition initiale est de résoudre un problème de compréhension et de conformité, la génération de factures pouvant éventuellement venir en complément, mais sans en constituer le cœur de la proposition de valeur.

Cette note d'intention pose les fondations du projet. Elle ne constitue ni une étude réglementaire, ni un cahier des charges fonctionnel : ces éléments seront développés dans les documents suivants du projet.

## 2. Contexte

La réforme française de la facturation électronique introduit de nouvelles obligations concernant l'émission, la réception et la transmission de certaines informations de facturation entre entreprises. Le calendrier précis, le périmètre exact des entreprises concernées, et le détail des obligations applicables devront être vérifiés et documentés dans `docs/02-regulatory-study.md`.

Ce que l'on peut affirmer sans ambiguïté, c'est que cette réforme crée un besoin d'information et d'accompagnement pour les petites entreprises, qui doivent généralement répondre à des questions telles que :

- Qu'est-ce qui va devenir obligatoire, et à partir de quand ? *(À confirmer dans `02-regulatory-study.md`.)*
- Quelles factures et quelles entreprises sont concernées ? *(À confirmer dans `02-regulatory-study.md`.)*
- Quelles informations doivent obligatoirement figurer sur une facture ? *(À confirmer dans `02-regulatory-study.md`.)*
- Comment se préparer, et par où commencer ?
- Comment vérifier que l'on est en conformité ?
- Comment fonctionnent les nouveaux circuits de transmission des factures ? *(À confirmer dans `02-regulatory-study.md`.)*
- Comment éviter les erreurs et les oublis ?

Pour une entreprise disposant d'un service comptable structuré, ces questions sont généralement prises en charge par des professionnels ou des outils métiers. Pour un micro-entrepreneur ou une TPE, ce n'est généralement pas le cas : l'information réglementaire est dispersée, le vocabulaire est technique, et le risque de non-conformité — perçu ou réel — peut générer une inquiétude difficile à lever seul.

## 3. Problème

Le problème central que ce projet cherche à adresser peut se formuler ainsi :

> Les petites entreprises françaises n'ont ni le temps, ni les ressources, ni nécessairement les connaissances pour comprendre seules ce que la réforme de la facturation électronique implique concrètement pour elles, et pour vérifier que leurs pratiques de facturation sont conformes.

Ce problème se décline en plusieurs difficultés concrètes, détaillées en section 6.

Il est important de noter que ce problème est actuellement formulé comme une **hypothèse de travail**, cohérente avec le contexte réglementaire, mais qui devra être confrontée à la réalité du terrain (voir section 12 — Hypothèses).

## 4. Vision

**Vision produit (moyen/long terme) :**

> Devenir un point de repère simple et fiable permettant aux petites entreprises françaises de comprendre leurs obligations en matière de facturation électronique, de vérifier leur conformité, et d'être accompagnées dans leur mise en conformité — sans avoir besoin de compétences comptables ou techniques particulières.

Cette vision est volontairement formulée de façon mesurée et progressive. Le projet est initié par une équipe réduite (potentiellement un développeur solo dans un premier temps), et la vision doit rester cohérente avec cette réalité : il s'agit de construire d'abord un outil utile et fiable sur un périmètre restreint, avant d'envisager une extension du produit.

La vision n'implique pas, à ce stade, de devenir un acteur réglementaire (comme une Plateforme de Dématérialisation Partenaire), ni de remplacer les outils comptables existants (voir section 11 — Hors périmètre).

## 5. Public cible

### Cible primaire (MVP)

- Micro-entrepreneurs (auto-entrepreneurs)
- Freelances et indépendants
- Très petites entreprises (TPE) sans service comptable ou informatique dédié
- Dirigeants de petites structures qui gèrent eux-mêmes leur facturation

Ces profils partagent une caractéristique commune : ils gèrent souvent seuls leurs obligations administratives et n'ont pas nécessairement accès à un conseil comptable régulier et approfondi.

### Cible secondaire (envisageable, non prioritaire pour le MVP)

- Assistants ou responsables administratifs de petites entreprises
- Dirigeants de petites entreprises (au-delà des TPE les plus petites) qui délèguent une partie de l'administratif

### Utilisateurs potentiels futurs (hors MVP, à explorer ultérieurement)

- Cabinets comptables souhaitant proposer un outil de vérification à leurs clients TPE
- Réseaux d'accompagnement à l'entrepreneuriat (structures d'aide à la création d'entreprise, par exemple)

> **Note :** Il ne faut pas présumer que l'ensemble de ces populations seront ciblées simultanément. Le MVP doit se concentrer sur la cible primaire. L'intérêt réel de la cible secondaire et des utilisateurs futurs reste une hypothèse à valider (voir section 12).

## 6. Problèmes utilisateurs

Les problèmes identifiés ci-dessous sont structurés par nature, sans hiérarchisation chiffrée (aucune donnée de marché n'est disponible à ce stade) :

**Compréhension de la réforme**
- Difficulté à comprendre ce que la réforme change concrètement pour son activité.
- Incertitude sur les obligations qui s'appliquent réellement à sa situation.
- Vocabulaire réglementaire et administratif difficile d'accès pour un non-spécialiste.
- Informations dispersées entre plusieurs sources officielles et non officielles.

**Conformité des factures**
- Difficulté à identifier les mentions obligatoires sur une facture.
- Risque d'erreurs ou d'oublis répétés sans en avoir conscience.
- Absence de visibilité claire sur son propre niveau de conformité.
- Difficulté à comprendre les nouveaux formats et circuits de transmission.

**Préparation et sérénité**
- Peur de ne pas être conforme à une date donnée, sans savoir comment vérifier ou corriger.
- Complexité perçue des démarches à entreprendre pour se préparer.
- Absence d'accompagnement adapté à une structure de très petite taille.

Cette liste n'est pas exhaustive et pourra être enrichie ou révisée à mesure que le projet avance, notamment après l'étude réglementaire et l'analyse de marché.

## 7. Proposition de valeur

> **Pourquoi une petite entreprise utiliserait-elle ce produit ?**

Parce qu'il transforme une problématique réglementaire complexe, anxiogène et dispersée en un parcours simple, guidé et compréhensible, sans nécessiter de compétences comptables ou techniques.

Le produit doit permettre à son utilisateur de :

1. **Comprendre** — ce qui s'applique à sa situation, en langage clair.
2. **Vérifier** — si ses factures actuelles répondent aux exigences attendues.
3. **Corriger** — les erreurs identifiées, avec des explications compréhensibles.
4. **Se préparer** — aux échéances et aux nouveaux circuits de facturation.
5. **Suivre** — l'évolution de son niveau de conformité dans le temps.

Le positionnement doit rester :

| Caractéristique | Description |
|---|---|
| Simple | Accessible sans connaissances comptables ou techniques préalables |
| Pédagogique | Explique le "pourquoi", pas seulement le "quoi" |
| Rassurant | Réduit l'anxiété liée à la conformité plutôt que de l'accentuer |
| Orienté action | Indique toujours une prochaine étape concrète |
| Centré sur la conformité | La conformité est la finalité, la facturation est un moyen |

> **Note :** Ce positionnement distingue volontairement le produit d'un simple "logiciel de facturation". La génération de factures pourra faire partie du produit à terme, mais elle doit rester au service de la conformité, et non l'inverse (voir section 3 du brief initial).

## 8. Principes produit

Ces principes serviront de référence pour les documents suivants (PRD, architecture, design system) :

1. **Simplicité avant complexité** — chaque fonctionnalité doit d'abord être compréhensible par un non-spécialiste.
2. **Conformité avant automatisation** — l'automatisation ne doit jamais se faire au détriment de la fiabilité ou de la compréhension des règles appliquées.
3. **Pédagogie systématique** — toute information de conformité doit être accompagnée d'une explication, pas seulement d'un verdict.
4. **Transparence** — l'utilisateur doit toujours pouvoir comprendre pourquoi une facture est jugée conforme ou non.
5. **Explicabilité des contrôles** — les règles de conformité appliquées doivent être traçables et justifiables.
6. **Privacy by design** — les données manipulées (factures, informations clients, données d'entreprise) sont sensibles et doivent être protégées dès la conception.
7. **Sécurité par défaut** — la sécurité n'est pas une option ajoutée après coup, mais une contrainte de conception.
8. **Expérience orientée action** — à chaque étape, l'utilisateur doit savoir ce qu'il doit faire ensuite.

## 9. Périmètre général

À un niveau de description volontairement général (le détail fonctionnel précis relève de `docs/04-product-requirements.md`), le produit pourrait couvrir les grandes capacités suivantes :

- Gestion des informations de l'entreprise utilisatrice
- Gestion des clients de l'entreprise utilisatrice
- Création et/ou import de factures
- Analyse de conformité des factures
- Moteur de règles de conformité
- Détection et explication des erreurs
- Recommandations de correction
- Suivi de l'état de conformité dans le temps
- Gestion et historique des documents
- Tableau de bord de conformité
- Notifications liées aux échéances ou anomalies
- Assistant conversationnel d'aide à la compréhension
- Préparation à l'utilisation des futurs circuits de facturation électronique
- Intégrations externes (à définir ultérieurement)

> **Important :** cette liste décrit des capacités possibles à un niveau macro. Elle ne constitue pas un engagement fonctionnel et ne préjuge pas de ce qui sera effectivement développé, ni dans quel ordre.

## 10. Vision du MVP

Le MVP doit être pensé comme un outil volontairement restreint, capable de démontrer la valeur centrale du produit — **comprendre et vérifier sa conformité** — sans chercher à couvrir l'ensemble du périmètre décrit en section 9.

### Ce que le MVP devrait probablement faire

- Permettre à l'utilisateur de renseigner les informations essentielles de son entreprise.
- Permettre l'import ou la saisie de factures (dans un format à déterminer ultérieurement).
- Analyser ces factures au regard d'un ensemble de règles de conformité de base.
- Expliquer clairement, en langage courant, les non-conformités détectées.
- Proposer des pistes de correction compréhensibles.
- Offrir une vision synthétique (tableau de bord simple) de l'état de conformité.

### Ce que le MVP ne devrait probablement PAS faire au départ

- Devenir une Plateforme de Dématérialisation Partenaire (PDP) ou un acteur agréé de transmission de factures.
- Couvrir l'intégralité des cas métiers et régimes fiscaux particuliers dès la première version.
- Remplacer un logiciel comptable ou un expert-comptable.
- Développer des intégrations complexes avec des systèmes tiers avant d'avoir validé le besoin central.
- Prendre en charge automatiquement la transmission réglementaire des factures — le produit pourra, si pertinent, s'appuyer sur des services externes existants plutôt que de reconstruire ces briques.

Aucune décision technique ou réglementaire définitive n'est prise dans cette note : ces choix relèvent des documents `02-regulatory-study.md` et `06-technical-architecture.md`.

## 11. Hors périmètre

Les éléments suivants sont explicitement exclus de l'ambition initiale du projet, afin de prévenir toute dérive de périmètre (*scope creep*) :

- Devenir une Plateforme de Dématérialisation Partenaire (PDP) agréée.
- Remplacer intégralement un logiciel de comptabilité.
- Devenir un ERP ou un outil de gestion d'entreprise généraliste.
- Prendre en charge l'ensemble de la comptabilité d'une entreprise (déclarations fiscales, bilan, etc.).
- Couvrir immédiatement tous les cas métiers ou régimes particuliers (TVA intracommunautaire complexe, groupes d'entreprises, secteurs très spécifiques, etc.).
- Développer l'ensemble des intégrations externes envisageables dès le MVP.

Ces exclusions concernent l'ambition **initiale** du projet. Elles pourront être réévaluées après validation du besoin et de la traction du produit, mais ne doivent pas être anticipées dans les documents de conception actuels.

## 12. Hypothèses

Les éléments suivants sont des **hypothèses de travail**, et non des faits établis. Elles devront être confirmées ou infirmées au fil du projet, notamment via l'étude réglementaire et l'analyse de marché.

- *Hypothèse* — les petites entreprises rencontrent un problème suffisamment réel et significatif face à la réforme de la facturation électronique. *À valider.*
- *Hypothèse* — ces entreprises seraient prêtes à utiliser un outil dédié plutôt que de se reposer uniquement sur leur expert-comptable ou sur des recherches personnelles. *À valider.*
- *Hypothèse* — elles préfèrent un assistant pédagogique à un simple logiciel de facturation. *À valider.*
- *Hypothèse* — elles ont besoin d'explications compréhensibles plutôt que d'un simple score ou indicateur de conformité. *À valider.*
- *Hypothèse* — certaines fonctionnalités (transmission réglementaire, génération de formats spécifiques) pourront être déléguées à des services externes spécialisés plutôt que développées en interne. *Dépend de l'étude réglementaire et de l'étude technique.*
- *Hypothèse* — le calendrier et les obligations précises de la réforme, tels que perçus à ce stade, sont conformes à la réalité. *Dépend entièrement de `02-regulatory-study.md`.*
- *Hypothèse* — il existe un espace de différenciation par rapport aux logiciels de facturation existants. *Dépend de `03-market-analysis.md`.*

## 13. Critères de réussite

En l'absence de données de marché ou d'usage à ce stade, les critères de réussite retenus ici sont qualitatifs. Des métriques quantitatives précises pourront être définies ultérieurement, une fois le produit en usage réel.

- L'utilisateur comprend rapidement, et dans ses propres termes, son niveau de conformité.
- L'utilisateur comprend pourquoi une facture donnée est considérée comme non conforme.
- L'utilisateur sait quelle action concrète entreprendre pour corriger un problème identifié.
- Le parcours produit est compréhensible sans connaissances comptables ou techniques préalables.
- Les explications fournies par le système sont traçables jusqu'à une règle de conformité identifiable.
- Le moteur de règles de conformité est maintenable et peut évoluer si la réglementation change.
- Le produit est perçu comme rassurant plutôt qu'anxiogène dans son usage.

## 14. Risques principaux

### Risques réglementaires
La réglementation relative à la facturation électronique peut évoluer (calendrier, obligations, formats). Le produit doit être conçu pour pouvoir s'adapter à ces évolutions sans refonte complète.

### Risques produit
Le besoin réel des utilisateurs peut différer des hypothèses formulées dans cette note (voir section 12). Une confrontation avec des utilisateurs réels sera nécessaire avant d'investir massivement dans le développement.

### Risques techniques
La validation, la génération ou la transmission de documents conformes à des formats réglementaires peut s'avérer plus complexe que prévu, en particulier si le produit interagit avec des formats ou circuits normés (à documenter dans `06-technical-architecture.md`).

### Risques sécurité
Le produit manipule des données financières et des informations d'entreprise potentiellement sensibles (factures, données clients). Une négligence en matière de sécurité aurait des conséquences importantes sur la confiance des utilisateurs.

### Risques commerciaux
Le marché de la facturation et de la comptabilité pour petites entreprises est déjà occupé par de nombreux acteurs établis. La différenciation du produit devra être clairement démontrée (voir `03-market-analysis.md`).

### Risques de positionnement
Sans vigilance, le produit pourrait progressivement dériver vers un simple logiciel de facturation générique, perdant ainsi sa différenciation initiale centrée sur la conformité et la pédagogie.

## 15. Dépendances

Le projet pourra dépendre, à des degrés divers et sans que cela constitue un engagement définitif, des éléments suivants :

- La réglementation française relative à la facturation électronique.
- Les normes et formats de facturation applicables (à documenter dans `02-regulatory-study.md`).
- Les plateformes agréées ou circuits de transmission réglementaires existants.
- D'éventuelles APIs externes (vérification d'entreprise, formats de facturation, etc.).
- Des services d'identification ou de vérification d'entreprise.
- Des services de stockage de documents.
- Des services d'envoi de notifications ou d'emails.
- D'éventuels fournisseurs de capacités d'intelligence artificielle, si un assistant conversationnel est développé.

Aucun fournisseur ou service spécifique n'est retenu à ce stade. Ces choix relèveront des documents techniques ultérieurs.

## 16. Principes de décision

Afin de guider les arbitrages futurs sans figer de choix prématurés, les principes suivants pourront servir de repères :

- En cas de doute entre complexité fonctionnelle et clarté pour l'utilisateur, privilégier la clarté.
- En cas de doute entre développement interne et service externe, privilégier le service externe pour toute brique non différenciante ou fortement réglementée, au moins pour le MVP.
- Toute règle de conformité intégrée au produit doit être traçable jusqu'à une source vérifiée dans `02-regulatory-study.md`.
- Aucune fonctionnalité ne doit être ajoutée au périmètre sans revalidation par rapport à la vision définie en section 4.

## 17. Relations avec les autres documents

Cette note constitue le socle du projet. Les documents suivants s'appuient sur elle de la manière suivante :

| Document | Rôle |
|---|---|
| `02-regulatory-study.md` | Vérifie et documente précisément les obligations réglementaires évoquées ici de façon non définitive. |
| `03-market-analysis.md` | Analyse le marché et les concurrents afin de confronter les hypothèses de la section 12. |
| `04-product-requirements.md` | Transforme la vision et le périmètre général en exigences fonctionnelles et non fonctionnelles précises. |
| `05-user-stories.md` | Traduit les exigences en parcours utilisateurs concrets. |
| `06-technical-architecture.md` | Conçoit l'architecture technique permettant de réaliser le produit. |
| `07-data-model.md` | Modélise les données métier du produit. |
| `08-api-specification.md` | Définit les contrats API du système. |
| `09-test-strategy.md` | Définit la stratégie de validation et de test. |
| `10-security-privacy.md` | Définit les exigences de sécurité et de confidentialité, en écho au principe de *privacy by design* (section 8). |
| `11-frontend-design-system.md` | Traduit les principes produit en expérience utilisateur et système visuel. |
| `12-roadmap.md` | Organise la réalisation du produit dans le temps, en cohérence avec la vision du MVP (section 10). |

## 18. Conclusion

Ce projet répond à un besoin potentiel clairement identifiable — l'accompagnement des petites entreprises françaises face à la réforme de la facturation électronique — mais dont l'ampleur réelle reste à confirmer. La note d'intention pose une direction claire : construire un assistant de conformité et de préparation, pédagogique et rassurant, plutôt qu'un énième logiciel de facturation.

Cette direction devra être vérifiée, affinée et parfois remise en question à mesure que les documents suivants apporteront des éléments factuels : réglementation vérifiée, analyse de marché, contraintes techniques. Cette note n'a pas vocation à figer des décisions, mais à donner un cap cohérent pour les documents à venir.

## Décisions à prendre dans les documents suivants

- **02-regulatory-study.md** : Quelles sont précisément les obligations applicables, à quelles entreprises, selon quel calendrier ? Quels formats et circuits de transmission sont concernés ? Quelles mentions obligatoires doivent figurer sur une facture ?
- **03-market-analysis.md** : Quels sont les acteurs déjà présents sur ce segment ? Existe-t-il réellement un espace de différenciation pour un produit centré sur la conformité plutôt que sur la facturation ? Qui sont les concurrents indirects (experts-comptables, outils généralistes) ?
- **04-product-requirements.md** : Quelles fonctionnalités précises composeront le MVP ? Quelles règles de conformité seront implémentées en premier ? Quelles exigences non fonctionnelles (performance, disponibilité, accessibilité) sont attendues ?
- **05-user-stories.md** : Quels sont les parcours utilisateurs prioritaires ? Comment se déroule concrètement une vérification de conformité du point de vue de l'utilisateur ?
- **06-technical-architecture.md** : Quelle architecture technique permettra de supporter le moteur de règles de conformité de façon évolutive ? Faut-il s'appuyer sur des services externes pour la transmission réglementaire, et lesquels ?
- **07-data-model.md** : Comment modéliser les entités entreprise, client, facture et règles de conformité de façon cohérente et évolutive ?
- **08-api-specification.md** : Quels contrats API sont nécessaires pour supporter les parcours définis dans les user stories ?
- **09-test-strategy.md** : Comment valider la fiabilité du moteur de règles de conformité, notamment en cas d'évolution réglementaire ?
- **10-security-privacy.md** : Quelles mesures de sécurité et de protection des données sont nécessaires, compte tenu de la sensibilité des données financières manipulées ? Quel cadre de conformité (RGPD notamment) s'applique au produit lui-même ?
- **11-frontend-design-system.md** : Comment traduire concrètement les principes de simplicité, de pédagogie et de réassurance dans l'interface utilisateur ?
- **12-roadmap.md** : Dans quel ordre développer les capacités identifiées en section 9, en cohérence avec la vision du MVP définie en section 10 ?
