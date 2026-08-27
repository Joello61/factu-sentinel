import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Conditions générales d'utilisation - FactuSentinel",
};

const LAST_UPDATED = "27 août 2026";

export default function CguPage() {
  return (
    <>
      <h1>Conditions générales d&apos;utilisation</h1>
      <p>Dernière mise à jour : {LAST_UPDATED}.</p>

      <section>
        <h2>1. Objet</h2>
        <p>
          Les présentes conditions générales d&apos;utilisation (« CGU ») régissent l&apos;accès
          et l&apos;utilisation du service FactuSentinel (« le Service »), édité dans les
          conditions décrites aux <a href="/mentions-legales">mentions légales</a>. Toute
          création de compte implique l&apos;acceptation pleine et entière des présentes
          CGU.
        </p>
      </section>

      <section>
        <h2>2. Définitions</h2>
        <ul>
          <li>
            <strong>Éditeur</strong> : la personne éditant FactuSentinel, identifiée aux
            mentions légales.
          </li>
          <li>
            <strong>Utilisateur</strong> : toute personne physique agissant à titre
            professionnel (micro-entrepreneur, indépendant, dirigeant de TPE) qui crée un
            compte sur FactuSentinel.
          </li>
          <li>
            <strong>Compte</strong> : l&apos;espace personnel de l&apos;Utilisateur, associé
            à une organisation, permettant l&apos;accès au Service.
          </li>
          <li>
            <strong>Contenu</strong> : toute donnée, document ou information que
            l&apos;Utilisateur saisit, importe ou génère par l&apos;usage du Service (données
            d&apos;entreprise, clients, factures, documents importés).
          </li>
        </ul>
      </section>

      <section>
        <h2>3. Description et périmètre du Service</h2>
        <p>
          FactuSentinel est un assistant de préparation, de contrôle et de compréhension de
          la conformité, qui aide le micro-entrepreneur ou la TPE à comprendre ce qu&apos;il
          doit corriger et à se préparer à utiliser sa plateforme agréée, dans le cadre de la
          réforme française de la facturation électronique (e-invoicing/e-reporting). Il
          permet notamment : la compréhension des obligations applicables selon la situation
          de l&apos;Utilisateur, l&apos;analyse de factures au regard de règles réglementaires
          versionnées, l&apos;identification de problèmes de conformité et l&apos;explication
          de leur cause, la proposition de corrections, et l&apos;accompagnement dans la
          préparation à la facturation électronique.
        </p>
        <p>
          <strong>FactuSentinel n&apos;est pas :</strong> un logiciel de facturation complet,
          un logiciel comptable, un expert-comptable, une plateforme agréée au sens de la
          réforme, une autorité réglementaire, ou une source juridique autonome. Le Service
          n&apos;émet ni ne transmet jamais réellement de facture à un tiers ou à
          l&apos;administration fiscale ; il ne se substitue ni à une plateforme agréée, ni
          à un expert-comptable, ni à un conseil juridique.
        </p>
        <p>
          Un résultat de conformité produit par le Service repose sur une règle
          réglementaire versionnée, dont la source et le niveau de confiance sont toujours
          indiqués. Ce résultat n&apos;est jamais présenté comme un avis juridique ni comme
          une garantie de conformité devant l&apos;administration - il reste une aide à la
          décision, dont l&apos;usage et les suites relèvent de la seule responsabilité de
          l&apos;Utilisateur (voir article 8).
        </p>
      </section>

      <section>
        <h2>4. Création et accès au compte</h2>
        <p>
          L&apos;accès au Service nécessite la création d&apos;un compte, avec une adresse
          email valide et un mot de passe. La vérification de l&apos;adresse email est
          requise avant l&apos;accès aux fonctionnalités sensibles (import de documents,
          analyses persistantes, assistant IA), mais non pour un usage basique du compte.
        </p>
        <p>
          L&apos;Utilisateur est seul responsable de la confidentialité de ses identifiants
          et de toute activité réalisée depuis son compte. Il s&apos;engage à informer
          l&apos;Éditeur sans délai de toute utilisation non autorisée de son compte dont il
          aurait connaissance.
        </p>
        <p>
          Au stade actuel du Service, un seul rôle existe par organisation (« Propriétaire »).
          L&apos;architecture du Service est conçue pour accueillir des rôles supplémentaires
          à l&apos;avenir, sans que cela ne modifie les présentes CGU par anticipation.
        </p>
      </section>

      <section>
        <h2>5. Obligations de l&apos;Utilisateur</h2>
        <p>L&apos;Utilisateur s&apos;engage à :</p>
        <ul>
          <li>
            Fournir des informations exactes et à jour concernant son entreprise et ses
            clients ;
          </li>
          <li>
            Utiliser le Service conformément à sa destination décrite à l&apos;article 3,
            dans le respect des lois et règlements applicables ;
          </li>
          <li>
            Ne téléverser aucun document ou contenu illicite, frauduleux, ou dont il ne
            détient pas les droits nécessaires ;
          </li>
          <li>
            Ne pas tenter de contourner les mesures de sécurité du Service, ni d&apos;accéder
            à des données d&apos;une autre organisation que la sienne ;
          </li>
          <li>
            Respecter, à l&apos;égard de ses propres clients dont les données figurent sur
            les factures analysées, ses propres obligations en matière de protection des
            données personnelles (voir article 9).
          </li>
        </ul>
      </section>

      <section>
        <h2>6. Propriété intellectuelle et Contenu de l&apos;Utilisateur</h2>
        <p>
          L&apos;Utilisateur demeure propriétaire de l&apos;intégralité du Contenu qu&apos;il
          saisit ou importe sur le Service. Il concède à l&apos;Éditeur, pour la seule durée
          d&apos;utilisation du Service et dans la seule mesure nécessaire à sa fourniture
          (stockage, traitement, analyse, sauvegarde), un droit d&apos;usage non exclusif de
          ce Contenu - sans que ce droit n&apos;autorise une exploitation commerciale du
          Contenu par l&apos;Éditeur.
        </p>
        <p>
          Les éléments propres au Service (logiciel, règles réglementaires versionnées,
          interface, marque) demeurent la propriété exclusive de l&apos;Éditeur - voir les{" "}
          <a href="/mentions-legales">mentions légales</a>.
        </p>
      </section>

      <section>
        <h2>7. Intelligence artificielle</h2>
        <p>
          Certaines explications ou réponses du Service sont générées par un modèle
          d&apos;intelligence artificielle tiers (Mistral AI), à partir d&apos;un contexte
          strictement limité et déjà déterminé par le moteur de conformité du Service - le
          modèle d&apos;IA ne détermine jamais lui-même un résultat de conformité, il en
          reformule un déjà établi. Ces contenus sont toujours identifiés comme générés par
          IA dans l&apos;interface. Le détail des données transmises à ce prestataire figure
          dans la <a href="/politique-de-confidentialite">politique de confidentialité</a>.
        </p>
      </section>

      <section>
        <h2>8. Absence de garantie de résultat réglementaire - limitation de responsabilité</h2>
        <p>
          Le Service constitue une aide à la compréhension et à la préparation de la
          conformité réglementaire, fondée sur l&apos;état de la réglementation connu et
          versionné à la date de l&apos;analyse. L&apos;Éditeur ne peut garantir
          l&apos;exhaustivité ou l&apos;absence d&apos;évolution ultérieure de la
          réglementation, ni que l&apos;usage du Service suffit, à lui seul, à assurer la
          conformité réglementaire complète de l&apos;Utilisateur vis-à-vis de
          l&apos;administration.
        </p>
        <p>
          La responsabilité de l&apos;Éditeur ne saurait être engagée en cas de sanction,
          pénalité ou préjudice résultant d&apos;une décision prise par l&apos;Utilisateur
          sur la seule base d&apos;un résultat du Service, sans vérification indépendante
          lorsque celle-ci s&apos;imposait, ni en cas d&apos;interruption, de perte de
          données ou de dysfonctionnement du Service dus à un cas de force majeure, à un
          tiers, ou à un usage non conforme aux présentes CGU. Cette limitation ne saurait
          exclure la responsabilité de l&apos;Éditeur en cas de faute lourde ou intentionnelle,
          ni dans les cas où la loi l&apos;interdit.
        </p>
        <p>
          Le Service est fourni en l&apos;état, sans engagement de disponibilité continue
          (« SLA ») au stade actuel de son développement. L&apos;Éditeur met en œuvre des
          moyens raisonnables pour assurer la disponibilité, la sécurité et la sauvegarde du
          Service, sans garantie de résultat sur ces points.
        </p>
      </section>

      <section>
        <h2>9. Protection des données personnelles - qualification des rôles</h2>
        <p>
          Pour les données relatives à l&apos;Utilisateur lui-même et à son compte,
          l&apos;Éditeur agit en tant que responsable de traitement, dans les conditions
          décrites dans la{" "}
          <a href="/politique-de-confidentialite">politique de confidentialité</a>.
        </p>
        <p>
          Pour les données relatives aux clients de l&apos;Utilisateur figurant sur les
          factures et documents analysés par le Service, l&apos;Éditeur agit en qualité de
          sous-traitant au sens de l&apos;article 28 du Règlement (UE) 2016/679 (RGPD),
          l&apos;Utilisateur restant seul responsable de traitement à l&apos;égard de ses
          propres clients. À ce titre, l&apos;Éditeur s&apos;engage à :
        </p>
        <ul>
          <li>
            Ne traiter ces données que pour la finalité d&apos;analyse de conformité
            décrite à l&apos;article 3, sur la seule instruction documentée de
            l&apos;Utilisateur que constitue son usage du Service ;
          </li>
          <li>
            Garantir la confidentialité de ces données et l&apos;isolation stricte entre
            organisations utilisatrices du Service ;
          </li>
          <li>
            Mettre en œuvre les mesures de sécurité techniques et organisationnelles
            décrites dans la politique de confidentialité ;
          </li>
          <li>
            Ne recourir à un sous-traitant ultérieur qu&apos;avec les garanties
            appropriées, et en informer l&apos;Utilisateur dans les conditions décrites dans
            la politique de confidentialité ;
          </li>
          <li>
            Assister raisonnablement l&apos;Utilisateur dans le respect de ses propres
            obligations envers ses clients (droits des personnes concernées, notification
            d&apos;une violation de données) ;
          </li>
          <li>
            Supprimer ou restituer ces données au terme de la relation contractuelle, sous
            réserve des obligations légales de conservation applicables (notamment la
            conservation décennale des factures).
          </li>
        </ul>
        <p>
          Cette qualification résulte d&apos;une analyse informée au regard des critères
          publiés par la Commission Nationale de l&apos;Informatique et des Libertés
          (CNIL) et n&apos;a pas fait l&apos;objet d&apos;une validation par un professionnel
          du droit à la date des présentes.
        </p>
      </section>

      <section>
        <h2>10. Tarification et droit de rétractation</h2>
        <p>
          Le Service est actuellement proposé sans contrepartie financière. En cas
          d&apos;introduction future d&apos;une offre payante, les conditions tarifaires
          seront communiquées séparément et de façon transparente avant toute souscription.
        </p>
        <p>
          Conformément à l&apos;article L221-3 du Code de la consommation, tout Utilisateur
          employant cinq salariés ou moins, souscrivant à une offre payante pour un usage
          hors du champ de son activité professionnelle principale, bénéficiera d&apos;un
          délai de rétractation de quatorze (14) jours à compter de la souscription, sans
          avoir à justifier de motif ni à supporter de pénalité - l&apos;Éditeur retient
          cette protection par précaution, sans se prononcer sur le caractère strictement
          obligatoire de son application à une souscription réalisée en ligne.
        </p>
      </section>

      <section>
        <h2>11. Suspension et résiliation</h2>
        <p>
          L&apos;Utilisateur peut supprimer son compte à tout moment depuis les paramètres
          du Service. L&apos;Éditeur peut suspendre ou résilier l&apos;accès d&apos;un
          Utilisateur en cas de manquement grave aux présentes CGU, après notification sauf
          urgence avérée (notamment en cas d&apos;atteinte à la sécurité du Service ou aux
          droits d&apos;un tiers).
        </p>
        <p>
          Les données conservées après suppression du compte (notamment les obligations de
          conservation légale des factures, dix ans) sont décrites dans la{" "}
          <a href="/politique-de-confidentialite">politique de confidentialité</a>.
        </p>
      </section>

      <section>
        <h2>12. Évolution du statut de l&apos;Éditeur</h2>
        <p>
          À la date des présentes, l&apos;Éditeur agit à titre non professionnel au sens de
          la loi pour la confiance dans l&apos;économie numérique (voir{" "}
          <a href="/mentions-legales">mentions légales</a>), le Service ne générant aucune
          contrepartie économique. Dès l&apos;introduction d&apos;une contrepartie
          économique réelle, l&apos;Éditeur s&apos;engage à régulariser sa situation
          (immatriculation professionnelle) et à mettre à jour les présentes CGU et les
          mentions légales en conséquence, avant l&apos;activation effective de tout
          paiement.
        </p>
      </section>

      <section>
        <h2>13. Modification des CGU</h2>
        <p>
          L&apos;Éditeur peut modifier les présentes CGU, notamment pour refléter une
          évolution du Service, de la réglementation applicable, ou de son propre statut
          (article 12). Toute modification substantielle sera portée à la connaissance des
          Utilisateurs par un moyen raisonnable (notification dans le Service ou par email)
          avant son entrée en vigueur.
        </p>
      </section>

      <section>
        <h2>14. Droit applicable</h2>
        <p>
          Les présentes CGU sont soumises au droit français. En cas de litige, et à défaut
          de résolution amiable, les tribunaux français compétents seront seuls saisis,
          dans les conditions de droit commun applicables à la qualité des parties.
        </p>
      </section>
    </>
  );
}
