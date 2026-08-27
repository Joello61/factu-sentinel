import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Politique de confidentialité - FactuSentinel",
};

const LAST_UPDATED = "27 août 2026";

export default function PolitiqueConfidentialitePage() {
  return (
    <>
      <h1>Politique de confidentialité</h1>
      <p>Dernière mise à jour : {LAST_UPDATED}.</p>

      <section>
        <h2>1. Qui est responsable de vos données ?</h2>
        <p>
          Pour les données décrites à la section 3 ci-dessous, FactuSentinel agit en tant
          que responsable de traitement, au sens du Règlement (UE) 2016/679 du 27 avril
          2016 (« RGPD ») et de la loi n° 78-17 du 6 janvier 1978 modifiée. Son identité
          figure dans les <a href="/mentions-legales">mentions légales</a>.
        </p>
        <p>
          Pour les données de vos propres clients figurant sur les factures que vous
          analysez, FactuSentinel agit en tant que sous-traitant, vous restant responsable
          de traitement à leur égard - voir l&apos;article 9 des{" "}
          <a href="/cgu">conditions générales d&apos;utilisation</a>.
        </p>
        <p>
          Pour toute question relative à cette politique ou à l&apos;exercice de vos
          droits, vous pouvez écrire à{" "}
          <a href="mailto:tchindajoel61@gmail.com">tchindajoel61@gmail.com</a>. Compte tenu
          de la taille actuelle du Service, aucun délégué à la protection des données (DPO)
          n&apos;a été désigné à titre obligatoire - cette adresse assure ce rôle de contact
          en pratique.
        </p>
      </section>

      <section>
        <h2>2. Principe général</h2>
        <p>
          FactuSentinel applique le principe de minimisation des données : chaque
          traitement décrit ci-dessous ne collecte que les données nécessaires à sa
          finalité, jamais par défaut au-delà. Vos données ne sont jamais vendues, ni
          utilisées à des fins publicitaires, ni transmises à un tiers en dehors des
          sous-traitants strictement nécessaires à la fourniture du Service, listés
          ci-dessous.
        </p>
      </section>

      <section>
        <h2>3. Les données que nous traitons</h2>

        <h3>3.1 Création et gestion de votre compte</h3>
        <p>
          <strong>Données</strong> : adresse email, mot de passe (jamais conservé en clair,
          uniquement sous forme hachée), statut de vérification de l&apos;email.
          <br />
          <strong>Finalité</strong> : vous permettre de créer un compte et d&apos;accéder au
          Service.
          <br />
          <strong>Base légale</strong> : exécution du contrat qui nous lie (les présentes
          CGU) - orientation retenue, non validée par un professionnel du droit à ce jour.
          <br />
          <strong>Durée de conservation</strong> : durée de vie de votre compte, purgée
          après un délai suivant sa suppression.
          <br />
          <strong>Destinataires</strong> : aucun en dehors de FactuSentinel.
        </p>

        <h3>3.2 Vérification de conformité de vos factures</h3>
        <p>
          <strong>Données</strong> : données de votre entreprise (SIREN, statut TVA,
          effectif), données de vos clients figurant sur vos factures, lignes de facture,
          montants.
          <br />
          <strong>Finalité</strong> : analyser la conformité de vos factures à la
          réglementation française de facturation électronique.
          <br />
          <strong>Base légale</strong> : exécution du contrat, à votre égard comme,
          s&apos;agissant des données de vos clients, à l&apos;égard de votre propre
          relation contractuelle avec eux (orientation retenue, non validée par un
          professionnel du droit à ce jour).
          <br />
          <strong>Durée de conservation</strong> : la facture originale et son résultat de
          conformité sont conservés dix ans, conformément à l&apos;obligation légale de
          conservation des documents comptables.
          <br />
          <strong>Destinataires</strong> : votre organisation exclusivement - une isolation
          technique stricte empêche toute autre organisation d&apos;y accéder.
        </p>

        <h3>3.3 Documents que vous importez</h3>
        <p>
          <strong>Données</strong> : le contenu de vos documents importés (PDF, Factur-X,
          XML), qui peut contenir des données personnelles de vos clients ou d&apos;autres
          personnes mentionnées sur le document.
          <br />
          <strong>Finalité</strong> : extraire et valider la structure du document importé.
          <br />
          <strong>Base légale</strong> : exécution du contrat.
          <br />
          <strong>Durée de conservation</strong> : alignée sur celle de la facture qu&apos;il
          matérialise (dix ans), sauf suppression du fichier à votre demande.
          <br />
          <strong>Destinataires</strong> : votre organisation exclusivement ; un composant
          technique interne de validation de documents, jamais exposé à l&apos;extérieur et
          jamais un sous-traitant tiers.
        </p>

        <h3>3.4 Sécurité, journalisation et support</h3>
        <p>
          <strong>Données</strong> : journaux de connexion, journal d&apos;audit des
          actions réalisées sur votre compte.
          <br />
          <strong>Finalité</strong> : sécurité du Service, prévention de la fraude,
          traçabilité, support technique.
          <br />
          <strong>Base légale</strong> : intérêt légitime (sécurité du Service).
          <br />
          <strong>Durée de conservation</strong> : le journal d&apos;audit est conservé sans
          limitation de durée prédéfinie et n&apos;est jamais supprimé activement, afin de
          garantir la traçabilité en cas d&apos;incident de sécurité.
          <br />
          <strong>Destinataires</strong> : l&apos;équipe technique de l&apos;Éditeur, dans
          le cadre strict de son rôle, avec une journalisation systématique de tout accès à
          des données d&apos;une organisation autre que la sienne.
        </p>

        <h3>3.5 Reformulation par intelligence artificielle</h3>
        <p>
          <strong>Données transmises à notre prestataire d&apos;IA</strong> : un contexte
          strictement limité (la règle réglementaire appliquée, le résultat déjà déterminé,
          et éventuellement une valeur précise extraite de votre facture pertinente pour
          l&apos;explication - par exemple un numéro SIREN manquant), ou le texte de votre
          question si vous utilisez l&apos;assistant. Nous ne transmettons jamais votre
          facture, votre fiche client ou votre fiche entreprise dans leur intégralité, ni
          aucune donnée d&apos;authentification.
          <br />
          <strong>Finalité</strong> : reformuler de façon pédagogique un résultat déjà
          déterminé par notre moteur de conformité interne, jamais pour produire ce
          résultat lui-même.
          <br />
          <strong>Base légale</strong> : exécution du contrat ou intérêt légitime
          (orientation retenue, non validée par un professionnel du droit à ce jour).
          <br />
          <strong>Sous-traitant</strong> : Mistral AI - voir la section 5 ci-dessous pour le
          détail des garanties vérifiées.
        </p>

        <h3>3.6 Notifications</h3>
        <p>
          <strong>Données</strong> : votre adresse email, le contenu du message envoyé
          (rappel d&apos;échéance réglementaire, invitation d&apos;un collaborateur).
          <br />
          <strong>Finalité</strong> : vous informer d&apos;une obligation réglementaire
          pertinente ou d&apos;un événement relatif à votre compte.
          <br />
          <strong>Base légale</strong> : exécution du contrat.
          <br />
          <strong>Destinataires</strong> : un prestataire d&apos;envoi d&apos;email n&apos;a
          pas encore été retenu à ce jour ; cette politique sera mise à jour dès qu&apos;un
          choix sera fait.
        </p>

        <h3>3.7 Sauvegardes</h3>
        <p>
          <strong>Données</strong> : une copie chiffrée de l&apos;ensemble des données
          décrites ci-dessus.
          <br />
          <strong>Finalité</strong> : continuité d&apos;activité et restauration en cas
          d&apos;incident technique.
          <br />
          <strong>Base légale</strong> : intérêt légitime (continuité d&apos;activité).
          <br />
          <strong>Mesures de sécurité</strong> : chiffrement systématique, clé de
          déchiffrement jamais conservée avec la sauvegarde elle-même.
          <br />
          <strong>Destinataires</strong> : un espace de stockage hébergé en Union
          européenne, distinct du serveur applicatif.
        </p>
      </section>

      <section>
        <h2>4. Sécurité de vos données</h2>
        <p>
          FactuSentinel met en œuvre des mesures techniques et organisationnelles
          proportionnées, parmi lesquelles : le hachage des mots de passe (jamais un
          stockage en clair), l&apos;isolation stricte des données entre organisations
          utilisatrices, le chiffrement des sauvegardes, la validation systématique de tout
          document importé avant traitement, et une politique de moindre privilège pour les
          accès internes. Aucun système n&apos;étant infaillible, FactuSentinel s&apos;engage
          à vous notifier, ainsi que l&apos;autorité compétente lorsque la loi
          l&apos;exige, en cas de violation de données présentant un risque pour vos
          droits.
        </p>
      </section>

      <section>
        <h2>5. Recours à un prestataire d&apos;intelligence artificielle - Mistral AI</h2>
        <p>
          FactuSentinel utilise le modèle d&apos;intelligence artificielle de Mistral AI,
          fournisseur établi en France, pour reformuler de façon pédagogique certains
          résultats déjà déterminés par notre moteur de conformité. Les points suivants ont
          été vérifiés dans les conditions de traitement des données publiées par ce
          prestataire :
        </p>
        <ul>
          <li>
            Mistral AI peut transférer des données vers des pays bénéficiant d&apos;une
            décision d&apos;adéquation de la Commission européenne, ou vers d&apos;autres
            pays sous couvert de clauses contractuelles types approuvées par la Commission
            européenne ;
          </li>
          <li>
            Mistral AI peut utiliser les données transmises pour l&apos;entraînement de ses
            modèles, sauf désactivation explicite de cette option par ses clients ;
            FactuSentinel s&apos;engage à activer cette désactivation avant toute mise en
            production impliquant des utilisateurs réels ;
          </li>
          <li>
            les données ne sont plus accessibles au-delà de trente jours suivant la fin de
            la relation contractuelle avec ce prestataire ;
          </li>
          <li>
            la liste des sous-traitants ultérieurs de Mistral AI est publiée et tenue à
            jour par ce dernier.
          </li>
        </ul>
        <p>
          Un échec ou une indisponibilité de ce prestataire n&apos;affecte jamais
          l&apos;affichage du résultat de conformité déjà déterminé par notre moteur
          interne, qui ne dépend d&apos;aucun prestataire externe pour fonctionner.
        </p>
      </section>

      <section>
        <h2>6. Transferts hors Union européenne</h2>
        <p>
          Votre organisation, votre compte et vos documents sont hébergés au sein de
          l&apos;Union européenne. Le seul transfert identifié à ce jour est celui décrit à
          la section 5 ci-dessus, relatif au prestataire d&apos;intelligence artificielle,
          limité au contexte minimisé qui y est décrit - jamais vos documents, vos données
          clients ou vos données de compte dans leur intégralité.
        </p>
      </section>

      <section>
        <h2>7. Cookies</h2>
        <p>
          FactuSentinel ne dépose qu&apos;un seul cookie : un jeton de connexion («
          refresh token »), strictement nécessaire à votre authentification, protégé
          (inaccessible en JavaScript, transmis uniquement en connexion sécurisée). Ce
          cookie étant strictement nécessaire au service que vous demandez explicitement en
          vous connectant, il est exempté de recueil de consentement préalable,
          conformément aux recommandations de la Commission Nationale de
          l&apos;Informatique et des Libertés (CNIL). FactuSentinel ne dépose et
          n&apos;autorise, à ce jour, aucun cookie de mesure d&apos;audience ni aucun
          cookie publicitaire. Cette politique sera mise à jour, avec recueil de votre
          consentement préalable si nécessaire, avant l&apos;introduction éventuelle d&apos;un
          tel outil.
        </p>
      </section>

      <section>
        <h2>8. Vos droits</h2>
        <p>
          Conformément au RGPD et à la loi Informatique et Libertés, vous disposez, sur les
          données vous concernant, d&apos;un droit d&apos;accès, de rectification,
          d&apos;effacement, de limitation, d&apos;opposition (pour les traitements fondés
          sur l&apos;intérêt légitime), et de portabilité. Vous pouvez exercer ces droits en
          écrivant à <a href="mailto:tchindajoel61@gmail.com">tchindajoel61@gmail.com</a>,
          ou directement depuis les paramètres de votre compte pour l&apos;accès,
          l&apos;export et la suppression de vos données.
        </p>
        <p>
          Une demande d&apos;effacement peut se heurter à une obligation légale de
          conservation (notamment la conservation décennale des factures) - dans ce cas,
          seules les données non concernées par cette obligation sont effacées
          immédiatement, les autres le sont à l&apos;expiration du délai légal. Vous en
          serez informé précisément lors de votre demande.
        </p>
        <p>
          Vous disposez également du droit d&apos;introduire une réclamation auprès de la
          CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">
            www.cnil.fr
          </a>), autorité de contrôle compétente en France.
        </p>
      </section>

      <section>
        <h2>9. Analyse d&apos;impact relative à la protection des données</h2>
        <p>
          FactuSentinel a évalué, sur la base des critères publiés par la CNIL, si une
          analyse d&apos;impact relative à la protection des données (AIPD) était requise
          pour ses traitements. À la date des présentes, et au stade actuel de son
          développement, cette analyse n&apos;apparaît pas clairement requise. Cette
          conclusion sera réexaminée explicitement si le volume d&apos;utilisateurs, le
          périmètre du recours à l&apos;intelligence artificielle, ou la nature des données
          traitées venaient à évoluer significativement.
        </p>
      </section>

      <section>
        <h2>10. Modification de cette politique</h2>
        <p>
          Cette politique peut être modifiée, notamment pour refléter une évolution du
          Service, de ses sous-traitants, ou de la réglementation applicable. Toute
          modification substantielle vous sera communiquée par un moyen raisonnable avant
          son entrée en vigueur.
        </p>
      </section>
    </>
  );
}
