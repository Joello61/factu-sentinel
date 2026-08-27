# Dossier des documents légaux publiés - Phase 17

Ce document ne recopie pas le texte publié aux utilisateurs (mentions légales, CGU,
politique de confidentialité - `frontend/app/(legal)/`). Il enregistre les **décisions
prises pour les rédiger**, leur justification, leurs sources, et ce qui devra être revu
explicitement à un jalon précis - à la manière de `docs/16-rgpd-compliance-dossier.md`,
dont ce document reprend directement plusieurs conclusions sans les redupliquer.

**Ces textes ont été rédigés par recherche approfondie de sources officielles (Légifrance,
CNIL, economie.gouv.fr), sans validation par un professionnel du droit** - décision
explicite de l'éditeur (contrainte budgétaire assumée, chemin de validation gratuit déjà
documenté en `16-rgpd-compliance-dossier.md` section 6). Ce n'est pas présenté comme une
garantie d'absence de risque juridique, mais comme un travail de préparation sérieux et
sourcé, à réviser par un professionnel dès qu'un point contesté ou un changement d'échelle
réel le justifierait.

## 1. Mentions légales - statut retenu : éditeur non professionnel

**Contexte factuel vérifié dans le dépôt** : à la date de rédaction (27/08/2026), aucune
intégration de paiement n'existe dans le code (`BR-SCOPE-001`, `04-product-requirements.md`
section 30) - le produit ne perçoit aucune contrepartie économique. L'éditeur n'a par
ailleurs aucune structure enregistrée (ni auto-entrepreneur, ni société).

**Base légale retenue** : article 1-1 de la LCEN (loi n° 2004-575 du 21 juin 2004, modifiée
par la loi SREN n° 2024-449 du 21 mai 2024 - **l'ancien article 6-III, souvent encore cité
par erreur dans des guides non à jour, est abrogé depuis le 23 mai 2024**, vérifié le
27/08/2026). Cet article permet à un éditeur agissant **à titre non professionnel** de ne
communiquer son identité complète (nom, adresse) qu'à son hébergeur, celui-ci la transmettant
uniquement sur réquisition de l'autorité judiciaire - à condition de publier l'identité de
l'hébergeur. **Cette qualification est perdue dès qu'une contrepartie économique apparaît,
même modeste** (jurisprudence/doctrine constante sur ce point).

