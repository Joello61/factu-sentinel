# Étude de la réglementation - Réforme française de la facturation électronique

> **État des connaissances au 17 août 2026.** Ce document a été rédigé à partir de recherches effectuées à cette date, en priorisant les sources officielles françaises (economie.gouv.fr, impots.gouv.fr, service-public.gouv.fr, Légifrance, BOFiP). Les sources commerciales n'ont été utilisées qu'en complément, jamais comme preuve principale lorsqu'une source officielle existait. Toute information non confirmée par une source officielle est explicitement signalée.

## 1. Résumé exécutif

La réforme française de la facturation électronique entre entreprises entrera en vigueur le **1er septembre 2026**. Elle repose sur deux piliers :

- **L'e-invoicing** : obligation d'émettre, transmettre et recevoir des factures sous format électronique structuré, via une **plateforme agréée** (PA, anciennement appelée PDP), pour les opérations B2B domestiques entre entreprises assujetties à la TVA établies en France.
- **L'e-reporting** : obligation de transmettre à l'administration fiscale certaines données de transaction et de paiement pour les opérations qui ne relèvent pas de l'e-invoicing (ventes à des particuliers, opérations internationales).

Le calendrier est échelonné selon la taille de l'entreprise : toutes les entreprises doivent pouvoir **recevoir** des factures électroniques dès le 1er septembre 2026 ; les grandes entreprises et ETI doivent en outre **émettre** dès cette date ; les PME, TPE et micro-entreprises bénéficient d'un délai supplémentaire jusqu'au **1er septembre 2027** pour l'émission <cite index="16-1">et le 1er septembre 2027 les petites et micro-entreprises doivent être en capacité d'émettre électroniquement leurs factures et de transmettre leurs données de e-reporting</cite>.

Les micro-entrepreneurs et TPE sont pleinement concernés par cette réforme, y compris ceux qui bénéficient de la franchise en base de TVA <cite index="16-1">qui ne sont pas redevables de la TVA mais restent assujetties à la TVA et sont donc soumises à la facturation électronique, en réception et en émission</cite>. Le régime de sanctions a été révisé par la loi de finances pour 2026 (amendes portées de 15 € à 50 € par facture pour défaut d'émission électronique, et de 250 € à 500 € par transmission manquante en e-reporting), avec une phase de tolérance administrative annoncée pour le démarrage.

Ce document rassemble l'ensemble des éléments réglementaires vérifiés à date, distingue ce qui est confirmé de ce qui reste incertain, et propose une première traduction de ces obligations en exigences pour le futur produit.

## 2. Périmètre de l'étude

**Entrent dans le périmètre de cette étude :**

- La facturation électronique (e-invoicing) et la transmission de données à l'administration (e-reporting) ;
- Le calendrier de la réforme et son historique ;
- Les catégories d'entreprises concernées, y compris les régimes particuliers de TVA ;
- La notion de facture électronique et les formats reconnus ;
- Les mentions obligatoires, nouvelles et préexistantes ;
- L'architecture (plateformes, portail public, administration) ;
- La conservation et l'archivage des factures ;
- Les contrôles et sanctions ;
- Les cas spécifiques aux TPE et micro-entrepreneurs.

**N'entrent pas dans le périmètre de cette étude :**

- La facturation électronique à destination du secteur public (B2G), qui répond à un cadre distinct et préexistant via Chorus Pro, sauf mention ponctuelle pour clarifier une confusion possible ;
- L'ensemble du droit fiscal général (TVA, IS, etc.) au-delà de ce qui touche directement à la facturation ;
- Les choix d'architecture technique du produit (traités dans `06-technical-architecture.md`) ;
- L'analyse concurrentielle (traitée dans `03-market-analysis.md`) ;
- Les exigences fonctionnelles détaillées (traitées dans `04-product-requirements.md`).

## 3. Sources réglementaires

Les sources suivantes ont été consultées pour la rédaction de ce document. Le registre complet, avec URL et niveau d'utilisation, figure en section 25.

**Sources officielles principales :**

- economie.gouv.fr - page de référence « Tout savoir sur la facturation électronique pour les entreprises »
- impots.gouv.fr - rubrique professionnelle dédiée, fiches pratiques, glossaire, guide pratique
- entreprendre.service-public.gouv.fr - actualités réglementaires, notamment sur l'évolution des sanctions
- Légifrance - Code général des impôts (article 1737 notamment), texte de la loi de finances pour 2026
- BOFiP-Impôts - doctrine administrative sur les infractions et sanctions

**Sources complémentaires (utilisées uniquement pour illustrer ou compléter, jamais comme preuve principale) :**

- Cabinets d'expertise comptable et éditeurs de logiciels de facturation (Pennylane, Cegid, Sellsy, MEG, Dougs, Lido, etc.), utilisés pour recouper des informations pratiques (formats techniques, exemples de calendrier) lorsque leur contenu concordait entre plusieurs sources indépendantes et n'entrait pas en contradiction avec une source officielle.

## 4. Vue d'ensemble de la réforme

La réforme trouve son origine dans la loi de finances pour 2020, qui a posé le principe de la généralisation de la facturation électronique entre entreprises assujetties à la TVA <cite index="3-1">dispositif légal issu de la loi de finances 2020, confirmé par la loi du 10 août 2025</cite> - _ce dernier point (loi du 10 août 2025) provient d'une source commerciale et n'a pas pu être vérifié directement sur Légifrance ou economie.gouv.fr dans le cadre de cette étude ; à confirmer._

Le calendrier a connu plusieurs reports depuis l'annonce initiale (initialement envisagée à partir de juillet 2024). Un jalon important a eu lieu le 15 octobre 2024 : le gouvernement a confirmé le calendrier 2026-2027 mais a annoncé un recentrage du rôle du Portail Public de Facturation, désormais limité à un rôle d'annuaire et de concentrateur de données plutôt que d'émetteur/récepteur universel de factures (voir section 12).

**Objectif affiché de la réforme**, tel que présenté par l'administration : simplification de la gestion administrative des entreprises, gain de productivité, et amélioration de la détection de la fraude à la TVA grâce à une meilleure traçabilité des transactions <cite index="16-1">la réforme permet plus d'équité fiscale entre les entreprises grâce à un meilleur repérage des fraudes en matière de TVA au bénéfice des entreprises respectant les règles</cite>.

> **Niveau de confiance : Élevé** pour le calendrier et les principes généraux (sources officielles concordantes). **Faible** pour la référence à une « loi du 10 août 2025 » consolidant le dispositif, non vérifiée directement.

## 5. Calendrier

| Date               | Entreprises concernées                                  | Obligation                                        | Détail                                                                                              | Source           |
| ------------------ | ------------------------------------------------------- | ------------------------------------------------- | --------------------------------------------------------------------------------------------------- | ---------------- |
| 1er septembre 2026 | **Toutes les entreprises**, quelle que soit leur taille | Réception de factures électroniques (e-invoicing) | Doivent être en capacité technique de recevoir des factures électroniques via une plateforme agréée | economie.gouv.fr |
| 1er septembre 2026 | Grandes entreprises et ETI                              | Émission de factures électroniques + e-reporting  | Obligation complète d'émission et de transmission des données de transaction                        | economie.gouv.fr |
| 1er septembre 2027 | PME, TPE et micro-entreprises                           | Émission de factures électroniques + e-reporting  | Délai supplémentaire d'un an accordé aux petites structures                                         | economie.gouv.fr |

**Précisions importantes :**

- La taille de l'entreprise s'apprécie <cite index="13-1">sur la base du dernier exercice clos avant le 1er janvier 2025 ou à défaut du premier exercice clos à compter de cette date</cite>, selon un outil d'auto-diagnostic publié par impots.gouv.fr.
- L'obligation de réception s'applique à toutes les entreprises dès 2026, **y compris celles qui n'émettront électroniquement qu'à partir de 2027** : une TPE doit donc être techniquement prête à recevoir des factures électroniques (par exemple de la part d'un grand fournisseur d'énergie ou de télécommunications) dès septembre 2026, même si elle n'émet pas encore elle-même.
- Certaines sources commerciales évoquent la possibilité que la date d'entrée en vigueur pour les PME/TPE soit « retardée d'un trimestre par décret ». **Cette information n'a pas été retrouvée sur une source officielle et doit être considérée comme non confirmée.** _À confirmer._
- Un guide pratique de démarrage a été publié par l'administration à l'approche du 1er septembre 2026, et une **phase de tolérance et de bienveillance de l'administration** a été annoncée pour les entreprises rencontrant des difficultés au démarrage.

> **Niveau de confiance : Élevé** pour les dates et le principe d'échelonnement (source officielle directe). **Faible** pour l'hypothèse d'un report trimestriel par décret.

### 5 bis. Critères de taille d'entreprise (ajouté en Phase 3)

Seuils exacts nécessaires pour déterminer si une entreprise relève de « grande entreprise/ETI » (émission 2026) ou « PME/TPE/micro-entreprise » (émission 2027), absents de la version initiale de cette étude, qui ne documentait que les dates. Vérifiés à l'implémentation de la Phase 3 (`12-roadmap.md`).

<cite>Les entreprises sont classées en quatre catégories selon l'article 3 du décret n° 2008-1354 du 18 décembre 2008 (pris pour l'application de l'article 51 de la loi n° 2008-776 du 4 août 2008 de modernisation de l'économie), sur la base de l'effectif, du chiffre d'affaires et du total de bilan du dernier exercice clos.</cite>

