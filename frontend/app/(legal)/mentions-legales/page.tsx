import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Mentions légales - FactuSentinel",
};

const LAST_UPDATED = "27 août 2026";

export default function MentionsLegalesPage() {
  return (
    <>
      <h1>Mentions légales</h1>
      <p>Dernière mise à jour : {LAST_UPDATED}.</p>

      <section>
        <h2>1. Éditeur du site</h2>
        <p>
          Le présent site est édité par Tchinda Tchoffo Timothée Joël, à titre non
          professionnel au sens de l&apos;article 1-1 de la loi n° 2004-575 du 21 juin 2004
          pour la confiance dans l&apos;économie numérique (LCEN), dans sa rédaction issue
          de la loi n° 2024-449 du 21 mai 2024 : FactuSentinel ne perçoit à ce jour aucune
          contrepartie économique, sous quelque forme que ce soit.
        </p>
        <p>
          À ce titre, et conformément à cet article, l&apos;adresse de l&apos;éditeur
          n&apos;est pas rendue publique - elle est communiquée à l&apos;hébergeur
          mentionné ci-dessous et ne peut être transmise qu&apos;aux autorités judiciaires,
          sur réquisition.
        </p>
        <p>
          Contact : <a href="mailto:tchindajoel61@gmail.com">tchindajoel61@gmail.com</a>
        </p>
        <p>
          <strong>Cette qualification cessera de s&apos;appliquer dès l&apos;introduction
          d&apos;une offre payante</strong>, quel qu&apos;en soit le montant. Les présentes
          mentions légales seront alors intégralement mises à jour (identité complète,
          adresse, immatriculation) avant l&apos;activation de tout paiement.
        </p>
      </section>

      <section>
        <h2>2. Directeur de la publication</h2>
        <p>Tchinda Tchoffo Timothée Joël, en sa qualité d&apos;éditeur du site.</p>
      </section>

      <section>
        <h2>3. Hébergement</h2>
        <p>
          Le site et les services associés sont hébergés par :<br />
          OVH SAS
          <br />
          2 rue Kellermann, 59100 Roubaix, France
          <br />
          RCS Lille Métropole n° 424 761 419
        </p>
      </section>

      <section>
        <h2>4. Propriété intellectuelle</h2>
        <p>
          L&apos;ensemble des éléments constituant FactuSentinel (structure, textes,
          logiciels, bases de données, graphismes, à l&apos;exclusion des contenus
          téléversés par les utilisateurs) est protégé par le droit de la propriété
          intellectuelle et demeure la propriété exclusive de l&apos;éditeur. Toute
          reproduction, représentation, modification ou adaptation, totale ou partielle,
          sans autorisation préalable, est interdite.
        </p>
        <p>
          Les contenus téléversés par un utilisateur (documents, factures, données
          d&apos;entreprise) restent sa propriété exclusive - voir les{" "}
          <a href="/cgu">conditions générales d&apos;utilisation</a>.
        </p>
      </section>

      <section>
        <h2>5. Nature du service</h2>
        <p>
          FactuSentinel est un assistant de préparation, de contrôle et de compréhension de
          la conformité à la réforme française de la facturation électronique. Il
          n&apos;est ni une plateforme agréée, ni un logiciel de facturation, ni un
          logiciel comptable, ni un cabinet d&apos;expertise comptable, ni une autorité
          réglementaire. Le détail de ce périmètre figure dans les{" "}
          <a href="/cgu">conditions générales d&apos;utilisation</a>.
        </p>
      </section>

      <section>
        <h2>6. Protection des données personnelles</h2>
        <p>
          Le traitement des données personnelles par FactuSentinel est décrit dans la{" "}
          <a href="/politique-de-confidentialite">politique de confidentialité</a>.
        </p>
      </section>

      <section>
        <h2>7. Signalement d&apos;un contenu ou d&apos;un comportement illicite</h2>
        <p>
          Toute personne peut signaler un contenu ou un comportement qu&apos;elle estime
          contraire à la loi en écrivant à l&apos;adresse mentionnée à la section 1
          ci-dessus.
        </p>
      </section>
    </>
  );
}