**Décision explicite (confirmée avec l'éditeur le 27/08/2026)** : rédiger les mentions
légales sur la base **réelle actuelle** (non professionnel, gratuit) plutôt que d'anticiper
un statut professionnel qui n'existe pas encore - publier une adresse personnelle
maintenant n'aurait aucun bénéfice juridique tant qu'aucune structure n'est enregistrée et
qu'aucun paiement n'existe.

**Déclencheur explicite de révision, à ne jamais manquer** : dès l'introduction d'un
premier paiement réel (quel que soit le montant), avant sa mise en service :
1. Immatriculation en auto-entrepreneur (gratuite, `formalites.entreprises.gouv.fr`,
   démarche courte) ou toute autre structure retenue.
2. Réécriture complète des mentions légales avec nom, adresse (personnelle ou de
   domiciliation), SIRET, forme juridique.
3. Revue du statut retenu en section 3 ci-dessous (droit de rétractation), qui ne
   s'applique que si une vente à distance existe réellement.

**Identité publique retenue** (fournie explicitement par l'éditeur le 27/08/2026, jamais
déduite ni devinée) : Tchinda Tchoffo Timothée Joël, contact
`tchindajoel61@gmail.com`.

**Hébergeur déclaré** : OVH SAS, 2 rue Kellermann, 59100 Roubaix, France, RCS Lille
Métropole 424 761 419 (identité de la société vérifiée par recherche croisée de sources
publiques le 27/08/2026 - capital social non confirmé avec une source primaire directement
accessible à cette date, volontairement omis plutôt qu'inventé).

## 2. CGU - positions prises, à connaître avant toute modification

- **Périmètre produit** : reprend mot pour mot la formulation de référence de
  `../CLAUDE.md` section 7 et `04-product-requirements.md` section 32 bis - jamais une
  reformulation libre qui risquerait de dériver du périmètre acté (`BR-SCOPE-001`).
- **Absence de garantie de résultat réglementaire** : rédigée en cohérence avec le principe
  produit "Pourquoi, jamais seulement si" (`../CLAUDE.md` section 9) - la limitation de
  responsabilité ne nie jamais la fiabilité du Compliance Engine déterministe, elle
  rappelle que la responsabilité réglementaire finale reste celle de l'utilisateur envers
  l'administration, FactuSentinel n'étant ni une plateforme agréée ni une autorité
  réglementaire (ADR-002).
- **Droit de rétractation pour "petits professionnels"** (article L221-3 du Code de la
  consommation, vérifié le 27/08/2026 sur Légifrance/economie.gouv.fr) : ce texte étend le
  droit de rétractation aux professionnels de 5 salariés ou moins, pour un contrat hors du
  champ de leur activité principale, **mais seulement pour un contrat conclu hors
  établissement** - la doctrine consultée ne converge pas clairement sur le fait qu'une
  simple inscription en ligne sur un site SaaS constitue un contrat "hors établissement"
  au sens de cet article. **Point non tranché ici, jamais présenté comme tranché dans les
  CGU** : plutôt que d'affirmer une conclusion incertaine dans un sens ou dans l'autre, les
  CGU proposent volontairement une fenêtre de rétractation de 14 jours dès qu'une offre
  payante existera, pour tout utilisateur remplissant les deux autres conditions (5
  salariés ou moins, hors de son activité principale) - une position prudente et
  protectrice plutôt qu'un pari sur une lecture stricte du texte.
- **Qualification sous-traitant RGPD** : reprend directement la conclusion de
  `16-rgpd-compliance-dossier.md` section 3 (analyse informée, pas une certitude juridique)
  - clause de sous-traitance intégrée aux CGU plutôt qu'un accord séparé par utilisateur,
  conformément à la pratique courante pour un SaaS grand public/TPE identifiée dans ce même
  document.
- **Aucun droit de rétractation consommateur classique** (Code de la consommation,
  dispositions générales) ne s'applique par ailleurs : les utilisateurs de FactuSentinel
  agissent tous dans un cadre professionnel (micro-entrepreneurs, indépendants, TPE),
  jamais en tant que consommateurs au sens strict pour ce service.

## 3. Politique de confidentialité - source unique de vérité

Le contenu factuel (finalités, données traitées, bases légales, durées, destinataires,
sous-traitants, transferts) est repris **directement et sans divergence** du registre des
traitements (`16-rgpd-compliance-dossier.md` section 1) et du dossier fournisseur Mistral
(section 2 du même document) - jamais une réécriture indépendante qui risquerait de
diverger du registre interne. Toute mise à jour du registre doit se répercuter dans la
politique de confidentialité publiée, jamais l'inverse.

Les bases légales encore marquées « à valider » dans le registre sont publiées avec
l'orientation retenue (ex. « exécution du contrat ») - une politique de confidentialité
doit prendre une position claire pour être utile aux personnes concernées, elle ne peut pas
rester au conditionnel indéfiniment. Cette position reste révisable si une validation
juridique ultérieure (section 6 du dossier RGPD) en décidait autrement.

## 4. Cookies

**Constat vérifié dans le code** (recherche exhaustive le 27/08/2026, `frontend/`) :
aucun outil d'analyse d'audience ou de mesure tierce (Google Analytics, Matomo, Hotjar,
etc.) n'existe dans le frontend consommateur - la seule surface "analytics" trouvée est le
tableau de bord interne Platform Admin, qui lit les propres données de FactuSentinel, pas
un traceur tiers sur les visiteurs. Le seul cookie déposé est le refresh token JWT,
`HttpOnly`/`Secure`/`SameSite` (`../CLAUDE.md` section 13, `backend/CLAUDE.md` section 8) -
strictement nécessaire à l'authentification.

**Base légale retenue** (recommandations CNIL sur les cookies et traceurs, vérifiées le
27/08/2026) : un cookie d'authentification strictement nécessaire au service explicitement
demandé par l'utilisateur est **exempté du recueil du consentement préalable** - **aucun
bandeau cookies n'est donc requis actuellement**, seule une information doit rester
accessible (intégrée à la politique de confidentialité, jamais un bandeau intrusif pour un
cookie exempté). **À réexaminer explicitement si un outil de mesure d'audience ou tout
autre traceur non strictement nécessaire est ajouté un jour** - ce jour-là, un bandeau de
consentement granulaire (jamais un simple "tout accepter/tout refuser", exigence CNIL
2026) deviendra nécessaire.

## 5. Emplacement dans le produit

- Pages : `frontend/app/(legal)/mentions-legales`, `.../cgu`, `.../politique-de-confidentialite`
  (groupe de routes dédié, layout minimal partagé, jamais l'App Shell authentifié).
- Liens : pied de page des écrans publics (connexion/inscription), case à cocher non
  pré-cochée obligatoire à l'inscription (CGU + politique de confidentialité, cohérent avec
  `11-frontend-design-system.md` section 42 : "non pré-coché"), section dédiée dans les
  paramètres du compte (`(app)/settings`) pour un utilisateur déjà connecté.

## 6. Ce qui reste explicitement ouvert

- Tout ce que `16-rgpd-compliance-dossier.md` section 5 liste déjà comme non tranché.
- Le statut LCEN (section 1 ci-dessus), à réviser au premier euro de contrepartie
  économique réel.
- Le capital social de l'hébergeur (mention omise, jamais inventée).
- Une validation professionnelle reste recommandée avant une mise à l'échelle réelle
  (volume d'utilisateurs significatif, levée de fonds, ou tout litige) - les canaux
  gratuits identifiés en `16-rgpd-compliance-dossier.md` section 6 restent le point
  d'entrée recommandé.
