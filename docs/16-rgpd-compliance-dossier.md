# Dossier de conformité RGPD - Phase 17

> Ce document rassemble les **documents de travail** préparant les décisions RGPD
> nécessaires avant la mise en production commerciale (`10-security-privacy.md` §69,
> `12-roadmap.md` §43). Il complète et formalise la matière déjà posée dans
> `10-security-privacy.md` (§9 Personal Data Mapping, §41 Legal Basis, §42 Records of
> Processing - "non rédigé ici, ce document en prépare la matière", §44 External
> Providers, §46 DPIA), sans les dupliquer.
>
> **Aucune ligne de ce document n'affirme une base légale, une qualification
> responsable/sous-traitant, une conclusion d'AIPD ou une conformité RGPD comme
> définitivement acquise.** Chaque point marqué « à valider » nécessite une validation
> juridique professionnelle avant toute formalisation contractuelle ou politique de
> confidentialité destinée aux utilisateurs, conformément à `CLAUDE.md` section 8 et
> `10-security-privacy.md` §69.

## 1. Registre des traitements

Reprend et détaille les traitements déjà esquissés en `10-security-privacy.md` §41,
avec les colonnes attendues d'un registre (§42).

### 1.1 Création et gestion de compte

| Champ | Contenu |
|---|---|
| Finalité | Fourniture du service (authentification, accès au compte) |
| Données traitées | Email, mot de passe (haché, jamais en clair), statut de vérification email |
| Personnes concernées | Utilisateur (dirigeant d'entreprise, micro-entrepreneur, indépendant) |
| Source | Fournie directement par la personne (inscription) |
| Destinataires internes | Aucun au-delà du système applicatif |
| Sous-traitants | Aucun à ce jour (pas de fournisseur email retenu, `10-security-privacy.md` §44) |
| Transferts | Aucun a priori |
| Durée de conservation | Durée de vie du compte, purge différée après suppression (`10-security-privacy.md` §38, délai exact **à confirmer juridiquement**) |
| Mesures de sécurité | Hachage Argon2id, `login_throttling`, JWT access/refresh (`10-security-privacy.md` §12-14) |
| Base légale | **À valider** - orientation indicative : exécution du contrat |

### 1.2 Vérification de conformité d'une facture (cœur du produit)

| Champ | Contenu |
|---|---|
| Finalité | Analyse de conformité à la réglementation de facturation électronique |
| Données traitées | Données d'entreprise (SIREN, statut TVA, effectif), données client (y compris client particulier, tiers non-utilisateur), lignes de facture, montants |
| Personnes concernées | Utilisateur, client de l'utilisateur (tiers) |
| Source | Fournie par l'utilisateur (saisie manuelle ou import de document) |
| Destinataires internes | Organisation propriétaire uniquement (isolation tenant, `TenantFilter`) |
| Sous-traitants | Aucun directement - le Compliance Engine est un traitement interne déterministe (ADR-002), sans appel externe |
| Transferts | Aucun a priori |
| Durée de conservation | Facture originale et résultat de conformité : **10 ans** (décision produit actée, `02-regulatory-study.md` §23, `07-data-model.md` §36) ; données techniques dérivées : durée propre, plus courte, **délai exact à confirmer juridiquement** (`10-security-privacy.md` §38) |
| Mesures de sécurité | Isolation tenant au niveau base de données (ADR-004), audit trail append-only |
| Base légale | **À valider** - orientations indicatives : exécution du contrat (utilisateur), intérêt légitime ou exécution du contrat de l'utilisateur envers son propre client (client tiers - voir qualification §3 ci-dessous) |

### 1.3 Traitement et stockage des documents importés

| Champ | Contenu |
|---|---|
| Finalité | Extraction et validation structurelle d'un document de facture importé (PDF, Factur-X, XML) |
| Données traitées | Contenu binaire du document (peut contenir des données personnelles de tiers mentionnés sur la facture) |
| Personnes concernées | Client, éventuellement autres personnes mentionnées sur le document |
| Source | Fournie par l'utilisateur (upload) |
| Destinataires internes | Organisation propriétaire uniquement ; Validator Container Mustang (interne, réseau Docker, jamais exposé) |
| Sous-traitants | Aucun - Mustang est un composant interne au périmètre de l'éditeur (ADR-008), pas un sous-traitant tiers |
| Transferts | Aucun |
| Durée de conservation | Alignée sur la facture qu'il matérialise (10 ans), sous réserve de suppression du fichier à la demande (`10-security-privacy.md` §38) |
| Mesures de sécurité | Validation magic bytes/MIME/taille avant tout traitement, stockage séparé des métadonnées, `storage_reference` opaque (jamais dérivé d'une donnée utilisateur) |
| Base légale | **À valider** - orientation indicative : exécution du contrat |

### 1.4 Sécurité, audit et accès Platform Administrator

| Champ | Contenu |
|---|---|
| Finalité | Sécurité du service, prévention de la fraude, traçabilité, support technique |
| Données traitées | Logs de connexion, journal d'audit (`AuditLogEntry`, append-only), accès en lecture par un `PlatformAdministrator` dans le cadre de son rôle (surface distincte, ADR-009) |
| Personnes concernées | Utilisateur |
| Source | Générée par le système |
| Destinataires internes | Équipe technique (accès `PlatformAdministrator`, MFA obligatoire, audit systématique de tout accès cross-tenant - `10-security-privacy.md` §17 bis) |
| Sous-traitants | Aucun |
| Transferts | Aucun |
| Durée de conservation | Journal d'audit : longue durée, non plafonnée a priori, jamais supprimé activement (`10-security-privacy.md` §38) |
| Mesures de sécurité | Append-only, accès restreint, MFA, surface Platform Admin encore soumise à un pentest ciblé avant activation en production (voir `docs/17-pentest-scope.md`) |
| Base légale | **À valider** - orientation indicative : intérêt légitime (sécurité) |

### 1.5 Reformulation IA d'un résultat de conformité

Voir dossier fournisseur dédié (section 2 ci-dessous) pour le détail du flux de données.

| Champ | Contenu |
|---|---|
| Finalité | Reformulation pédagogique d'un résultat déjà déterminé par le Compliance Engine, jamais une décision |
| Données traitées | Contexte minimisé d'un `ComplianceFinding` (règle, résultat, éventuellement une valeur observée extraite - ex. un SIREN manquant, un montant incohérent) - jamais l'`Invoice`, le `Customer` ou l'`Organization` eux-mêmes |
| Personnes concernées | Potentiellement l'utilisateur ou son client, selon le contenu de la valeur observée |
| Source | Dérivée d'un traitement déjà effectué (1.2) |
| Destinataires internes | AI Gateway (interne) |
| Sous-traitants | **Mistral AI** (voir section 2) |
| Transferts | Mécanisme de transfert existant selon le DPA Mistral, à vérifier contractuellement (section 2) - pas présumé nul |
| Durée de conservation | Selon la politique de rétention de Mistral (section 2), non stockée côté FactuSentinel au-delà du résultat déjà produit |
| Mesures de sécurité | Minimisation structurelle (DTO dédié, aucun accès direct aux entités métier depuis l'AI Gateway) |
| Base légale | **À valider** - orientation indicative : exécution du contrat ou intérêt légitime |

### 1.6 Notifications (échéances réglementaires et équipe)

| Champ | Contenu |
|---|---|
| Finalité | Rappel d'une obligation réglementaire pertinente, notification d'équipe (invitation, changement de rôle) |
| Données traitées | Email, diagnostic d'éligibilité, contenu du message |
| Personnes concernées | Utilisateur, collaborateur invité |
| Source | Générée par le système ou saisie par l'utilisateur (invitation) |
| Destinataires internes | Aucun au-delà du système ; fournisseur email non encore choisi (`10-security-privacy.md` §44) |
| Sous-traitants | Fournisseur email - **à choisir**, cadre d'évaluation déjà posé (`10-security-privacy.md` §44) mais aucun fournisseur retenu |
| Transferts | Non tranché - dépend du fournisseur qui sera retenu |
| Durée de conservation | Courte à moyenne, purge périodique envisageable (`10-security-privacy.md` §38) |
| Mesures de sécurité | À définir avec le choix du fournisseur |
| Base légale | **À valider** - orientation indicative : exécution du contrat |

### 1.7 Sauvegardes

| Champ | Contenu |
|---|---|
| Finalité | Continuité d'activité, restauration en cas d'incident |
| Données traitées | Copie chiffrée de l'ensemble des données ci-dessus (base de données + stockage documentaire) |
| Personnes concernées | Toutes les personnes concernées par les traitements 1.1 à 1.6 |
| Source | Dérivée des traitements existants |
| Destinataires internes | Équipe technique (accès à la clé de déchiffrement, jamais stockée avec l'archive) |
| Sous-traitants | Stockage objet hors site - **hébergeur à confirmer** (Bloc B, dossier hébergeur `12-roadmap.md`/plan Phase 17) |
| Transferts | Dépend de la localisation du stockage objet retenu - à vérifier une fois l'hébergeur confirmé |
| Durée de conservation | Rétention à définir en cohérence avec le RPO/RTO 24h/24h déjà acté (`10-security-privacy.md` §59) |
| Mesures de sécurité | Chiffrement GPG AES256, clé jamais stockée avec l'archive, jamais réutilisée entre environnements |
| Base légale | **À valider** - orientation indicative : obligation légale/intérêt légitime (continuité d'activité) |

## 2. Dossier fournisseur - Mistral AI

Cartographie précise du flux de données réel (vérifiée dans le code,
`backend/src/AI/Service/ComplianceFindingExplanationContext.php`), complétée par la
vérification du DPA public de Mistral (`legal.mistral.ai/terms/data-processing-addendum`,
vérifié le 26/08/2026).

### 2.1 Ce qui est envoyé

Un seul DTO minimisé, `ComplianceFindingExplanationContext`, construit à partir d'un
`ComplianceFinding` déjà résolu et autorisé :

- Identifiant, nom, description et numéro de version de la règle réglementaire appliquée.
- Résultat déjà déterminé (`CONFORME`, `NON_CONFORME`, etc.) et son message par défaut.
- Champ concerné (`relatedField`) et **valeur observée (`observedValue`)** - point
  d'attention : cette valeur peut contenir une donnée extraite de la facture (ex. un
  SIREN manquant, un montant incohérent), potentiellement une donnée personnelle du
  client de l'utilisateur si le champ concerné en est une. Jamais l'entité `Invoice`,
  `Customer` ou `Organization` elle-même.
- Source réglementaire et niveau de confiance de la règle.

Pour l'endpoint `/assistant/questions` : la question libre saisie par l'utilisateur (peut
contenir n'importe quel texte que l'utilisateur choisit d'y écrire - traité comme une
donnée, jamais comme une instruction, `10-security-privacy.md` §31) et le texte intégral
de `02-regulatory-study.md` embarqué au build (jamais une donnée personnelle).

### 2.2 Ce qui n'est jamais envoyé

Structurellement, par construction (pas seulement par discipline) : l'entité `Invoice`
complète, l'entité `Customer` complète, l'entité `Organization` complète, tout champ
d'authentification ou de compte, toute donnée d'une autre organisation (l'AI Gateway ne
reçoit que ce qui lui est explicitement transmis, aucun accès direct à la base de
données - `06-technical-architecture.md` §14-15).

### 2.3 Points vérifiés dans le DPA public de Mistral, à confirmer contractuellement

Le DPA public (`legal.mistral.ai/terms/data-processing-addendum`, revérifié le 27/08/2026,
numéros de section confirmés à cette date) contredit partiellement l'hypothèse optimiste
précédemment posée dans `10-security-privacy.md` §45 ("risque réduit... mais pas éliminé
par construction") :

- **Localisation/résidence UE** : le DPA lui-même ne détaille pas d'option de résidence UE
  spécifique dans son texte (renvoi au Trust Center pour les mesures techniques) -
  **toujours à confirmer directement dans la console/le contrat du compte utilisé**, pas
  déductible du seul DPA générique.
- **Transferts internationaux** (section 8 du DPA, définition 1(h)) : transferts autorisés
  vers tout pays bénéficiant d'une décision d'adéquation de la Commission européenne
  (section 8.1), et vers les "Restricted Countries" (tout pays hors EEE sans décision
  d'adéquation) sous clauses contractuelles types, Module 4 Sous-traitant→Responsable
  (section 8.2). **Confirmé : un mécanisme de transfert hors UE existe bel et bien** -
  jamais une garantie d'absence de transfert du seul fait que Mistral est une société
  française.
- **Entraînement des modèles** (section 2.3) : **confirmé, modèle opt-out** - Mistral peut
  traiter les données pour l'entraînement "sauf si le client a désactivé l'entraînement"
  (y compris les retours "pouce levé/baissé"). **Action requise, non encore vérifiée comme
  faite** : activer explicitement cette désactivation sur le compte utilisé par
  FactuSentinel avant toute mise en production réelle avec des utilisateurs.
- **Rétention post-résiliation** (section 10.1) : **confirmé** - les données ne sont plus
  accessibles après un délai de 30 jours suivant la fin de l'accès du client.
- **Sous-traitants ultérieurs** (section 7.1, Exhibit 1) : liste tenue à jour à
  `trust.mistral.ai/subprocessors` - **à consulter au moment de la validation
  contractuelle**, jamais supposée stable dans le temps.
- **Zero Data Retention** (section 2.3) : le DPA mentionne cette option comme activable
  pour des exceptions de détection d'abus, sans préciser explicitement le palier d'offre
  qui y donne droit dans le texte du DPA lui-même - **toujours à vérifier directement avec
  le compte/l'offre souscrite**, pas résolu par cette relecture du DPA seul.

### 2.4 Conclusion de ce dossier

**Aucun de ces points n'est présenté comme réglé.** Avant toute mise en production
impliquant des utilisateurs réels, il reste à faire, par toi ou un juriste : lire le DPA
et les conditions contractuelles réelles du compte Mistral utilisé (pas seulement le
document public générique), activer explicitement l'opt-out entraînement, confirmer le
palier d'offre et son éventuelle option Zero Data Retention, documenter la conclusion
dans `10-security-privacy.md` §45 une fois obtenue.

## 3. Qualification responsable de traitement / sous-traitant

Reprend `10-security-privacy.md` §43, précisé (pas tranché) à l'aide du cadre officiel
CNIL (`Déterminer la qualification juridique des acteurs`, `Responsable du traitement,
sous-traitants : comment bien identifier son rôle ?`, vérifiés le 27/08/2026) : le critère
déterminant n'est jamais la qualification choisie contractuellement par les parties, mais
qui détermine réellement les **finalités et moyens** du traitement, et si le prestataire
traite la donnée **pour ses propres finalités** (auquel cas il devient responsable, voire
responsable conjoint) ou **exclusivement pour le compte du client, sur ses instructions**
(auquel cas il est sous-traitant). La CNIL rappelle explicitement qu'elle n'est pas liée
par la qualification contractuelle en cas de contrôle - elle requalifie sur la base des
faits réels.

Appliqué à FactuSentinel, sur la base de ce cadre (analyse informée, pas une conclusion
juridique certaine) :

- **Données du compte utilisateur lui-même** (email, entreprise, factures analysées comme
  objet du service) : FactuSentinel détermine les finalités et moyens de ce traitement
  (fournir le service de vérification) - **responsable de traitement**, orientation
  suffisamment claire pour ne pas nécessiter un arbitrage juridique long, mais toujours
  formellement "à valider" avant toute politique de confidentialité publiée.
- **Données d'un client tiers de l'utilisateur figurant sur une facture** (nom, adresse,
  parfois SIREN) : FactuSentinel ne détermine ni la collecte initiale de cette donnée (faite
  par l'utilisateur, dans le cadre de sa propre relation commerciale) ni son usage final -
  le traitement se limite à vérifier la conformité de la facture qui la contient, sur
  instruction implicite de l'utilisateur, sans réutilisation à des fins propres à
  FactuSentinel (pas de statistiques croisées inter-organisations, pas d'entraînement de
  modèle sur ces données - isolation tenant stricte, ADR-004). C'est la configuration que
  le cadre CNIL qualifie typiquement de **sous-traitance** plutôt que de responsabilité
  conjointe, contrairement à un service qui réutiliserait la donnée pour ses propres
  finalités (ex. amélioration de produit à partir des données de tous les clients
  confondus) - ce que FactuSentinel ne fait structurellement pas.

**Implication concrète, actionnable sans juriste dans un premier temps** : si l'analyse
ci-dessus est confirmée, les conditions générales d'utilisation de FactuSentinel devront
intégrer une clause de sous-traitance conforme à l'article 28 RGPD (objet, durée, nature
et finalité du traitement, obligations de sécurité, sort des données en fin de contrat,
droit d'audit) plutôt qu'un accord de sous-traitance séparé à négocier individuellement
avec chaque utilisateur - une clause standard intégrée aux CGU est une pratique courante et
proportionnée pour un service SaaS grand public/TPE, pas systématiquement un contrat sur
mesure nécessitant un avocat par client. **Cette qualification et la rédaction exacte de
cette clause restent à valider avant publication** - voir section 6 pour les pistes de
validation à coût nul ou faible.

## 4. Screening de nécessité d'une AIPD

### 4.0 Vérification croisée avec la liste positive officielle de la CNIL

Au-delà des 9 critères G29 (grille ci-dessous), la CNIL a publié une **liste positive
distincte et contraignante** de 14 types de traitements pour lesquels une AIPD est
**obligatoire dans tous les cas** (délibération n° 2018-327 du 11 octobre 2018, revérifiée
le 27/08/2026 sur cnil.fr - `Listes des traitements pour lesquels une AIPD est requise ou
non`). Cette liste inclut notamment : données de santé par des établissements de soins,
profilage RH, accompagnement social/médico-social, géolocalisation à large échelle, données
génétiques/biométriques à des fins d'identification, évaluation de solvabilité/scoring
crédit à large échelle, dispositifs de lancement d'alerte, objets connectés de santé.
**Aucun de ces types ne correspond au traitement de FactuSentinel** (pas de données de
santé, pas de profilage RH, pas de scoring de personnes physiques, pas de géolocalisation) -
constat qui renforce, sans le remplacer, le screening par les 9 critères ci-dessous. La
liste complète et à jour reste à consulter directement sur cnil.fr avant toute conclusion
définitive, jamais reproduite de mémoire.

Grille reprenant les **9 critères officiels** issus des lignes directrices G29/CNIL
(vérifiés sur cnil.fr le 26/08/2026 : "Ce qu'il faut savoir sur l'analyse d'impact relative
à la protection des données"). **Règle officielle : une AIPD est nécessaire dès lors qu'au
moins 2 des 9 critères sont remplis.**

| # | Critère | Analyse pour FactuSentinel | Rempli ? |
|---|---|---|---|
| 1 | Évaluation/scoring (y compris profilage) | Le Compliance Engine évalue la conformité d'une **facture/entreprise**, jamais le comportement, la solvabilité ou les caractéristiques d'une **personne physique**. Pas un scoring de personne au sens du critère. | Non |
| 2 | Décision automatisée avec effet légal ou similaire | Le résultat de conformité n'emporte aucune décision automatique ayant un effet juridique direct sur une personne physique (pas de refus de service, pas de sanction automatique) - il informe l'utilisateur, qui reste décisionnaire de ses propres actions. | Non |
| 3 | Surveillance systématique | Aucune surveillance de personnes à grande échelle ou en continu (pas de géolocalisation, pas de suivi comportemental) - le produit traite des factures ponctuellement soumises. | Non |
| 4 | Données sensibles ou hautement personnelles | Les factures contiennent des données financières (montants, SIREN) - sensibles au sens commercial, mais pas des données dites "sensibles" au sens de l'article 9 RGPD (santé, opinions, origine, etc.). **Point de vigilance, pas un critère strictement rempli.** | Discutable - retenu par prudence comme partiellement rempli |
| 5 | Collecte à large échelle | Volume actuel d'un produit naissant (Private Beta achevée, pas encore de clients commerciaux), pas un traitement à large échelle au sens des lignes directrices. **À réévaluer si le volume change significativement.** | Non, à ce stade |
| 6 | Croisement/combinaison de données | Les données restent cloisonnées par organisation (isolation tenant stricte, ADR-004) - pas de croisement inter-organisations ni avec des sources externes tierces. | Non |
| 7 | Personnes vulnérables | Aucun ciblage de personnes vulnérables (patients, enfants, personnes âgées) - le produit s'adresse à des professionnels (micro-entrepreneurs, indépendants) dans le cadre de leur activité. | Non |
| 8 | Usage innovant (nouvelle technologie) | Usage de l'IA générative (Mistral) pour la reformulation - une technologie récente, mais bornée à un rôle non décisionnel (ADR-002) et déjà répandue dans ce type d'usage pédagogique. **Point de vigilance.** | Discutable - retenu par prudence comme partiellement rempli |
| 9 | Exclusion du bénéfice d'un droit/contrat | Le produit n'exclut personne d'un droit ou d'un contrat sur la base d'un traitement automatisé. | Non |

**Décompte** : au maximum 2 critères retenus par prudence (4 et 8), les deux de façon
discutable/non certaine plutôt que clairement remplis. **Conclusion provisoire, marquée
« à valider » - pas la conclusion officielle du screening** : sur la base de cette
analyse, une AIPD complète **n'apparaît pas clairement requise** au stade actuel du
produit (Private Beta achevée, pas de mise en production commerciale à grande échelle),
mais le résultat est suffisamment proche du seuil (2 critères) pour que la décision finale
reste **à valider par toi et/ou un juriste avant la mise en production**, et à
**réexaminer explicitement si le volume d'utilisateurs, le périmètre de l'IA, ou l'usage
des données financières évoluent significativement**.

## 5. Ce qui reste à faire (Bloc B, hors de portée de ce document)

- Validation juridique des bases légales listées « à valider » ci-dessus (section 1).
- Validation juridique de la qualification responsable/sous-traitant (section 3).
- Vérification contractuelle réelle du compte Mistral utilisé (section 2.4).
- Décision finale, validée juridiquement, sur la nécessité d'une AIPD complète (section 4).
- Mise à jour de `10-security-privacy.md` (§41, §43, §45, §46) une fois ces validations
  obtenues, jamais anticipée ici.

## 6. Avancer sans budget avocat - ressources gratuites vérifiées (27/08/2026)

Ce dossier a déjà fait la plus grande partie du travail de préparation (registre, cadre de
qualification appliqué, screening AIPD croisé avec la liste officielle, DPA Mistral
disséqué section par section). Ce qui reste n'est pas nécessairement hors de portée sans
budget - plusieurs ressources officielles gratuites existent en France, à distinguer d'une
simple confirmation d'auto-diagnostic (jamais un substitut à un avis professionnel pour un
point réellement disputé) :

- **Consultations juridiques gratuites CCI + Ordre des avocats** : la plupart des Chambres
  de Commerce et d'Industrie organisent, avec le barreau local, des permanences juridiques
  gratuites sur rendez-vous pour les entrepreneurs (souvent mensuelles, parfois
  bimensuelles) - couvrent typiquement droit des contrats, RGPD, propriété intellectuelle.
  Chercher "[ta ville] CCI permanence juridique gratuite avocat" ou contacter directement
  la CCI de ton département. C'est le canal le plus direct pour faire trancher précisément
  les deux points encore ouverts de ce dossier (qualification sous-traitant section 3,
  bases légales section 1) par un professionnel, sans frais.
- **Registre des traitements** : modèle Excel officiel et gratuit sur cnil.fr (rubrique
  Documentation > Modèle de registre), version "simple" adaptée à une TPE - peut remplacer
  ou compléter le tableau de la section 1 de ce dossier sous une forme que la CNIL
  reconnaît directement en cas de contrôle.
- **Logiciel PIA (AIPD)** : outil libre et gratuit publié par la CNIL (poste de travail ou
  serveur), structuré exactement selon la méthodologie officielle en 4 étapes - à utiliser
  si la conclusion provisoire de la section 4 devait un jour basculer vers "AIPD requise"
  (pas nécessaire dans l'état actuel de l'analyse).
- **CNIL elle-même** : service de renseignement téléphonique et par formulaire, gratuit,
  pour une question ponctuelle et précise (pas une revue complète de produit) -
  `cnil.fr/fr/notification-de-plainte` et rubrique contact du site.

**Ce que ces ressources ne remplacent pas** : la rédaction contractuelle finale (clause de
sous-traitance dans les CGU, section 3), et un désaccord ou un cas limite qu'un
professionnel jugerait réellement incertain après avoir vu le produit réel - dans ce cas,
la permanence gratuite CCI/Ordre des avocats reste le point d'entrée recommandé avant
d'envisager une consultation payante ciblée sur le seul point resté incertain, plutôt
qu'un mandat complet.
