# Fixtures - Document (Phase 7)

- `pdf-simple.pdf` : PDF ordinaire généré par LibreOffice pour ces tests, sans XML embarqué
  (cas PDF_SIMPLE, US-COMPLIANCE-005).
- `facturx-valid.pdf` : échantillon Factur-X/ZUGFeRD valide, repris de la suite de test
  officielle de Mustangproject (`library/src/test/resources/zugferd_invoice.pdf`,
  https://github.com/ZUGFeRD/mustangproject, licence Apache-2.0) - fichier d'exemple public
  destiné aux tests, réutilisé tel quel.
- `ubl-sample.xml` : XML minimal portant le namespace UBL, uniquement pour tester la
  classification `DocumentFileFormat::UBL` (décision produit 2, plan Phase 7 : traitement non
  couvert par cette phase) - ne représente pas un document UBL valide/complet.