| Catégorie                        | Effectif                       | Chiffre d'affaires | Total de bilan                                                                    |
| -------------------------------- | ------------------------------ | ------------------ | --------------------------------------------------------------------------------- |
| Micro-entreprise                 | < 10 personnes                 | ≤ 2 M€             | ≤ 2 M€                                                                            |
| PME (inclut la micro-entreprise) | < 250 personnes                | ≤ 50 M€            | ≤ 43 M€ (l'un des deux critères monétaires suffit, en plus du critère d'effectif) |
| ETI                              | Pas une PME, < 5 000 personnes | ≤ 1,5 Md€          | ≤ 2 Md€ (l'un des deux critères monétaires suffit)                                |
| Grande entreprise                | Au-delà des seuils ETI         | (pas de plafond)   | (pas de plafond)                                                                  |

<cite>La page officielle impots.gouv.fr consacrée au calendrier de la réforme ("À partir de quand suis-je concerné par la réforme de la facturation électronique ?") référence explicitement l'article 51 de la loi du 4 août 2008 de modernisation de l'économie pour fonder le critère de taille</cite>, c'est-à-dire la même base légale que la classification INSEE ci-dessus, confirmant que ce sont bien ces seuils qui s'appliquent au calendrier de la réforme, et non un barème propre à la facturation électronique.

**Simplification produit assumée** : pour la seule détermination de la date d'émission, la réforme regroupe grande entreprise et ETI sous une même date (1er septembre 2026), et PME/TPE/micro-entreprise sous une autre (1er septembre 2027, la micro-entreprise étant un sous-ensemble strict de la PME au sens de ce tableau). Le produit n'a donc jamais besoin de distinguer une ETI d'une grande entreprise, ni une micro-entreprise d'une PME plus grande : seul le seuil PME (< 250 personnes, et CA ≤ 50 M€ ou bilan ≤ 43 M€) est encodé comme donnée versionnée (`07-data-model.md`, sections 15-16). Voir `07-data-model.md` section 7 pour la traduction en modèle de données (`company_size_category`, classification à deux niveaux, explicitement distincte de la classification légale INSEE à quatre niveaux).

> **Niveau de confiance : Élevé** pour les seuils eux-mêmes (source INSEE directe, définition officielle et fiche métadonnées). **Élevé** pour le fait que ce même cadre légal (article 51 LME) s'applique au critère de taille de la réforme de facturation électronique (référencé explicitement par impots.gouv.fr), bien que la page dédiée de l'administration ne redétaille pas elle-même les seuils chiffrés.

## 6. Entreprises concernées

<cite index="16-1">Toutes les entreprises, indépendants et professions libérales assujettis à la taxe sur la valeur ajoutée (TVA) sont concernés par la facturation électronique, quels que soient leur taille, le chiffre d'affaires qu'elles réalisent, leur forme juridique ou leur régime d'imposition.</cite>

Ce périmètre couvre explicitement :

- les micro-entrepreneurs (auto-entrepreneurs) ;
- les indépendants et professions libérales ;
- les TPE, PME, ETI et grandes entreprises ;
- les entreprises bénéficiant de la franchise en base de TVA.

### Point d'attention majeur : franchise en base de TVA ≠ hors périmètre

Il s'agit d'un point souvent mal compris et qu'il est essentiel de bien intégrer dans le produit : <cite index="16-1">les entreprises qui bénéficient de la franchise en base de TVA (par exemple les micro-entrepreneurs) ne sont pas redevables de la TVA. Cependant, elles restent assujetties à la TVA et sont donc soumises à la facturation électronique, en réception et en émission.</cite>

Autrement dit :

- **Assujetti** = qui réalise une activité économique entrant dans le champ de la TVA (c'est le cas de la quasi-totalité des micro-entrepreneurs).
- **Redevable** = qui doit effectivement facturer, collecter et reverser la TVA.
- Un micro-entrepreneur en franchise en base est **assujetti mais non redevable** : cette nuance suffit à le placer dans le champ de la réforme.

<cite index="16-1">Même une entreprise qui n'émet pas de facture devra être en capacité de recevoir des factures électroniques de ses fournisseurs et pourrait avoir à transmettre des données complémentaires à l'administration.</cite>

### Chiffres cités par l'administration

La page de référence economie.gouv.fr indique, à des endroits différents, deux ordres de grandeur : <cite index="16-1">plus de 10 millions d'acteurs économiques sont concernés</cite> dans son bandeau d'introduction, et <cite index="16-1">la généralisation de la facturation électronique concerne plus de sept millions d'entreprises en France</cite> dans sa FAQ. Ces deux chiffres proviennent de la même source officielle sans que la différence soit explicitée (probablement une différence entre « acteurs économiques » au sens large et « entreprises » au sens strict). _Niveau de confiance : Moyen - chiffres officiels mais formulation à clarifier._

### Entreprises hors périmètre

Ne sont pas concernées par l'obligation d'e-invoicing : les entreprises non établies en France, les opérations avec des particuliers (qui relèvent de l'e-reporting et non de l'e-invoicing), et <cite index="16-1">les opérations bénéficiant d'une exonération de TVA</cite> qui ne sont pas soumises à la facturation électronique (par exemple certaines activités médicales, bancaires ou associatives relevant des articles 261 à 261 E du CGI).

## 7. Types d'opérations

| Situation                                                          | E-invoicing ?                                         | E-reporting ?                                  | Détail                                                                                                                     |
| ------------------------------------------------------------------ | ----------------------------------------------------- | ---------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| B2B domestique (entre entreprises assujetties, établies en France) | Oui                                                   | Non (les données transitent via l'e-invoicing) | Circuit principal de la réforme                                                                                            |
| B2C (vente à un particulier)                                       | Non                                                   | Oui                                            | Transmission de données de transaction ; la facture elle-même peut continuer d'être transmise au client par un canal libre |
| B2B international (client étranger, hors France)                   | Non                                                   | Oui                                            | Concerne notamment exportations, livraisons et acquisitions intracommunautaires                                            |
| Prestations de services taxées sur les encaissements               | Facture concernée par l'e-invoicing si B2B domestique | Oui, en complément (e-reporting de paiement)   | Transmission de données de paiement en sus des données de transaction                                                      |
| Opérations exonérées de TVA (art. 261 à 261 E CGI)                 | Non                                                   | Non                                            | Hors champ de la réforme dans son ensemble                                                                                 |

<cite index="58-1">Les entreprises qui vendent à la fois à des professionnels (B2B) et à des particuliers (B2C) devront gérer à la fois la facturation électronique pour leurs clients professionnels et le e-reporting pour leurs ventes aux particuliers.</cite> Ce cas de figure est très courant pour les TPE et micro-entrepreneurs (par exemple un artisan qui facture aussi bien des particuliers que des entreprises), et devra être traité avec attention dans le produit.

> **Niveau de confiance : Élevé** pour la distinction e-invoicing/e-reporting (confirmée par impots.gouv.fr). **Moyen** pour le détail des cas particuliers (sources commerciales concordantes mais non vérifiées sur source officielle primaire pour chaque cas).

## 8. Définition de la facture électronique

C'est l'un des points les plus structurants de la réforme pour le produit. <cite index="16-1">Une facture électronique doit respecter une forme électronique normée, comporter les mentions obligatoires d'une facture sous un format donné dans un champ dédié, et être transmise au client par l'intermédiaire d'une plateforme agréée, partenaire de l'administration.</cite>

Conséquence directe, formulée explicitement par l'administration : <cite index="16-1">la facturation électronique, comme on l'entend aujourd'hui, sous forme de facture « papier » scannée, de PDF ordinaire ou de document envoyé par mail, ne sera plus conforme à la réglementation.</cite>

Cette distinction est **essentielle pour le positionnement du produit** (voir `01-intent-note.md`) : un grand nombre de TPE pensent aujourd'hui être « déjà en facturation électronique » parce qu'elles envoient leurs factures par email au format PDF. Ce n'est pas le cas au sens de la réforme, car :

- le PDF simple n'est pas une donnée structurée exploitable automatiquement ;
- il n'est pas transmis via une plateforme agréée ;
- il ne garantit pas nativement les propriétés d'authenticité, d'intégrité et de lisibilité attendues du circuit réglementaire.

Une facture papier scannée ou un PDF envoyé par email restent des pratiques valables aujourd'hui, mais **cesseront d'être conformes** à la date d'obligation d'émission applicable à l'entreprise concernée.

> **Niveau de confiance : Élevé** - formulation explicite et sans ambiguïté d'une source officielle (economie.gouv.fr, y compris dans sa vidéo pédagogique).

## 9. Formats de facturation

Le socle européen sur lequel s'appuient les formats reconnus est la norme **EN 16931**. Trois formats structurés sont cités de façon concordante par de nombreuses sources professionnelles comme étant les formats reconnus dans le cadre de la réforme française :

| Format                                | Nature                                         | Structure                                                            | Origine                                                                     | Cas d'usage typique                                                                            |
| ------------------------------------- | ---------------------------------------------- | -------------------------------------------------------------------- | --------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| **Factur-X**                          | Hybride                                        | PDF/A-3 lisible par un humain, avec un fichier XML structuré intégré | Co-développé par la FNFE-MPE (France) et le FeRD (Allemagne)                | Particulièrement adapté aux TPE/PME B2B, car lisible à la fois visuellement et par une machine |
| **UBL** (Universal Business Language) | XML structuré pur, sans mise en forme visuelle | Standard porté par OASIS                                             | Interopérabilité avec Peppol et les échanges internationaux/EDI             |
| **CII** (Cross Industry Invoice)      | XML structuré pur                              | Porté par l'ONU (UN/CEFACT)                                          | Filières industrielles, logistiques, chaînes d'approvisionnement multi-pays |

**Point important pour la conception du produit** : les plateformes agréées ont l'obligation d'assurer l'interopérabilité et la conversion entre ces formats. Une entreprise n'a donc pas besoin de gérer les trois formats elle-même ; le choix d'un format d'émission (le plus souvent Factur-X pour une TPE, du fait de sa double lisibilité) est converti si nécessaire par la plateforme agréée du destinataire.

> **Niveau de confiance : Moyen.** Cette section repose principalement sur des sources professionnelles concordantes (éditeurs de logiciels, cabinets comptables). Aucune page officielle equivalente en niveau de détail technique (spécification exacte des formats) n'a pu être directement consultée dans le cadre de cette étude. **Recommandation : vérifier ces éléments auprès de la documentation technique DGFiP/AIFE avant toute décision d'architecture.** _À confirmer dans les spécifications techniques officielles._

## 10. Mentions obligatoires

<cite index="16-1">La réforme de la facturation électronique modifie le processus de transmission de la facture mais les modalités de facturation restent identiques.</cite> Autrement dit, l'essentiel des mentions obligatoires actuelles d'une facture française reste inchangé ; la réforme en ajoute quatre nouvelles.

| Donnée                                                                                                                                                                                                | Obligatoire ?                                              | À partir de            | Conditions                                           | Source                                                                            |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- | ---------------------- | ---------------------------------------------------- | --------------------------------------------------------------------------------- |
| Numéro SIREN du client                                                                                                                                                                                | Nouvelle mention obligatoire                               | 1er septembre 2026     | Toutes factures B2B concernées                       | economie.gouv.fr                                                                  |
| Catégorie de l'opération (vente de bien / prestation de service / mixte)                                                                                                                              | Nouvelle mention obligatoire                               | 1er septembre 2026     | Toutes factures B2B concernées                       | economie.gouv.fr                                                                  |
| Mention de l'option de paiement de la TVA sur les débits                                                                                                                                              | Nouvelle mention obligatoire                               | 1er septembre 2026     | Si l'entreprise a opté pour ce régime                | economie.gouv.fr                                                                  |
| Adresse complète de livraison du bien                                                                                                                                                                 | Nouvelle mention obligatoire                               | 1er septembre 2026     | Uniquement si différente de l'adresse de facturation | economie.gouv.fr                                                                  |
| Mentions obligatoires préexistantes (identité des parties, SIREN/SIRET du fournisseur, numéro et date de facture, désignation des biens/services, prix unitaire HT, taux de TVA, montant total, etc.) | Déjà obligatoires avant la réforme                         | Antérieur à la réforme | Inchangées par la réforme elle-même                  | economie.gouv.fr (renvoi vers la fiche générale sur les mentions obligatoires)    |
| Mention « TVA non applicable, art. 293 B du CGI »                                                                                                                                                     | Déjà obligatoire pour les entreprises en franchise en base | Antérieur à la réforme | Applicable aux micro-entrepreneurs en franchise      | Sources concordantes ; **à vérifier sur BOFiP pour la formulation exacte exigée** |

> **Niveau de confiance : Élevé** pour les quatre nouvelles mentions (source officielle explicite). **Moyen** pour le détail exact du libellé de la mention de franchise en base, qui n'a pas été vérifié directement sur une source primaire dans le cadre de cette étude.

## 11. Données structurées et données transmises

Il convient de distinguer plusieurs niveaux de données, qui devront être modélisés séparément dans le futur produit (voir aussi `07-data-model.md`) :

1. **Données de la facture elle-même** - les mentions obligatoires listées en section 10, présentes dans le document transmis au client.
2. **Données structurées transmises via l'e-invoicing** - extraites automatiquement par la plateforme agréée à partir de la facture électronique, à destination de l'administration (données fiscales : montants, TVA, identifiants).
3. **Données de transaction (e-reporting)** - pour les opérations hors du champ de l'e-invoicing (B2C, international). D'après une source professionnelle détaillant le contenu réglementaire de ces flux, ces données incluent notamment <cite index="57-1">le numéro d'identification du fournisseur du bien ou service, la période au titre de laquelle la transmission est effectuée (ou la date de la facture pour les opérations donnant lieu à facture), la mention « Option pour le paiement de la taxe d'après les débits » s'il y a lieu, la catégorie de transaction (livraison de biens, prestation de services...), le montant total hors taxe et le montant de TVA correspondante par taux d'imposition, ainsi que le montant total de TVA due en France</cite>.
4. **Données de paiement (e-reporting de paiement)** - spécifiques aux prestations de services dont la TVA est exigible à l'encaissement. Selon une fiche pratique de l'administration fiscale, ces données comprennent <cite index="55-1">le numéro de SIREN du fournisseur de la prestation de services, la date d'encaissement, le montant encaissé, et la période au titre de laquelle la transmission est effectuée (déterminée selon le régime de TVA de l'entreprise)</cite>. Cette même fiche précise que <cite index="55-1">le client n'a pas à transmettre à l'administration d'informations sur le paiement effectué à son fournisseur</cite>.

**Cas particulier important pour la cible TPE/indépendants** : de nombreux indépendants et prestataires de services relèvent d'un régime de TVA sur les encaissements (exigibilité au moment du paiement plutôt qu'à l'émission de la facture). Ce point aura un impact direct sur le fonctionnement du moteur de conformité, qui devra distinguer les factures nécessitant un e-reporting de paiement complémentaire.

> **Niveau de confiance : Élevé** - cette section s'appuie directement sur des fiches pratiques publiées par l'administration fiscale (impots.gouv.fr).

## 12. Architecture et acteurs

### Schéma général (B2B domestique - e-invoicing)

```text
Entreprise émettrice
        ↓
Plateforme agréée (PA) de l'émetteur
        ↓  (transmission de la facture)
Plateforme agréée (PA) du destinataire
        ↓
Entreprise cliente
```

En parallèle :

```text
Plateforme agréée (émetteur et/ou destinataire)
        ↓  (données extraites de la facture)
Portail Public de Facturation - rôle de concentrateur
        ↓
Administration fiscale (DGFiP)
```

### Rôle du Portail Public de Facturation (PPF)

Ce point a fait l'objet d'un changement important qu'il est essentiel de bien comprendre :

**Ancienne conception (avant octobre 2024)** → le PPF devait initialement pouvoir jouer le rôle d'une plateforme de facturation à part entière, gratuite, permettant à toute entreprise d'émettre et recevoir ses factures directement auprès de l'État.

**Nouvelle conception (depuis le communiqué du 15 octobre 2024)** → <cite index="8-1">le Gouvernement confirme le calendrier de la réforme mais limite le périmètre du portail public de facturation. Le ministère chargé du Budget et des Comptes publics a confirmé le calendrier de mise en place de l'obligation de facturation électronique entre entreprises, en privilégiant la construction d'un annuaire des destinataires, indispensable aux échanges entre les plateformes, et d'un concentrateur des données permettant leur transmission à l'administration fiscale.</cite>

Autrement dit, **depuis cette décision, le PPF n'émet ni ne reçoit plus de factures**. Son rôle se limite à deux fonctions :

1. **Annuaire** - recenser les entreprises assujetties à la TVA et leurs adresses de facturation électronique, afin de permettre aux plateformes agréées de s'orienter entre elles ;
2. **Concentrateur de données** - collecter les données fiscales transmises par les plateformes agréées et les mettre à disposition de la DGFiP.

Ce changement de rôle est corroboré de façon concordante par de nombreuses sources professionnelles postérieures à octobre 2024, mais la source la plus directement citable reste le communiqué ministériel du 15 octobre 2024 relayé par des cabinets d'expertise.

**Conséquence pratique majeure** : toute entreprise, y compris une TPE, doit obligatoirement passer par une **plateforme agréée (PA)** - elle n'a pas la possibilité d'utiliser directement le PPF comme circuit de facturation. Ce point doit être clairement expliqué dans le produit pour éviter toute confusion chez les utilisateurs qui auraient entendu parler du PPF dans sa conception initiale.

### Cas particulier : Chorus Pro (B2G)

Chorus Pro est le circuit préexistant, obligatoire depuis 2020, pour la facturation à destination du secteur public (marchés publics). Il **reste en vigueur** et **n'est pas remplacé** par la réforme B2B. Ce circuit est hors périmètre principal de cette étude (voir section 2) mais peut concerner certains utilisateurs cibles (TPE travaillant avec des collectivités, par exemple).

> **Niveau de confiance : Élevé** pour le principe général du recentrage du PPF (largement corroboré). **Moyen** pour les détails précis du calendrier de mise en œuvre technique du volet annuaire/concentrateur (source primaire du communiqué non directement consultée dans son texte intégral).

## 13. Plateformes agréées

<cite index="16-1">Une plateforme agréée est un prestataire proposant des services de dématérialisation de factures et ayant fait l'objet d'une procédure d'immatriculation par l'administration. C'est l'intermédiaire obligatoire entre entreprises pour l'envoi et la réception des factures au format électronique et la transmission des données de factures et de transactions à l'administration fiscale. Seule une plateforme agréée est habilitée à assurer toutes les fonctionnalités prévues par la réforme en matière de facturation électronique et de e-reporting.</cite>

Pour obtenir l'immatriculation, un opérateur doit <cite index="46-1">déposer un dossier de candidature démontrant sa conformité fiscale, la sécurité de ses infrastructures et de ses données, ainsi que son interopérabilité technique avec le Portail Public de Facturation et avec les autres plateformes. L'immatriculation définitive n'est accordée qu'après réussite des tests d'interopérabilité en conditions réelles.</cite>

Une notion voisine mais distincte est celle de **solution compatible** : <cite index="16-1">une solution informatique (logiciel comptable, métier, de facturation ou de caisse) qui peut proposer une large gamme de fonctionnalités pour aider les entreprises à se mettre en conformité, mais qui n'est pas immatriculée par l'administration et doit donc obligatoirement recourir au service d'une plateforme agréée pour transmettre les factures et informations attendues à l'administration fiscale.</cite>

> **Positionnement à retenir pour notre produit** : notre assistant de conformité n'a pas vocation, à ce stade, à être une plateforme agréée. Il pourrait, le cas échéant et selon des décisions à prendre ultérieurement, se positionner comme une « solution compatible » qui s'appuierait sur une ou plusieurs plateformes agréées existantes. Cette question relève de `04-product-requirements.md` et `06-technical-architecture.md`, et n'est pas tranchée par le présent document.

La liste des plateformes agréées est publiée et régulièrement mise à jour par l'administration fiscale, sous plusieurs formats (ODS, XLSX, PDF), distinguant les opérateurs pleinement immatriculés de ceux en attente de validation de leurs tests d'interopérabilité.

> **Niveau de confiance : Élevé** - informations directement issues d'impots.gouv.fr.

## 14. E-invoicing

**Définition** : <cite index="16-1">le terme de e-invoicing fait référence à l'émission et à la réception de factures via une plateforme de dématérialisation. Au sens large, il traduit le projet de la facturation électronique dans son ensemble.</cite>

**Opérations concernées** : achats et ventes de biens et/ou prestations de services entre entreprises établies en France et assujetties à la TVA (voir section 7).

**Acteurs** : entreprise émettrice, plateforme agréée de l'émetteur, plateforme agréée du destinataire, entreprise destinataire, administration fiscale (en tant que destinataire des données extraites, via le concentrateur).

**Calendrier** : voir section 5.

**Ce que l'e-invoicing change concrètement** : centralisation de la réception des factures fournisseurs sur une plateforme unique, horodatage et suivi de statut de chaque facture, standardisation des données facilitant leur exploitation comptable.

> **Niveau de confiance : Élevé.**

## 15. E-reporting

**Définition** : <cite index="16-1">le e-reporting consiste à transmettre électroniquement des données de transaction et de paiement à l'administration fiscale. Toutes les entreprises assujetties à la TVA et établies en France sont concernées lorsqu'elles réalisent des opérations avec des clients particuliers, certaines associations ou avec des opérateurs étrangers.</cite>

**Différence avec l'e-invoicing** : l'e-invoicing transmet une facture complète entre deux entreprises via des plateformes ; l'e-reporting transmet uniquement des données de synthèse à l'administration, pour des opérations où il n'y a pas nécessairement de facture électronique B2B (vente à un particulier, opération internationale).

**Deux composantes de l'e-reporting** :

1. **E-reporting de transaction** - porte sur l'opération elle-même (montants, TVA, catégorie).
2. **E-reporting de paiement** - porte sur l'encaissement effectif, pour les prestations de services dont la TVA est exigible à l'encaissement.

**Fréquence de transmission** : dépend du régime d'imposition de l'entreprise (par exemple, transmission par périodes de 10 jours pour le régime réel normal, selon une source professionnelle qui n'a pas pu être recoupée directement avec une fiche officielle dans le cadre de cette étude). _À confirmer précisément par régime de TVA (réel normal, réel simplifié, franchise) dans une future itération de cette étude ou directement auprès de la documentation DGFiP._

**Cas particulier des opérations exonérées** : ces opérations sont hors du champ de l'e-reporting comme de l'e-invoicing (voir section 6).

> **Niveau de confiance : Élevé** pour la définition et le périmètre général (source officielle). **Moyen** pour le détail des fréquences de transmission par régime de TVA (source professionnelle non recoupée avec une source primaire dans cette étude).

## 16. Conservation et archivage

Les factures, qu'elles soient électroniques ou non, sont soumises à des obligations de conservation qui préexistent à la réforme et ne sont pas fondamentalement modifiées par elle, mais qui prennent une importance renouvelée avec la généralisation du format électronique structuré.

**Ce qui semble globalement établi** (recoupé par plusieurs sources professionnelles concordantes, sans confirmation directe sur une source primaire de type BOFiP dans le cadre de cette étude) :

- Les factures doivent être conservées pendant une durée de l'ordre de **6 ans** au titre des obligations fiscales et de l'ordre de **10 ans** au titre du droit comptable (documents comptables et pièces justificatives).
- Une facture reçue ou émise sous forme électronique devrait, selon plusieurs sources, être conservée **dans son format d'origine** (par exemple le fichier XML sous-jacent d'un Factur-X) pendant toute la durée de conservation applicable, une simple impression ou conversion ne remplaçant pas le fichier natif.
- Le support de conservation devrait se trouver en France ou dans un autre État membre de l'Union européenne, ou dans un pays tiers offrant des garanties équivalentes d'accès aux données.

**Incohérences relevées entre les sources consultées** : certaines sources indiquent une durée de conservation du format électronique d'origine de 3 ans avant possibilité de conversion papier, tandis que d'autres évoquent 6 ans sans mention de cette possibilité de bascule. Cette divergence n'a pas pu être tranchée avec les sources consultées dans le cadre de cette étude.

> **Niveau de confiance : Faible à moyen.** Cette section repose exclusivement sur des sources commerciales, malgré des recherches ciblées. **Action requise avant toute décision produit : vérifier les durées exactes et les conditions de conservation du format d'origine directement sur BOFiP (rubrique conservation des documents) avant de figer une exigence produit sur ce sujet.** _À confirmer._

## 17. Contrôles et sanctions

Le régime de sanctions applicable a été **modifié par la loi de finances pour 2026** (LOI n° 2026-103 du 19 février 2026, article 123), avec effet à compter de sa publication. Il convient de bien distinguer les sanctions **spécifiques à la réforme de la facturation électronique** des sanctions **générales préexistantes** relatives aux factures.

### 17.1. Sanctions spécifiques à la réforme (modifiées en 2026)

| Manquement                                                                                  | Ancien montant                   | Nouveau montant (depuis la loi de finances 2026)                                                                                                                                                 | Plafond annuel                                      | Source                              |
| ------------------------------------------------------------------------------------------- | -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------- | ----------------------------------- |
| Non-respect de l'obligation d'émettre une facture électronique                              | 15 € par facture                 | **50 € par facture**                                                                                                                                                                             | 15 000 € par entreprise (SIREN) et par année civile | service-public.gouv.fr / Légifrance |
| Non-transmission des données de transaction/paiement (e-reporting)                          | 250 € par transmission manquante | **500 € par transmission manquante**                                                                                                                                                             | 15 000 € par an                                     | service-public.gouv.fr / Légifrance |
| Omission ou manquement d'une plateforme agréée à ses obligations de transmission de données | -                                | 50 € par facture à la charge de la plateforme                                                                                                                                                    | 45 000 € par an                                     | Légifrance (art. 1737 IV CGI)       |
| Absence de recours à une plateforme agréée pour la réception (nouveauté)                    | -                                | Mise en demeure de se conformer sous 3 mois, puis amende de **500 €**, puis nouvelle mise en demeure de 3 mois, puis **1 000 € tous les trimestres** tant que la situation n'est pas régularisée | -                                                   | service-public.gouv.fr              |

<cite index="39-1">Concernant ces sanctions, la loi de finances précise qu'elles ne sont pas applicables « en cas de première infraction commise au cours de l'année civile en cours et des trois années précédentes si l'infraction a été réparée spontanément ou dans les trente jours suivant une première demande de l'administration ».</cite>

Le texte codifié de l'article 1737 du CGI, tel que consulté sur Légifrance, confirme le mécanisme : <cite index="35-1">le non-respect par l'assujetti de l'obligation d'émission d'une facture sous une forme électronique dans les conditions prévues à l'article 289 bis donne lieu à l'application d'une amende de 50 € par facture, sans que le total des amendes appliquées au titre d'une même année civile puisse être supérieur à 15 000 €. Toute omission ou tout manquement par une plateforme agréée aux obligations de transmission de données mentionnées à l'article 289 E donne lieu à une amende de 50 € par facture mise à la charge de cette plateforme, sans que le total des amendes appliquées au titre d'une même année civile puisse être supérieur à 45 000 €.</cite> Le même texte précise également : <cite index="35-1">lorsque l'administration constate une omission ou un manquement par l'assujetti à l'obligation de recourir à une plateforme agréée pour la réception de factures électroniques prévue au I de l'article 289 bis du présent code, elle le met en demeure de s'y conformer dans un délai de trois mois.</cite>

### 17.2. Sanctions générales préexistantes (article 1737 CGI, non spécifiques à la réforme)

Ces sanctions existaient avant la réforme et continuent de s'appliquer indépendamment d'elle :

| Manquement                                                                                           | Montant                                                                                                                                               | Source                       |
| ---------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| Omission ou inexactitude dans une facture (mention manquante ou erronée)                             | 15 € par omission/inexactitude, plafonné au quart du montant de la facture                                                                            | Légifrance, art. 1737-II CGI |
| Dissimulation de l'identité ou de l'adresse d'un fournisseur/client, usage d'un prête-nom            | 50 % des sommes versées ou reçues                                                                                                                     | BOFiP, art. 1737-I-1 CGI     |
| Délivrance d'une facture ne correspondant pas à une livraison ou prestation réelle (facture fictive) | 50 % du montant de la facture                                                                                                                         | BOFiP, art. 1737-I-2 CGI     |
| Défaut de délivrance d'une facture                                                                   | 50 % du montant de la transaction, réductible à 5 % si l'opération a été régulièrement comptabilisée et justifiée sous 30 jours d'une mise en demeure | BOFiP, art. 1737-I-3 CGI     |

### 17.3. Approche annoncée pour le démarrage de la réforme

L'administration a annoncé une **phase de tolérance** lors du lancement de la réforme, dans un communiqué relayé sur economie.gouv.fr, évoquant <cite index="16-1">une approche de bienveillance et de tolérance de l'administration à l'égard des entreprises qui rencontreraient des difficultés au 1er septembre</cite>. Les modalités précises et la durée de cette tolérance n'ont pas été détaillées dans les sources consultées. _À confirmer - surveiller les communications ultérieures de la DGFiP._

> **Niveau de confiance : Élevé** pour les montants et mécanismes de sanctions (sources officielles directes : service-public.gouv.fr et Légifrance, avec référence précise au texte de loi). **Moyen** pour la portée exacte et la durée de la phase de tolérance administrative annoncée.

## 18. Cas spécifiques aux TPE et micro-entrepreneurs

Cette section répond directement à la question : _« Je suis micro-entrepreneur en France : qu'est-ce qui va concrètement changer pour moi ? »_

### Scénario A - Micro-entrepreneur en franchise en base de TVA, clientèle 100 % B2B française

- **Concerné par l'e-invoicing** en réception dès le 1er septembre 2026, et en émission à partir du 1er septembre 2027 (voir section 6 - assujetti même sans être redevable).
- Doit choisir une plateforme agréée pour recevoir (dès 2026) puis émettre (dès 2027) ses factures.
- Doit continuer à faire figurer la mention de franchise en base sur ses factures (_libellé exact à vérifier sur BOFiP_).
- N'est en principe pas concerné par l'e-reporting pour cette activité B2B, puisque ces opérations relèvent de l'e-invoicing.

### Scénario B - Micro-entrepreneur assujetti et redevable de la TVA (a dépassé les seuils de franchise ou y a renoncé), clientèle B2B française

- Mêmes obligations d'e-invoicing que le scénario A pour l'émission et la réception.
- Doit en outre gérer la TVA facturée, collectée et déclarée normalement.
- Si son activité relève de prestations de services taxées à l'encaissement, doit gérer l'e-reporting de paiement en complément pour les opérations concernées (voir section 15).

### Scénario C - TPE avec clientèle mixte (professionnels et particuliers)

- Gère **simultanément** l'e-invoicing pour ses clients professionnels et l'e-reporting pour ses ventes aux particuliers.
- Cas très fréquent (artisans, commerces de proximité travaillant aussi avec des professionnels) et probablement l'un des cas les plus complexes à outiller pour notre produit, car il nécessite de savoir distinguer automatiquement le régime applicable selon la nature du client.

### Scénario D - TPE principalement B2C

- Essentiellement concernée par l'e-reporting (transmission de données de transaction et, le cas échéant, de paiement) plutôt que par l'e-invoicing, sauf pour ses éventuelles opérations avec des professionnels.
- Doit néanmoins être en capacité de **recevoir** des factures électroniques de ses propres fournisseurs professionnels dès 2026.

### Scénario E - Entreprise avec opérations internationales (export, UE ou hors UE)

- Les opérations avec des clients étrangers relèvent de l'e-reporting et non de l'e-invoicing, qui est réservé aux opérations domestiques France-France.
- Doit transmettre les données de transaction correspondantes à sa plateforme agréée.

> **Niveau de confiance : Élevé** pour la structure générale des scénarios (découle directement des règles décrites en sections 6, 7 et 15). **Moyen** pour certains détails fins (libellés exacts de mentions, articulation précise avec les régimes déclaratifs de TVA), qui devront être vérifiés au cas par cas lors de la conception du moteur de règles.

## 19. Impacts sur le produit

Cette section traduit les obligations réglementaires identifiées en contraintes que le produit devra probablement prendre en compte, sans figer d'exigence fonctionnelle définitive (celles-ci relèvent de `04-product-requirements.md`).

| Référence réglementaire | Obligation                                                      | Impact utilisateur                                                               | Impact produit                                                                                                                            | Impact technique                                                                                             |
| ----------------------- | --------------------------------------------------------------- | -------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Section 6               | Assujettissement même en franchise en base                      | L'utilisateur doit comprendre qu'il est concerné même s'il ne facture pas de TVA | Le produit doit clarifier ce point dès l'onboarding, pour éviter le malentendu le plus fréquent chez les micro-entrepreneurs              | Modéliser un statut « assujetti » distinct du statut « redevable »                                           |
| Section 5               | Calendrier différencié par taille d'entreprise                  | L'utilisateur doit connaître précisément sa date d'obligation d'émission         | Le produit doit pouvoir déterminer la taille de l'entreprise selon les critères officiels                                                 | Modéliser la notion de taille d'entreprise et sa date d'appréciation                                         |
| Section 8               | Un PDF/email n'est pas une facture électronique conforme        | L'utilisateur doit comprendre pourquoi sa pratique actuelle ne suffira plus      | Le produit doit pouvoir détecter et expliquer qu'un document donné n'est pas conforme au sens de la réforme                               | Distinguer document informel (PDF/papier) de facture structurée conforme                                     |
| Section 10              | Quatre nouvelles mentions obligatoires                          | L'utilisateur doit savoir quelles mentions ajouter et dans quels cas             | Le produit doit vérifier la présence de chaque mention selon les conditions applicables                                                   | Modéliser chaque mention comme une règle avec ses conditions d'applicabilité                                 |
| Section 7, 15, 18       | Distinction e-invoicing / e-reporting selon la nature du client | L'utilisateur doit savoir quel régime s'applique à chacune de ses opérations     | Le produit doit pouvoir qualifier automatiquement une opération selon le statut du client (professionnel français, particulier, étranger) | Nécessite une donnée fiable sur le statut du client (SIREN, localisation)                                    |
| Section 13              | Plateforme agréée obligatoire pour tout circuit réglementaire   | L'utilisateur doit choisir une plateforme agréée                                 | Le produit peut orienter l'utilisateur, sans se substituer à une plateforme agréée                                                        | Dépendance externe potentielle vers une ou plusieurs plateformes agréées (décision à prendre ultérieurement) |
| Section 17              | Sanctions financières en cas de non-conformité                  | L'utilisateur veut éviter les amendes                                            | Le produit doit permettre d'anticiper et de corriger les non-conformités avant qu'elles ne deviennent sanctionnables                      | Traçabilité des vérifications effectuées et de leur date                                                     |

> Cette table n'est pas exhaustive et sera enrichie à mesure que l'étude réglementaire progressera et que le PRD sera rédigé.

## 20. Impacts sur le futur moteur de conformité

Le futur moteur de règles de conformité devra vraisemblablement pouvoir gérer les catégories de règles suivantes, identifiées à partir de la présente étude :

- **Règles de qualification d'opération** - déterminer si une opération donnée relève de l'e-invoicing, de l'e-reporting de transaction, de l'e-reporting de paiement, ou d'aucun des deux (opération exonérée). Exemple conceptuel :

```text
RULE-QUALIFICATION-001
Condition :
    Le client est une entreprise assujettie à la TVA
    ET le client est établi en France
    ET l'opération n'est pas exonérée de TVA
Résultat attendu :
    L'opération relève de l'e-invoicing.
```

- **Règles de mentions obligatoires** - vérifier la présence et la validité de chaque mention selon le contexte de la facture (nature de l'opération, statut TVA de l'émetteur, différence entre adresse de livraison et de facturation, etc.).
- **Règles de format** - vérifier qu'un document soumis correspond à un format structuré reconnu (par opposition à un PDF simple ou une image), sans que cette étude ne tranche à ce stade la manière technique de le faire.
- **Règles de statut de l'émetteur** - déterminer si l'entreprise est déjà soumise à l'obligation d'émission (selon sa taille et la date courante) ou seulement à l'obligation de réception.
- **Règles de plafond et de cumul de sanctions** - utiles non pas pour appliquer une sanction (qui reste de la compétence de l'administration), mais pour aider l'utilisateur à estimer un risque financier en cas de non-conformité persistante.

**Données nécessaires pour évaluer ces règles** : statut d'assujettissement et de redevabilité TVA de l'entreprise, taille de l'entreprise (et date de référence retenue), régime de TVA (débits/encaissements), statut du client (professionnel français identifié par SIREN, particulier, étranger), nature de l'opération (bien/service/mixte), date de l'opération, contenu de la facture.

**Dépendances et exceptions** à anticiper : opérations exonérées de TVA, opérations mixtes (biens et services sur une même facture), clients à statut incertain, changements de régime de TVA en cours d'année (franchissement de seuil).

> Cette section reste volontairement conceptuelle. La conception technique du moteur de règles relève de `06-technical-architecture.md`.

## 21. Versionnement des règles

Dans la mesure où la réforme a déjà connu plusieurs évolutions significatives en peu de temps (report du calendrier, recentrage du rôle du PPF en octobre 2024, révision des sanctions par la loi de finances pour 2026), il paraît nécessaire que le produit puisse un jour répondre à une question du type : _« Cette facture était-elle conforme selon les règles applicables à sa date d'émission ? »_

Cela suppose conceptuellement :

- une **date d'entrée en vigueur** et, le cas échéant, une **date de fin de validité** pour chaque règle du moteur de conformité ;
- un mécanisme de **versionnement** des règles, permettant de conserver les anciennes versions plutôt que de les écraser ;
- une **traçabilité** permettant de savoir, pour une vérification de conformité donnée, quelle version des règles a été appliquée ;
- la capacité à **historiser** les évolutions constatées dans cette étude elle-même (ce document devra probablement être mis à jour au fil du temps, avec un historique de ses propres révisions).

Ce sujet reste volontairement conceptuel à ce stade ; sa traduction technique (modèle de données, architecture) relève de `06-technical-architecture.md` et `07-data-model.md`.

## 22. Traçabilité et explicabilité

Le produit devra être capable de répondre à la question : _« Pourquoi cette facture est-elle considérée comme non conforme ? »_ Cette exigence, déjà posée en principe dans `01-intent-note.md`, trouve un ancrage réglementaire dans la nature même des vérifications attendues : mentions obligatoires précises, conditions d'applicabilité contextuelles, régime de sanctions gradué et progressif (mise en demeure avant amende, tolérance pour la première infraction).

Pour être utile et digne de confiance, chaque résultat de vérification de conformité devrait pouvoir être retracé jusqu'à :

- la **règle** appliquée ;
- la **source réglementaire** sur laquelle cette règle s'appuie (idéalement avec un lien vers la présente étude ou vers la source officielle) ;
- la **version** de la règle utilisée au moment de la vérification ;
- la **date** à laquelle la vérification a été effectuée.

Cette exigence d'explicabilité est cohérente avec le principe produit « Explicabilité des contrôles » énoncé dans `01-intent-note.md`.

## 23. Questions réglementaires - état après vérification complémentaire (2026)

> Les points ci-dessous ont été revérifiés par l'équipe projet courant 2026, postérieurement à la rédaction initiale de cette étude. Les décisions retenues sont donc des **décisions produit s'appuyant sur cette vérification complémentaire**, pas un nouvel audit réglementaire indépendant mené par ce document. Chaque point reste sujet à revalidation si une source officielle contredit la vérification de l'équipe.

| Question initiale                                                                          | Décision retenue                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | Statut                                                                                                                   |
| ------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Le calendrier d'émission PME/TPE (1er septembre 2027) peut-il être décalé d'un trimestre ? | **Ne pas construire le produit sur l'hypothèse d'un report.** Le calendrier de référence retenu reste : 1er septembre 2026 (réception pour toutes les entreprises, émission et e-reporting pour GE/ETI) et 1er septembre 2027 (émission et e-reporting pour PME/TPE/micro-entreprises). Le mécanisme de versionnement des règles (section 21) permet d'ajuster ces dates si la réglementation évolue effectivement.                                                                                                                                                                                | Résolu - décision produit                                                                                                |
| Durée exacte de conservation du format électronique d'origine ?                            | **10 ans** pour la facture originale (pièce comptable), à distinguer explicitement des données techniques dérivées du traitement (traçabilité, données temporaires), dont la durée de conservation dépend de leur propre finalité et non de la durée légale de la facture elle-même. Voir `07-data-model.md` (section 36, mis à jour) et `10-security-privacy.md` (section 38, mis à jour).                                                                                                                                                                                                        | Résolu - décision produit, durée légale de 10 ans retenue pour la pièce comptable                                        |
| Libellé exact de la mention de franchise en base de TVA ?                                  | **Non hardcodé.** La formulation exacte est traitée comme une donnée réglementaire versionnée (`RuleVersion.explanation_template`/`check_definition`, `07-data-model.md` section 16), pas une constante dispersée dans le code - cohérent avec le principe de versionnement déjà posé en `06-technical-architecture.md` (ADR-003). Le libellé précis reste à renseigner depuis une source officielle à jour au moment de l'implémentation de cette règle.                                                                                                                                          | Résolu au niveau du principe de modélisation ; le libellé littéral reste à confirmer au moment de l'implémentation       |
| Fréquence exacte de transmission de l'e-reporting selon le régime de TVA ?                 | **Modélisée comme donnée versionnée**, pas une constante (`frequency` porté par une règle dédiée, avec `regime`, `operation_type`, `effective_from`/`effective_to` - cohérent avec `07-data-model.md` section 16). Indication de départ pour la franchise en base : transmission bimestrielle ; les autres régimes ont des périodicités distinctes, à documenter au fil de l'implémentation de chaque règle.                                                                                                                                                                                       | Résolu au niveau du principe de modélisation ; les fréquences précises par régime restent à finaliser à l'implémentation |
| La référence à une « loi du 10 août 2025 » est-elle exacte ?                               | **Retirée.** Aucune base officielle suffisamment solide ne permet de retenir cette formulation. Les références retenues pour le calendrier et le cadre général sont désormais la loi de finances pour 2024, le CGI, les textes réglementaires d'application, et la loi de finances pour 2026 pour les évolutions de sanctions déjà documentées (section 17).                                                                                                                                                                                                                                       | Résolu - référence supprimée                                                                                             |
| Modalités de la phase de « tolérance » administrative ?                                    | **Ne jamais implémenter une tolérance administrative comme une règle de conformité.** Distinction stricte à respecter dans le Compliance Engine : `LEGAL RULE ≠ ADMINISTRATIVE TOLERANCE ≠ PRODUCT BEHAVIOR`. Si une tolérance officielle est publiée, elle pourra être représentée comme une donnée distincte (`AdministrativeGuidance`), mais elle ne doit jamais transformer un résultat `NON_CONFORME` en `CONFORME` - seulement, le cas échéant, nuancer le ton de la communication produit (`04-product-requirements.md`, section 17-18) sans jamais altérer le résultat du moteur lui-même. | Résolu - principe de non-altération du résultat par une tolérance administrative                                         |
| Seuils de franchise en base de TVA 2026 stabilisés ?                                       | **Oui.** Seuils retenus : vente de biens/hébergement - 85 000 € (seuil de base) / 93 500 € (seuil majoré) ; prestations de services - 37 500 € (seuil de base) / 41 250 € (seuil majoré). Le projet de seuil unique à 25 000 € évoqué dans la version initiale de cette étude a été abandonné. Ces seuils sont implémentés comme des règles versionnées (`07-data-model.md`, section 16), pas comme des constantes globales.                                                                                                                                                                       | Résolu - seuils confirmés et actés                                                                                       |
| Documentation Factur-X/UBL/CII stabilisée ?                                                | **Oui, suffisamment pour démarrer.** Les trois formats (UBL, CII, Factur-X) sont confirmés par la DGFiP. Le FNFE-MPE a publié Factur-X 1.09 / ZUGFeRD 2.5 (juin 2026). **Décision produit : Factur-X en priorité pour le MVP**, plus adapté à la cible TPE/micro-entrepreneurs, puis UBL/CII en complément - voir `04-product-requirements.md` (section 32, mis à jour) et `06-technical-architecture.md` (section 11, mis à jour) pour la traduction technique de ce choix.                                                                                                                       | Résolu - priorité de format actée pour le MVP                                                                            |
| `confidence_level` de la règle `format-facture-electronique` (Phase 7) : peut-il passer de MOYEN à ÉLEVÉ ? | **Oui.** Vérification complémentaire effectuée à l'implémentation de la Phase 7 (docs/12-roadmap.md) : la page officielle DGFiP `impots.gouv.fr/factures-norme-afnor` (modifiée le 02/07/2026) référence les normes AFNOR XP Z12-012/013/014 couvrant Factur-X/UBL/CII, cohérente avec la citation économie.gouv.fr déjà retenue en section 8 (confiance Élevée : un PDF simple/papier scanné/email "ne sera plus conforme") et avec la ligne ci-dessus (formats actés "Résolu"). Cette vérification s'appuie sur une page officielle et des sources professionnelles convergentes, sans lecture intégrale du dossier de spécifications externes DGFiP primaire (document volumineux, non entièrement consulté) - à revalider si une source officielle venait à le contredire. `RuleVersion` v2 de `format-facture-electronique` publiée avec `confidence_level = ELEVE` (`backend/migrations/Version20260820100002.php`), la v1 (`MOYEN`) close à la même date sans être modifiée (ADR-003). | Résolu - décision produit Phase 7, vérification complémentaire non exhaustive |

## 24. Synthèse des exigences réglementaires

En synthèse, les éléments réglementaires suivants doivent être considérés comme des **contraintes fermes** pour la conception du produit (niveau de confiance élevé, sources officielles) :

1. Toute entreprise assujettie à la TVA établie en France, y compris en franchise en base, est concernée par la réforme.
2. Toutes les entreprises doivent pouvoir recevoir des factures électroniques dès le 1er septembre 2026.
3. Les PME, TPE et micro-entreprises doivent pouvoir émettre des factures électroniques et transmettre leurs données d'e-reporting à partir du 1er septembre 2027.
4. Une facture électronique conforme doit être structurée, contenir les mentions obligatoires (dont les quatre nouvelles mentions), et transiter par une plateforme agréée.
5. Un PDF simple envoyé par email ne constitue pas une facture électronique conforme au sens de la réforme.
6. Les opérations B2C et internationales relèvent de l'e-reporting, distinct de l'e-invoicing.
7. Le non-respect des obligations expose à des sanctions financières précises et à un mécanisme de mise en demeure préalable, avec tolérance possible pour une première infraction régularisée rapidement.
8. Le Portail Public de Facturation n'est plus, depuis octobre 2024, un circuit de facturation utilisable directement par les entreprises ; le recours à une plateforme agréée est obligatoire.

Les éléments suivants restent des **zones d'incertitude** à traiter avec prudence (voir section 23) :

- durées exactes de conservation et modalités d'archivage ;
- fréquences exactes de transmission de l'e-reporting par régime ;
- stabilité définitive des seuils de franchise en base de TVA ;
- documentation technique fine des formats de facture.

## 25. Registre des sources

| ID  | Organisme                                                                                                                         | Document/page                                                                         | Date                                        | URL                                                                                                                                                                          | Utilisation                                                                                                                                                                    |
| --- | --------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- | ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| S1  | Ministère de l'Économie (economie.gouv.fr)                                                                                        | Tout savoir sur la facturation électronique pour les entreprises                      | Consultée le 17/08/2026                     | https://www.economie.gouv.fr/tout-savoir-sur-la-facturation-electronique-pour-les-entreprises                                                                                | Source principale - calendrier, définitions, mentions obligatoires, glossaire                                                                                                  |
| S2  | DGFiP (impots.gouv.fr)                                                                                                            | Je découvre la facturation électronique                                               | Consultée le 17/08/2026                     | https://www.impots.gouv.fr/professionnel/la-facturation-electronique-quest-ce-que-cest                                                                                       | Définitions e-invoicing/e-reporting, périmètre entreprises                                                                                                                     |
| S3  | DGFiP (impots.gouv.fr)                                                                                                            | Je consulte la liste des plateformes agréées                                          | Modifiée le 17/08/2026                      | https://www.impots.gouv.fr/je-consulte-la-liste-des-plateformes-agreees                                                                                                      | Définition et modalités d'immatriculation des plateformes agréées                                                                                                              |
| S4  | DGFiP (impots.gouv.fr)                                                                                                            | La facturation électronique, qu'est-ce que ça change pour moi ?                       | Consultée le 17/08/2026                     | https://www.impots.gouv.fr/facturation-electronique-qu-est-ce-que-ca-change-pour-moi                                                                                         | Critère de taille d'entreprise                                                                                                                                                 |
| S5  | DGFiP (impots.gouv.fr)                                                                                                            | Fiche 8 - Transmission des données de paiement                                        | Consultée le 17/08/2026                     | https://www.impots.gouv.fr/sites/default/files/media/1_metier/2_professionnel/EV/2_gestion/290_facturation_electronique/fiche-8_tpe_transmission-des-donnees-de-paiement.pdf | Détail des données d'e-reporting de paiement                                                                                                                                   |
| S6  | Direction de l'information légale et administrative / Service-public.gouv.fr                                                      | Facturation électronique : les sanctions évoluent                                     | Publiée le 20/02/2026                       | https://entreprendre.service-public.gouv.fr/actualites/A18802                                                                                                                | Source principale - évolution des sanctions (loi de finances 2026)                                                                                                             |
| S7  | Légifrance                                                                                                                        | Code général des impôts - Article 1737                                                | Consultée le 17/08/2026                     | https://www.legifrance.gouv.fr/codes/id/LEGISCTA000006163056                                                                                                                 | Texte codifié des sanctions                                                                                                                                                    |
| S8  | BOFiP-Impôts                                                                                                                      | BOI-CF-INF-10-40-40 - Infractions aux règles de facturation                           | Consultée le 17/08/2026                     | https://bofip.impots.gouv.fr/bofip/724-PGP.html/identifiant=BOI-CF-INF-10-40-40-20120912                                                                                     | Sanctions générales préexistantes (art. 1737-I CGI)                                                                                                                            |
| S9  | KPMG Avocats (relais d'un communiqué ministériel)                                                                                 | Facturation électronique : le schéma initialement prévu est modifié                   | Article relatif au communiqué du 15/10/2024 | https://kpmg.com/av/fr/avocats/eclairages/2024/10/facturation-electronique-le-schema-initialement-prevu-est-modifie.html                                                     | Recentrage du rôle du PPF - source secondaire relayant un communiqué ministériel non consulté directement                                                                      |
| S10 | Francenum.gouv.fr (guide rédigé par un partenaire Activateur France Num)                                                          | Guide du e-reporting des données de transaction et de paiement                        | Consultée le 17/08/2026                     | https://www.francenum.gouv.fr/guides-et-conseils/pilotage-de-lentreprise/dematerialisation-des-documents/facturation-1                                                       | Détail du contenu des données d'e-reporting de transaction                                                                                                                     |
| S11 | Bpifrance Création                                                                                                                | Facturation électronique et obligation de e-reporting                                 | Consultée le 17/08/2026                     | https://bpifrance-creation.fr/encyclopedie/gerer-lentreprise/gestion-financiere-comptable/facturation-electronique-obligation-e                                              | Complément sur la définition de l'e-reporting                                                                                                                                  |
| S12 | Diverses sources professionnelles (Pennylane, Cegid, MEG, Dougs, Lido, Hayot, Quadient, comparatif-facture-electronique.fr, etc.) | Articles de blog et guides pratiques sur le calendrier, les formats et le rôle du PPF | Consultées le 17/08/2026                    | Voir citations en sections 5, 9, 12                                                                                                                                          | Utilisées uniquement en recoupement/complément lorsque concordantes entre elles ; jamais comme preuve unique d'un fait réglementaire important                                 |
| S13 | Diverses sources sur les seuils de franchise en base de TVA (CCI Lyon Métropole, Indy, petite-entreprise.net, etc.)               | Articles sur l'évolution (suspendue puis abandonnée) de la réforme des seuils de TVA  | Consultées le 17/08/2026                    | Voir citations en section 6 et question ouverte en section 23                                                                                                                | Sources partiellement contradictoires entre elles ; signalées comme telles, non tranchées dans cette étude                                                                     |
| S14 | INSEE                                                                                                                             | Catégorie d'entreprise (fiche métadonnées, définition officielle)                     | Consultée le 19/08/2026                     | https://www.insee.fr/fr/metadonnees/definition/c1057                                                                                                                         | Section 5 bis : seuils exacts des quatre catégories d'entreprises (décret n° 2008-1354)                                                                                        |
| S15 | DGFiP (impots.gouv.fr)                                                                                                            | À partir de quand suis-je concerné par la réforme de la facturation électronique ?    | Consultée le 19/08/2026                     | https://www.impots.gouv.fr/professionnel/questions/partir-de-quand-suis-je-concerne-par-la-reforme-de-la-facturation                                                         | Section 5 bis : confirme que le critère de taille de la réforme se fonde sur l'article 51 de la loi de modernisation de l'économie du 4 août 2008, la même base légale que S14 |

## 26. Impact sur les prochains documents

- **`03-market-analysis.md`** devra tenir compte du fait que le marché des solutions de facturation électronique est déjà dense (plus d'une centaine de plateformes agréées en cours d'immatriculation ou immatriculées), ce qui renforce la nécessité de différencier notre produit sur l'axe « conformité et pédagogie » plutôt que sur l'axe « plateforme de facturation ».
- **`04-product-requirements.md`** devra s'appuyer sur les scénarios de la section 18 pour prioriser les parcours de vérification de conformité les plus utiles à la cible primaire, et sur la table de la section 19 pour dériver des exigences fonctionnelles et non fonctionnelles précises.
- **`05-user-stories.md`** pourra directement s'inspirer des scénarios A à E de la section 18 pour construire des parcours utilisateurs réalistes et représentatifs de la diversité des situations rencontrées par les TPE et micro-entrepreneurs.
- **`06-technical-architecture.md`** devra statuer sur le positionnement du produit vis-à-vis des plateformes agréées (simple orientation, intégration technique, ou autre) à la lumière de la section 13, et sur la question ouverte relative aux formats techniques (section 23).
- **`07-data-model.md`** devra modéliser distinctement les notions d'assujettissement et de redevabilité TVA (section 6), les différents statuts de client (section 7), et le mécanisme de versionnement des règles (section 21).
- **`08-api-specification.md`** devra anticiper une possible intégration avec une ou plusieurs plateformes agréées ou solutions compatibles, sans que cette étude ne tranche ce choix.
- **`09-test-strategy.md`** devra prévoir des scénarios de test couvrant chacun des cas de la section 18, ainsi que des cas limites liés aux zones d'incertitude de la section 23.
- **`10-security-privacy.md`** devra tenir compte de la sensibilité des données manipulées (données fiscales, SIREN clients, montants de transactions) à la lumière des exigences de transmission décrites en section 11.
- **`11-frontend-design-system.md`** devra permettre de rendre lisibles des notions réglementaires complexes (assujettissement vs redevabilité, e-invoicing vs e-reporting) pour un public non spécialiste, en cohérence avec le principe de pédagogie de `01-intent-note.md`.
- **`12-roadmap.md`** devra tenir compte du calendrier réglementaire de la section 5 (échéances du 1er septembre 2026 et du 1er septembre 2027) comme contrainte de temporalité externe pour le séquencement des livraisons.
